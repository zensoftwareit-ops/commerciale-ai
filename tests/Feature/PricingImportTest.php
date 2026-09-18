<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\KnowledgeDocument;
use App\Models\PricingRule;
use App\Models\UsageRecord;
use App\Services\Ai\ImportPricingDocuments;
use App\Services\Ai\ImportedPricingGuidance;
use App\Services\Licensing\LicenseUsageGuard;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PricingImportTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commerciale-ai.ai_provider' => 'openai', 'commerciale-ai.openai.api_key' => 'test-only', 'commerciale-ai.openai.model' => 'test-model']);
        Http::preventStrayRequests();
    }

    private function draft(): array
    {
        return ['items' => [[
            'name' => 'Sito vetrina', 'keywords_text' => 'sito web, vetrina', 'minimum_price' => 1000, 'maximum_price' => 2000,
            'includes' => 'Cinque pagine e modulo contatti. EUR IVA esclusa, a progetto.',
            'excludes' => 'Hosting e dominio.', 'validity_days' => null, 'evidence' => 'listino.txt, sezione siti web.',
        ]], 'guidance' => 'Per più lingue chiedere un preventivo al commerciale.', 'warnings' => ['Validità non specificata.']];
    }

    private function fakeResponse(?array $draft = null, string $status = 'completed'): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'status' => $status, 'model' => 'test-model', 'usage' => ['input_tokens' => 150, 'output_tokens' => 80],
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($draft ?? $this->draft())]]]],
        ])]);
    }

    private function upload(): array
    {
        return ['explanation' => 'Crea il listino dai documenti allegati, prezzi in euro IVA esclusa.', 'consent' => '1',
            'attachments' => [UploadedFile::fake()->createWithContent('listino.txt', 'Sito vetrina: 1000-2000 euro IVA esclusa.')]];
    }

    private function generatedRun(): array
    {
        [$org, $owner] = $this->organizationWithUser();
        $this->fakeResponse();
        $this->actingAs($owner)->withSession(['organization_id' => $org->id])->post(route('pricing-import.generate'), $this->upload())
            ->assertSessionHasNoErrors()->assertRedirect();
        return [$org, $owner, AiRun::withoutGlobalScopes()->where('operation', 'pricing_import')->firstOrFail()];
    }

    private function selection(): array
    {
        return ['items' => [[...$this->draft()['items'][0], 'selected' => '1', 'validity_days' => 30, 'is_active' => '1']],
            'save_guidance' => '1', 'guidance' => 'Regola confermata: ulteriori lingue da quotare separatamente.'];
    }

    public function test_owner_can_upload_review_edit_and_apply_without_overwriting_existing_rules(): void
    {
        [$org, $owner, $run] = $this->generatedRun();
        $this->assertDatabaseCount('pricing_rules', 0);
        $this->assertDatabaseCount('knowledge_documents', 0);
        $this->assertSame(150, UsageRecord::withoutGlobalScopes()->first()->input_units);
        $this->assertStringNotContainsString('base64', json_encode($run->input_context));
        $this->get(route('pricing-import.create'))->assertOk()->assertSee('multipart/form-data', false);
        $this->get(route('pricing-import.preview', $run->id))->assertOk()->assertSee('Sito vetrina')->assertSee('Validità non specificata.');
        app(TenantContext::class)->set($org);
        $old = PricingRule::create(['name' => 'Esistente', 'keywords' => ['altro'], 'minimum_price' => 50, 'maximum_price' => 60, 'validity_days' => 15, 'is_active' => true]);
        app(TenantContext::class)->clear();
        $payload = $this->selection();
        $payload['items'][0]['name'] = 'Sito personalizzato';
        $payload['items'][0]['minimum_price'] = 1200;
        $this->post(route('pricing-import.apply', $run->id), $payload)->assertSessionHasNoErrors()->assertRedirect(route('settings.organization'));
        $this->assertDatabaseHas('pricing_rules', ['organization_id' => $org->id, 'name' => 'Sito personalizzato', 'minimum_price' => 1200, 'is_active' => true]);
        $this->assertDatabaseHas('pricing_rules', ['id' => $old->id, 'minimum_price' => 50]);
        $this->assertDatabaseHas('knowledge_documents', ['organization_id' => $org->id, 'content' => $payload['guidance'], 'status' => 'active']);
        $this->post(route('pricing-import.apply', $run->id), $payload)->assertRedirect();
        $this->assertDatabaseCount('pricing_rules', 2);
        $this->assertDatabaseCount('knowledge_documents', 1);
        $this->get(route('settings.organization'))->assertOk()->assertSee('Crea listini e regole da documenti');
        app(TenantContext::class)->set($org);
        $this->assertSame($payload['guidance'], app(ImportedPricingGuidance::class)->context()[0]['content']);
    }

    public function test_missing_prices_stay_blank_and_cannot_be_saved_as_zero(): void
    {
        [$org, $owner] = $this->organizationWithUser();
        $draft = $this->draft();
        $draft['items'][0]['minimum_price'] = null;
        $draft['items'][0]['maximum_price'] = null;
        $this->fakeResponse($draft);
        $this->actingAs($owner)->withSession(['organization_id' => $org->id])->post(route('pricing-import.generate'), $this->upload())->assertRedirect();
        $run = AiRun::withoutGlobalScopes()->firstOrFail();
        $this->get(route('pricing-import.preview', $run->id))->assertOk()->assertSee('Prezzo da completare');
        $payload = $this->selection();
        $payload['items'][0]['minimum_price'] = '';
        $this->from(route('pricing-import.preview', $run->id))->post(route('pricing-import.apply', $run->id), $payload)->assertSessionHasErrors('items.0');
        $this->assertDatabaseCount('pricing_rules', 0);
        $payload['items'][0]['minimum_price'] = 3000;
        $this->post(route('pricing-import.apply', $run->id), $payload)->assertSessionHasErrors('items.0');
        $this->assertDatabaseCount('knowledge_documents', 0);
    }

    public function test_it_can_save_guidance_alone_and_rules_default_to_inactive(): void
    {
        [$org, $owner, $run] = $this->generatedRun();
        $payload = $this->selection();
        unset($payload['items'][0]['is_active']);
        $payload['save_guidance'] = '0';
        $this->post(route('pricing-import.apply', $run->id), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pricing_rules', ['name' => 'Sito vetrina', 'is_active' => false]);
        $this->assertDatabaseCount('knowledge_documents', 0);
        $this->fakeResponse(['items' => [], 'guidance' => 'Solo regole senza prezzi.', 'warnings' => []]);
        $this->post(route('pricing-import.generate'), $this->upload())->assertSessionHasNoErrors();
        $newRun = AiRun::withoutGlobalScopes()->where('id', '!=', $run->id)->firstOrFail();
        $this->post(route('pricing-import.apply', $newRun->id), ['save_guidance' => 1, 'guidance' => 'Solo regole senza prezzi.'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('knowledge_documents', 1);
    }

    public function test_tenant_and_role_boundaries_protect_drafts_and_apply(): void
    {
        [$org, $owner, $run] = $this->generatedRun();
        [$other, $otherOwner] = $this->organizationWithUser();
        $this->actingAs($otherOwner)->withSession(['organization_id' => $other->id])->get(route('pricing-import.preview', $run->id))->assertNotFound();
        $this->post(route('pricing-import.apply', $run->id), $this->selection())->assertNotFound();
        [$third, $member] = $this->organizationWithUser('sales');
        $this->actingAs($member)->withSession(['organization_id' => $third->id])->get(route('pricing-import.create'))->assertForbidden();
        $this->post(route('pricing-import.generate'), $this->upload())->assertForbidden();
        $this->post(route('pricing-import.apply', $run->id), $this->selection())->assertForbidden();
        $this->assertDatabaseCount('pricing_rules', 0);
    }

    public function test_upload_validation_prevents_external_calls(): void
    {
        [$org, $owner] = $this->organizationWithUser();
        $this->actingAs($owner)->withSession(['organization_id' => $org->id]);
        $this->post(route('pricing-import.generate'), ['explanation' => 'Test'])->assertSessionHasErrors(['attachments', 'consent']);
        $payload = $this->upload();
        $payload['attachments'] = [UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;')];
        $this->post(route('pricing-import.generate'), $payload)->assertSessionHasErrors('attachments.0');
        $payload['attachments'] = [UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')];
        $this->post(route('pricing-import.generate'), $payload)->assertSessionHasErrors('attachments.0');
        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_runs', 0);
    }

    public function test_incomplete_and_invalid_responses_count_usage_but_never_create_pricing_rules(): void
    {
        [$org, $owner] = $this->organizationWithUser();
        $this->actingAs($owner)->withSession(['organization_id' => $org->id]);
        $this->fakeResponse(status: 'incomplete');
        $this->post(route('pricing-import.generate'), $this->upload())->assertSessionHasErrors('import');
        $this->assertDatabaseHas('ai_runs', ['status' => 'failed', 'operation' => 'pricing_import']);
        $this->assertDatabaseCount('usage_records', 1);
        $this->fakeResponse(['items' => [['name' => 'Invalid']], 'guidance' => '', 'warnings' => []]);
        $this->post(route('pricing-import.generate'), $this->upload())->assertSessionHasErrors('import');
        $this->assertDatabaseCount('usage_records', 2);
        $this->assertDatabaseCount('pricing_rules', 0);
    }

    public function test_a_semantically_invalid_recipe_does_not_discard_the_valid_pricing_draft(): void
    {
        [$org, $owner] = $this->organizationWithUser();
        $draft = $this->draft();
        $draft['items'][0]['pricing_formula'] = [
            'version' => 1,
            'variables' => [[
                'key' => 'destination', 'label' => 'Destinazione', 'type' => 'distance_km',
                'aliases' => ['destinazione'], 'required' => true,
            ]],
            'components' => [[
                'key' => 'transport', 'label' => 'Trasporto', 'operation' => 'multiply',
                'quantity_variable' => 'destination', 'unit_price' => 2,
            ]],
        ];
        $this->fakeResponse($draft);

        $this->actingAs($owner)->withSession(['organization_id' => $org->id])
            ->post(route('pricing-import.generate'), $this->upload())
            ->assertSessionHasNoErrors()->assertRedirect();

        $run = AiRun::withoutGlobalScopes()->where('operation', 'pricing_import')->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertNull($run->output['items'][0]['pricing_formula']);
        $this->assertStringContainsString('località di partenza', implode(' ', $run->output['warnings']));
        $this->get(route('pricing-import.preview', $run->id))->assertOk()->assertSee('Ricetta automatica non attivata');
    }

    public function test_legacy_daily_rates_from_model_output_are_never_imported_as_a_fallback_recipe(): void
    {
        [$org, $owner] = $this->organizationWithUser();
        $draft = $this->draft();
        $draft['items'][0]['daily_rate_tiers'] = [
            ['min_days' => 1, 'max_days' => 3, 'rate_per_day' => 550],
            ['min_days' => 4, 'max_days' => 8, 'rate_per_day' => 500],
        ];
        $draft['items'][0]['origin_address'] = 'Località inventata';
        $draft['items'][0]['distance_rate_per_km'] = 2;
        $draft['items'][0]['distance_round_trip'] = true;
        $draft['items'][0]['pricing_formula'] = null;
        $this->fakeResponse($draft);

        $this->actingAs($owner)->withSession(['organization_id' => $org->id])
            ->post(route('pricing-import.generate'), $this->upload())
            ->assertSessionHasNoErrors()->assertRedirect();

        $output = AiRun::withoutGlobalScopes()->where('operation', 'pricing_import')->firstOrFail()->output;
        $this->assertSame([], $output['items'][0]['daily_rate_tiers']);
        $this->assertNull($output['items'][0]['origin_address']);
        $this->assertNull($output['items'][0]['distance_rate_per_km']);
        $this->assertFalse($output['items'][0]['distance_round_trip']);
        $this->assertStringContainsString('Nessuna ricetta eseguibile', implode(' ', $output['warnings']));
        $this->assertSame('object', ImportPricingDocuments::schema()['properties']['items']['items']['properties']['pricing_formula']['type']);
    }

    public function test_images_and_documents_use_distinct_multimodal_input_types(): void
    {
        [$org, $owner] = $this->organizationWithUser();
        $this->fakeResponse();
        $payload = $this->upload();
        $payload['attachments'][] = UploadedFile::fake()->createWithContent('esempio.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $this->actingAs($owner)->withSession(['organization_id' => $org->id])->post(route('pricing-import.generate'), $payload)->assertSessionHasNoErrors();
        Http::assertSent(fn ($request) => $request['store'] === false
            && $request['input'][1]['content'][1]['type'] === 'input_file'
            && $request['input'][1]['content'][2]['type'] === 'input_image'
            && str_starts_with($request['input'][1]['content'][2]['image_url'], 'data:image/png;base64,'));
    }

    public function test_budget_exhaustion_and_provider_failure_leave_existing_configuration_untouched(): void
    {
        [$org, $owner] = $this->organizationWithUser();
        $this->actingAs($owner)->withSession(['organization_id' => $org->id]);
        $this->mock(LicenseUsageGuard::class)->shouldReceive('assertAiCapacity')->once()
            ->andThrow(ValidationException::withMessages(['license' => 'Budget AI mensile esaurito.']));
        $this->post(route('pricing-import.generate'), $this->upload())->assertSessionHasErrors('import');
        Http::assertNothingSent();
        $this->app->forgetInstance(LicenseUsageGuard::class);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'Internal provider details']], 429)]);
        $this->post(route('pricing-import.generate'), $this->upload())->assertSessionHasErrors('import');
        $this->assertDatabaseCount('pricing_rules', 0);
        $this->assertDatabaseCount('knowledge_documents', 0);
        $this->assertDatabaseHas('ai_runs', ['status' => 'failed', 'error_code' => 'pricing_import_failed']);
    }

    public function test_total_size_and_file_count_are_limited(): void
    {
        [$org, $owner] = $this->organizationWithUser();
        $this->actingAs($owner)->withSession(['organization_id' => $org->id]);
        $payload = $this->upload();
        $payload['attachments'] = array_map(fn ($i) => UploadedFile::fake()->create('listino'.$i.'.pdf', 7500, 'application/pdf'), range(1, 3));
        $this->post(route('pricing-import.generate'), $payload)->assertSessionHasErrors('attachments');
        $payload['attachments'] = array_map(fn ($i) => UploadedFile::fake()->createWithContent('listino'.$i.'.txt', 'Prezzo 10 euro'), range(1, 6));
        $this->post(route('pricing-import.generate'), $payload)->assertSessionHasErrors('attachments');
        Http::assertNothingSent();
    }

    public function test_imported_guidance_excludes_other_tenants_and_archived_documents(): void
    {
        [$org, $owner, $run] = $this->generatedRun();
        $this->post(route('pricing-import.apply', $run->id), $this->selection())->assertSessionHasNoErrors();
        app(TenantContext::class)->set($org);
        $document = KnowledgeDocument::query()->firstOrFail();
        $document->update(['content' => 'Regola ottimizzata dopo l’importazione.']);
        $this->assertSame('Regola ottimizzata dopo l’importazione.', app(ImportedPricingGuidance::class)->context()[0]['content']);
        $document->update(['status' => 'archived']);
        $this->assertSame([], app(ImportedPricingGuidance::class)->context());
        $document->update(['status' => 'active']);
        app(TenantContext::class)->clear();
        [$other] = $this->organizationWithUser();
        app(TenantContext::class)->set($other);
        $this->assertSame([], app(ImportedPricingGuidance::class)->context());
    }

    public function test_a_pdf_is_sent_inline_without_remote_file_storage(): void
    {
        [$org, $owner] = $this->organizationWithUser();
        $this->fakeResponse();
        $payload = $this->upload();
        $payload['attachments'] = [UploadedFile::fake()->createWithContent('listino.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")];
        $this->actingAs($owner)->withSession(['organization_id' => $org->id])->post(route('pricing-import.generate'), $payload)->assertSessionHasNoErrors();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['input'][1]['content'][1]['filename'] === 'listino.pdf'
            && str_starts_with($request['input'][1]['content'][1]['file_data'], 'data:application/pdf;base64,'));
    }

    public function test_a_connection_timeout_returns_a_useful_reference(): void
    {
        [$org, $owner] = $this->organizationWithUser();
        Http::fake(fn () => throw new ConnectionException('cURL error 28: operation timed out'));
        $response = $this->actingAs($owner)->withSession(['organization_id' => $org->id])
            ->post(route('pricing-import.generate'), $this->upload());
        $response->assertSessionHasErrors('import');
        $message = $response->getSession()->get('errors')->first('import');
        $this->assertStringContainsString('non ha risposto entro il tempo previsto', $message);
        $run = AiRun::withoutGlobalScopes()->firstOrFail();
        $this->assertStringContainsString($run->id, $message);
        $this->assertSame('pricing_import_timeout', $run->error_code);
        $this->assertDatabaseCount('pricing_rules', 0);
    }
}

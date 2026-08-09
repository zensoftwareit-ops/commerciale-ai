<?php

namespace Tests\Feature;

use App\Models\KnowledgeDocument;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KnowledgeTenantIsolationTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_document_from_another_tenant_is_not_accessible(): void
    {
        [$first, $firstUser] = $this->organizationWithUser();
        [$second] = $this->organizationWithUser();
        app(TenantContext::class)->set($second);
        $document = KnowledgeDocument::create(['title' => 'Riservato', 'type' => 'text', 'content' => 'Dato del secondo tenant', 'status' => 'active']);
        app(TenantContext::class)->clear();

        $this->actingAs($firstUser)->withSession(['organization_id' => $first->id])->get(route('knowledge.edit', $document))->assertNotFound();
    }
}

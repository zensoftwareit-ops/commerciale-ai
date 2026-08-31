<?php

namespace App\Services\Privacy;

use App\Models\InboundSource;
use App\Models\KnowledgeDocument;
use App\Models\Lead;
use App\Models\MailboxAccount;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationDataExporter
{
    public function download(Organization $organization): StreamedResponse
    {
        $filename = 'daria-export-'.$organization->slug.'-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($organization): void {
            $settings = $organization->settings()->withoutGlobalScopes()->first();
            $users = $organization->users()->get()->map(fn ($user): array => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);
            $sources = InboundSource::withoutGlobalScopes()->where('organization_id', $organization->id)->get()
                ->map(fn (InboundSource $source): array => $source->only(['id', 'name', 'allowed_domains', 'is_active', 'created_at']));
            $mailboxes = MailboxAccount::withoutGlobalScopes()->where('organization_id', $organization->id)->get()
                ->map(fn (MailboxAccount $mailbox): array => $mailbox->only([
                    'id', 'name', 'from_address', 'from_name', 'reply_to_address', 'host', 'port', 'encryption',
                    'username', 'folder', 'is_active', 'domain_verification_status', 'domain_verified_at', 'created_at',
                ]));
            $knowledge = KnowledgeDocument::withoutGlobalScopes()->where('organization_id', $organization->id)->get();

            echo '{"exported_at":'.json_encode(now()->toIso8601String(), JSON_THROW_ON_ERROR);
            echo ',"organization":'.json_encode($organization->only(['id', 'name', 'slug', 'timezone', 'locale', 'status', 'created_at']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            echo ',"settings":'.json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            echo ',"users":'.json_encode($users, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            echo ',"sources":'.json_encode($sources, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            echo ',"mailboxes":'.json_encode($mailboxes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            echo ',"knowledge_documents":'.json_encode($knowledge, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            foreach (['pipeline_stages', 'qualification_profiles', 'prompt_policies', 'pricing_rules', 'ai_runs', 'usage_records', 'webhook_receipts', 'commercial_notifications', 'licenses'] as $table) {
                echo ','.json_encode($table, JSON_THROW_ON_ERROR).':[';
                $firstRow = true;
                DB::table($table)->where('organization_id', $organization->id)->orderBy('id')
                    ->chunkById(100, function ($rows) use (&$firstRow): void {
                        foreach ($rows as $row) {
                            echo $firstRow ? '' : ',';
                            echo json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                            $firstRow = false;
                        }
                    }, 'id');
                echo ']';
            }
            echo ',"leads":[';

            $first = true;
            Lead::withoutGlobalScopes()->where('organization_id', $organization->id)
                ->with([
                    'contacts' => fn ($query) => $query->withoutGlobalScopes(),
                    'activities' => fn ($query) => $query->withoutGlobalScopes(),
                    'analyses' => fn ($query) => $query->withoutGlobalScopes(),
                    'replies' => fn ($query) => $query->withoutGlobalScopes(),
                    'inboundEmails' => fn ($query) => $query->withoutGlobalScopes(),
                    'quotations' => fn ($query) => $query->withoutGlobalScopes(),
                ])
                ->orderBy('id')
                ->chunkById(100, function ($leads) use (&$first): void {
                    foreach ($leads as $lead) {
                        echo $first ? '' : ',';
                        echo json_encode($lead, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                        $first = false;
                    }
                }, 'id');
            echo ']}';
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }
}

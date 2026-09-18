<?php

namespace App\Services\Organizations;

use App\Models\Organization;
use App\Services\Licensing\OrganizationProvisioner;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResetOrganizationWorkspace
{
    public function __construct(
        private readonly OrganizationProvisioner $provisioner,
        private readonly Filesystem $filesystem,
    ) {}

    /** @return array{members_detached:int, storage_deleted:bool} */
    public function handle(Organization $organization): array
    {
        $organizationId = (string) $organization->id;
        $ownerIds = DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->where('role', 'owner')
            ->pluck('user_id');
        if ($ownerIds->isEmpty()) {
            throw new RuntimeException('Il cliente non ha un owner: assegna prima un titolare al workspace.');
        }
        $membersDetached = 0;

        DB::transaction(function () use ($organization, $organizationId, $ownerIds, &$membersDetached): void {
            $membersDetached = DB::table('organization_user')
                ->where('organization_id', $organizationId)
                ->whereNotIn('user_id', $ownerIds)
                ->delete();
            // Remove queued work first, otherwise an old AI job could write data back after the reset.
            DB::table('jobs')->where('payload', 'like', '%'.$organizationId.'%')->delete();
            DB::table('failed_jobs')->where('payload', 'like', '%'.$organizationId.'%')->delete();

            foreach ([
                'whatsapp_messages', 'commercial_notifications', 'inbound_emails', 'quotations',
                'lead_replies', 'usage_records', 'ai_analyses', 'activities', 'lead_contacts',
                'webhook_receipts', 'ai_runs', 'leads', 'pricing_rules', 'whatsapp_accounts',
                'mailbox_accounts', 'inbound_sources', 'knowledge_documents', 'prompt_policies',
                'qualification_profiles', 'quotation_counters', 'organization_settings', 'pipeline_stages',
            ] as $table) {
                DB::table($table)->where('organization_id', $organizationId)->delete();
            }

            $organization->update([
                'status' => 'onboarding',
                'onboarding_completed_at' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);
            $this->provisioner->initializeWorkspace($organization);
        });

        $storageDeleted = $this->filesystem->deleteDirectory(storage_path('app/private/organizations/'.$organizationId));
        $this->filesystem->deleteDirectory(storage_path('app/private/pricing-imports/'.$organizationId));

        return ['members_detached' => $membersDetached, 'storage_deleted' => $storageDeleted];
    }
}

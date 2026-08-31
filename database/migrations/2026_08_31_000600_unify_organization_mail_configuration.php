<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailbox_accounts', function (Blueprint $table): void {
            $table->string('from_address')->nullable()->after('name');
            $table->string('from_name')->nullable()->after('from_address');
            $table->string('reply_to_address')->nullable()->after('from_name');
            $table->timestampTz('last_outbound_tested_at')->nullable()->after('last_tested_at');
            $table->text('last_outbound_error')->nullable()->after('last_error');
        });
        Schema::table('lead_replies', function (Blueprint $table): void {
            $table->string('reply_to_address')->nullable()->after('sender_name');
        });

        $configuredOrganizations = [];
        DB::table('mailbox_accounts')->orderBy('created_at')->orderBy('id')->get()->each(function (object $mailbox) use (&$configuredOrganizations): void {
            $firstForOrganization = ! isset($configuredOrganizations[$mailbox->organization_id]);
            $configuredOrganizations[$mailbox->organization_id] = true;
            $address = filter_var($mailbox->username, FILTER_VALIDATE_EMAIL)
                ? mb_strtolower(trim($mailbox->username))
                : null;
            DB::table('mailbox_accounts')->where('id', $mailbox->id)->update([
                'name' => $firstForOrganization ? 'Email Daria' : $mailbox->name,
                'from_address' => $firstForOrganization ? $address : null,
                'from_name' => $firstForOrganization ? (trim((string) $mailbox->name) ?: 'Daria') : null,
                'reply_to_address' => $firstForOrganization ? $address : null,
                'is_active' => $firstForOrganization ? $mailbox->is_active : false,
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['mail_from_address', 'mail_from_name']);
        });
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->dropColumn('authorized_sender');
        });
    }

    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->string('authorized_sender')->nullable();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('mail_from_address')->nullable()->after('external_account_id');
            $table->string('mail_from_name')->nullable()->after('mail_from_address');
        });
        Schema::table('lead_replies', function (Blueprint $table): void {
            $table->dropColumn('reply_to_address');
        });
        Schema::table('mailbox_accounts', function (Blueprint $table): void {
            $table->dropColumn(['from_address', 'from_name', 'reply_to_address', 'last_outbound_tested_at', 'last_outbound_error']);
        });
    }
};

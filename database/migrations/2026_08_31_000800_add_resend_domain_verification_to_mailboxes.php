<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailbox_accounts', function (Blueprint $table): void {
            $table->string('resend_domain_id')->nullable()->after('domain_verified_by')->index();
            $table->string('resend_domain_name')->nullable()->after('resend_domain_id');
            $table->string('resend_domain_status')->nullable()->after('resend_domain_name');
            $table->json('resend_dns_records')->nullable()->after('resend_domain_status');
            $table->timestampTz('resend_last_checked_at')->nullable()->after('resend_dns_records');
            $table->text('resend_last_error')->nullable()->after('resend_last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('mailbox_accounts', function (Blueprint $table): void {
            $table->dropIndex(['resend_domain_id']);
            $table->dropColumn([
                'resend_domain_id', 'resend_domain_name', 'resend_domain_status',
                'resend_dns_records', 'resend_last_checked_at', 'resend_last_error',
            ]);
        });
    }
};

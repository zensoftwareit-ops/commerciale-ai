<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailbox_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption', 10)->nullable()->default('ssl');
            $table->boolean('validate_cert')->default(true);
            $table->string('username');
            $table->text('password');
            $table->string('authentication', 30)->nullable();
            $table->string('folder')->default('INBOX');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_tested_at')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'is_active']);
            $table->unique(['organization_id', 'username', 'host']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::table('inbound_emails', function (Blueprint $table): void {
            $table->uuid('mailbox_account_id')->nullable()->after('organization_id');
            $table->index(['mailbox_account_id', 'imap_uid']);
            $table->foreign('mailbox_account_id')->references('id')->on('mailbox_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inbound_emails', function (Blueprint $table): void {
            $table->dropForeign(['mailbox_account_id']);
            $table->dropIndex(['mailbox_account_id', 'imap_uid']);
            $table->dropColumn('mailbox_account_id');
        });
        Schema::dropIfExists('mailbox_accounts');
    }
};


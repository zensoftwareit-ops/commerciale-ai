<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->unique();
            $table->string('name')->default('WhatsApp Daria');
            $table->string('waba_id');
            $table->string('phone_number_id')->unique();
            $table->string('display_phone_number');
            $table->text('access_token');
            $table->boolean('is_active')->default(false);
            $table->boolean('auto_reply_enabled')->default(false);
            $table->boolean('internal_test_only')->default(true);
            $table->json('allowed_recipients')->nullable();
            $table->timestampTz('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::table('lead_replies', function (Blueprint $table): void {
            $table->string('channel', 20)->default('email')->after('status');
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('whatsapp_account_id');
            $table->uuid('lead_id')->nullable();
            $table->uuid('lead_reply_id')->nullable();
            $table->string('external_message_id')->unique();
            $table->string('direction', 10);
            $table->string('type', 30)->default('text');
            $table->string('status', 30)->default('pending');
            $table->string('from_number', 40);
            $table->string('to_number', 40);
            $table->longText('body')->nullable();
            $table->json('payload')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'received_at']);
            $table->index(['organization_id', 'lead_id', 'received_at']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('whatsapp_account_id')->references('id')->on('whatsapp_accounts')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('lead_reply_id')->references('id')->on('lead_replies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::table('lead_replies', fn (Blueprint $table) => $table->dropColumn('channel'));
        Schema::dropIfExists('whatsapp_accounts');
    }
};

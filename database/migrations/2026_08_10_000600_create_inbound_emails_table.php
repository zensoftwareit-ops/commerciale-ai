<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_replies', function (Blueprint $table): void {
            $table->string('outbound_message_id')->nullable()->unique()->after('status');
            $table->string('parent_message_id')->nullable()->after('outbound_message_id');
            $table->timestampTz('follow_up_cancelled_at')->nullable()->after('follow_up_at');
        });

        Schema::create('inbound_emails', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('lead_id');
            $table->uuid('lead_reply_id')->nullable();
            $table->char('message_hash', 64)->unique();
            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->string('imap_uid')->nullable();
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->timestampTz('received_at');
            $table->timestamps();
            $table->index(['organization_id', 'lead_id', 'received_at']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('lead_reply_id')->references('id')->on('lead_replies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_emails');
        Schema::table('lead_replies', function (Blueprint $table): void {
            $table->dropUnique(['outbound_message_id']);
            $table->dropColumn(['outbound_message_id', 'parent_message_id', 'follow_up_cancelled_at']);
        });
    }
};

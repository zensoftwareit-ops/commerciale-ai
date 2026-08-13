<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_emails', function (Blueprint $table): void {
            $table->dropForeign(['lead_id']);
            $table->uuid('lead_id')->nullable()->change();
            $table->string('status', 20)->default('linked')->after('lead_reply_id');
            $table->string('match_confidence', 20)->nullable()->after('status');
            $table->string('match_reason', 50)->nullable()->after('match_confidence');
            $table->boolean('sender_differs')->default(false)->after('match_reason');
            $table->foreignId('linked_by')->nullable()->after('received_at');
            $table->timestampTz('linked_at')->nullable()->after('linked_by');
            $table->index(['organization_id', 'status', 'received_at']);
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            $table->foreign('linked_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inbound_emails', function (Blueprint $table): void {
            $table->dropForeign(['lead_id']);
            $table->dropForeign(['linked_by']);
            $table->dropIndex(['organization_id', 'status', 'received_at']);
            $table->dropColumn(['status', 'match_confidence', 'match_reason', 'sender_differs', 'linked_by', 'linked_at']);
            $table->uuid('lead_id')->nullable(false)->change();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });
    }
};

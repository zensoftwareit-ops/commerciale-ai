<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_replies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('lead_id');
            $table->uuid('ai_analysis_id')->nullable();
            $table->uuid('ai_run_id')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('recipient');
            $table->string('subject');
            $table->longText('body');
            $table->timestampTz('follow_up_at')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'follow_up_at']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('ai_analysis_id')->references('id')->on('ai_analyses')->nullOnDelete();
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_replies');
    }
};

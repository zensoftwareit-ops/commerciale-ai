<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('organization_settings')->update([
            'auto_analyze_new_leads' => true,
            'direct_quote_enabled' => true,
            'quotation_review_mode' => true,
            'auto_send_initial_email' => false,
            'conversation_automation_enabled' => false,
            'auto_send_quotes_enabled' => false,
            'new_lead_automation_started_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('organization_settings')->update([
            'direct_quote_enabled' => false,
            'quotation_review_mode' => false,
            'updated_at' => now(),
        ]);
    }
};

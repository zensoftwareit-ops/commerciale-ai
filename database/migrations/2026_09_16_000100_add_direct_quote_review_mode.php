<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->boolean('direct_quote_enabled')->default(false)->after('auto_send_initial_email');
            $table->boolean('quotation_review_mode')->default(false)->after('direct_quote_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('organization_settings', fn (Blueprint $table) => $table->dropColumn([
            'direct_quote_enabled', 'quotation_review_mode',
        ]));
    }
};

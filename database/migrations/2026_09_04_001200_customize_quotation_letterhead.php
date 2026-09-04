<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->string('quotation_primary_color', 7)->default('#169BD5');
            $table->text('quotation_header_text')->nullable();
            $table->text('quotation_intro_text')->nullable();
            $table->string('quotation_footer_left')->nullable();
            $table->string('quotation_footer_center')->nullable();
            $table->string('quotation_footer_right')->nullable();
            $table->text('quotation_acceptance_text')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organization_settings', fn (Blueprint $table) => $table->dropColumn([
            'quotation_primary_color', 'quotation_header_text', 'quotation_intro_text',
            'quotation_footer_left', 'quotation_footer_center', 'quotation_footer_right',
            'quotation_acceptance_text',
        ]));
    }
};

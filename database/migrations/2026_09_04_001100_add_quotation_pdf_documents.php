<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_counters', function (Blueprint $table): void {
            $table->uuid('organization_id');
            $table->unsignedSmallInteger('document_year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->primary(['organization_id', 'document_year']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->string('quotation_logo_path')->nullable();
            $table->text('quotation_company_details')->nullable();
            $table->text('quotation_payment_terms')->nullable();
            $table->text('quotation_footer')->nullable();
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->unsignedSmallInteger('document_year')->nullable();
            $table->unsignedInteger('document_sequence')->nullable();
            $table->string('document_number')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestampTz('pdf_generated_at')->nullable();
            $table->unique(['organization_id', 'document_year', 'document_sequence'], 'quotations_document_sequence_unique');
            $table->unique(['organization_id', 'document_number'], 'quotations_document_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropUnique('quotations_document_sequence_unique');
            $table->dropUnique('quotations_document_number_unique');
            $table->dropColumn(['document_year', 'document_sequence', 'document_number', 'valid_until', 'pdf_path', 'pdf_generated_at']);
        });
        Schema::table('organization_settings', fn (Blueprint $table) => $table->dropColumn([
            'quotation_logo_path', 'quotation_company_details', 'quotation_payment_terms', 'quotation_footer',
        ]));
        Schema::dropIfExists('quotation_counters');
    }
};

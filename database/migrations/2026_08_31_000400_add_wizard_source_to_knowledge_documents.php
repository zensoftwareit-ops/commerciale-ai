<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->string('source', 30)->nullable()->after('status');
            $table->string('source_key', 100)->nullable()->after('source');
            $table->unique(['organization_id', 'source_key'], 'knowledge_documents_org_source_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->dropUnique('knowledge_documents_org_source_key_unique');
            $table->dropColumn(['source', 'source_key']);
        });
    }
};

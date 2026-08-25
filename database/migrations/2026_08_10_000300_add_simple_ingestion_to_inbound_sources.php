<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_sources', function (Blueprint $table): void {
            $table->json('allowed_domains')->nullable()->after('secret');
            $table->string('endpoint_token_hash', 64)->nullable()->unique()->after('allowed_domains');
        });
        Schema::table('webhook_receipts', function (Blueprint $table): void {
            $table->string('source_domain')->nullable()->after('payload_hash');
            $table->string('validation_mode', 30)->nullable()->after('source_domain');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_receipts', function (Blueprint $table): void {
            $table->dropColumn(['source_domain', 'validation_mode']);
        });
        Schema::table('inbound_sources', function (Blueprint $table): void {
            $table->dropUnique(['endpoint_token_hash']);
            $table->dropColumn(['allowed_domains', 'endpoint_token_hash']);
        });
    }
};

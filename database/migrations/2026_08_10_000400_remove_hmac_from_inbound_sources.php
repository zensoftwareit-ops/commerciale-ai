<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_sources', function (Blueprint $table): void {
            $table->dropUnique(['key']);
            $table->dropColumn(['key', 'secret']);
        });
    }

    public function down(): void
    {
        Schema::table('inbound_sources', function (Blueprint $table): void {
            $table->string('key')->nullable()->unique()->after('name');
            $table->text('secret')->nullable()->after('key');
        });
    }
};

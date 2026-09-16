<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_rules', fn (Blueprint $table) => $table->json('pricing_formula')->nullable()->after('distance_round_trip'));
    }

    public function down(): void
    {
        Schema::table('pricing_rules', fn (Blueprint $table) => $table->dropColumn('pricing_formula'));
    }
};

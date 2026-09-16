<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table): void {
            $table->json('daily_rate_tiers')->nullable()->after('required_fields');
            $table->string('origin_address')->nullable()->after('daily_rate_tiers');
            $table->decimal('distance_rate_per_km', 10, 2)->nullable()->after('origin_address');
            $table->boolean('distance_round_trip')->default(true)->after('distance_rate_per_km');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_rules', fn (Blueprint $table) => $table->dropColumn([
            'daily_rate_tiers', 'origin_address', 'distance_rate_per_km', 'distance_round_trip',
        ]));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->decimal('estimated_price', 12, 2)->nullable()->after('maximum_price');
            $table->unsignedTinyInteger('complexity_score')->nullable()->after('confidence');
            $table->string('scope_title')->nullable()->after('complexity_score');
            $table->text('scope_description')->nullable()->after('scope_title');
            $table->json('line_items')->nullable()->after('scope_description');
            $table->json('assumptions')->nullable()->after('line_items');
            $table->text('estimate_rationale')->nullable()->after('assumptions');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn([
            'estimated_price', 'complexity_score', 'scope_title', 'scope_description',
            'line_items', 'assumptions', 'estimate_rationale',
        ]));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('mail_from_address')->nullable()->after('external_account_id');
            $table->string('mail_from_name')->nullable()->after('mail_from_address');
        });
        Schema::table('lead_replies', function (Blueprint $table): void {
            $table->string('sender_address')->nullable()->after('recipient');
            $table->string('sender_name')->nullable()->after('sender_address');
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'mail_from_address' => $user->email,
                'mail_from_name' => $user->name,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('lead_replies', function (Blueprint $table): void {
            $table->dropColumn(['sender_address', 'sender_name']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['mail_from_address', 'mail_from_name']);
        });
    }
};

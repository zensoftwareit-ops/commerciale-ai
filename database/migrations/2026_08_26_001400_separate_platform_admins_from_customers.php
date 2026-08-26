<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('is_super_admin', true)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('organization_user')
                    ->whereColumn('organization_user.user_id', 'users.id');
            })
            ->update(['is_super_admin' => false]);
    }

    public function down(): void
    {
        // La separazione degli account non viene annullata automaticamente.
    }
};

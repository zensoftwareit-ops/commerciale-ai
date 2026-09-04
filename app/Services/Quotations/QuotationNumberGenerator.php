<?php

namespace App\Services\Quotations;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class QuotationNumberGenerator
{
    /** @return array{year:int,sequence:int,number:string} */
    public function next(string $organizationId, ?int $year = null): array
    {
        $year ??= (int) now()->format('Y');
        $sequence = DB::transaction(function () use ($organizationId, $year): int {
            $counter = DB::table('quotation_counters')
                ->where('organization_id', $organizationId)->where('document_year', $year)
                ->lockForUpdate()->first();
            if (! $counter) {
                try {
                    DB::table('quotation_counters')->insert([
                        'organization_id' => $organizationId, 'document_year' => $year,
                        'last_number' => 1, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    return 1;
                } catch (QueryException) {
                    $counter = DB::table('quotation_counters')
                        ->where('organization_id', $organizationId)->where('document_year', $year)
                        ->lockForUpdate()->firstOrFail();
                }
            }
            $next = (int) $counter->last_number + 1;
            DB::table('quotation_counters')->where('organization_id', $organizationId)
                ->where('document_year', $year)->update(['last_number' => $next, 'updated_at' => now()]);
            return $next;
        }, 3);

        return ['year' => $year, 'sequence' => $sequence, 'number' => sprintf('OFF-%d-%05d', $year, $sequence)];
    }
}

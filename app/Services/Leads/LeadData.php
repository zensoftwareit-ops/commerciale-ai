<?php

namespace App\Services\Leads;

class LeadData
{
    public static function normalizeEmail(?string $email): ?string
    {
        return $email ? mb_strtolower(trim($email)) : null;
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $normalized = preg_replace('/[^0-9+]/', '', trim($phone));

        return $normalized !== '' ? $normalized : null;
    }
}

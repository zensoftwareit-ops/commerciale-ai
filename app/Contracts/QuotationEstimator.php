<?php

namespace App\Contracts;

interface QuotationEstimator
{
    /** @return array<string, mixed> */
    public function estimate(array $input): array;
}

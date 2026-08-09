<?php

namespace App\Contracts;

use App\Models\Lead;

interface LeadAnalyzer
{
    /** @return array<string, mixed> */
    public function analyze(Lead $lead): array;
}

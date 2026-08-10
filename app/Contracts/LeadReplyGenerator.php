<?php

namespace App\Contracts;

use App\Models\AiAnalysis;
use App\Models\Lead;

interface LeadReplyGenerator
{
    public function generate(Lead $lead, AiAnalysis $analysis, array $context = []): array;
}

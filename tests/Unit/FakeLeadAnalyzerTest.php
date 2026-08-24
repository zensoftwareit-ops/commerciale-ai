<?php

namespace Tests\Unit;

use App\Contracts\LeadAnalyzer;
use App\Models\Lead;
use Tests\TestCase;

class FakeLeadAnalyzerTest extends TestCase
{
    public function test_fake_analyzer_is_deterministic_and_structured(): void
    {
        $lead = new Lead(['name' => 'Demo', 'requested_service' => 'Sito web', 'request_data' => []]);
        $result = app(LeadAnalyzer::class)->analyze($lead);
        $this->assertSame('fake', $result['_meta']['provider']);
        $this->assertSame(50, $result['fit_score']);
        $this->assertArrayHasKey('missing_information', $result);
    }
}

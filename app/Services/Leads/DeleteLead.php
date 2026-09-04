<?php

namespace App\Services\Leads;

use App\Models\AiRun;
use App\Models\InboundEmail;
use App\Models\Lead;
use App\Models\WebhookReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteLead
{
    public function handle(Lead $lead): void
    {
        $quotationFiles = $lead->quotations()->pluck('pdf_path')->filter()->all();
        DB::transaction(function () use ($lead): void {
            $aiRunIds = AiRun::query()->where('lead_id', $lead->id)->pluck('id');

            InboundEmail::query()->where('lead_id', $lead->id)->delete();
            WebhookReceipt::query()->where('lead_id', $lead->id)->delete();
            $lead->delete();

            AiRun::query()->whereIn('id', $aiRunIds)->delete();
        });
        Storage::disk('local')->delete($quotationFiles);
    }
}

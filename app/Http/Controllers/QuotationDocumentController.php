<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Quotation;
use App\Services\Quotations\QuotationPdfGenerator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuotationDocumentController extends Controller
{
    public function __invoke(string $lead, string $quotation, QuotationPdfGenerator $generator): StreamedResponse
    {
        $lead = Lead::query()->findOrFail($lead);
        $quotation = Quotation::query()->whereKey($quotation)->where('lead_id', $lead->id)->firstOrFail();
        $path = $generator->ensure($quotation);

        return Storage::disk('local')->download($path, $generator->filename($quotation), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

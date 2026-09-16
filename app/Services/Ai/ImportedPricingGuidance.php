<?php

namespace App\Services\Ai;

use App\Models\KnowledgeDocument;

class ImportedPricingGuidance
{
    public function context(): array
    {
        return KnowledgeDocument::query()->where('status', 'active')->where('source', 'pricing_import')
            ->latest('updated_at')->limit(10)->get(['title', 'content'])
            ->map(fn ($document) => ['title' => $document->title, 'content' => mb_substr($document->content, 0, 20000)])->all();
    }
}

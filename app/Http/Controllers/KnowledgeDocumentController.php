<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeDocumentController extends Controller
{
    public function index(): View
    {
        $documents = KnowledgeDocument::query()->latest('updated_at')->paginate(30);

        return view('knowledge.index', compact('documents'));
    }

    public function create(): View
    {
        return view('knowledge.form', ['document' => new KnowledgeDocument]);
    }

    public function store(Request $request): RedirectResponse
    {
        KnowledgeDocument::create([...$this->validated($request), 'updated_by' => $request->user()->id]);

        return redirect()->route('knowledge.index')->with('status', 'Contenuto aggiunto alla knowledge base.');
    }

    public function edit(string $document): View
    {
        $document = KnowledgeDocument::query()->findOrFail($document);

        return view('knowledge.form', compact('document'));
    }

    public function update(Request $request, string $document): RedirectResponse
    {
        $document = KnowledgeDocument::query()->findOrFail($document);
        $document->update([...$this->validated($request), 'updated_by' => $request->user()->id]);

        return redirect()->route('knowledge.index')->with('status', 'Contenuto aggiornato.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:text,faq,service,pricing'],
            'content' => ['required', 'string', 'max:50000'], 'status' => ['required', 'in:draft,active,archived'],
        ]);
    }
}

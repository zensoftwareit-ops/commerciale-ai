<?php

namespace App\Http\Controllers;

use App\Models\InboundSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InboundSourceController extends Controller
{
    public function index(): View
    {
        return view('settings.sources', [
            'sources' => InboundSource::query()->orderBy('name')->get(),
            'endpoint' => url('/api/v1/inbound/leads'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $secret = Str::random(64);
        $source = InboundSource::create([
            'name' => $data['name'],
            'key' => Str::slug($data['name']).'-'.Str::lower(Str::random(10)),
            'secret' => $secret,
            'is_active' => true,
        ]);

        return back()
            ->with('status', 'Sorgente creata. Copia ora il segreto: non verrà mostrato di nuovo.')
            ->with('webhook_credentials', ['id' => $source->id, 'key' => $source->key, 'secret' => $secret]);
    }

    public function rotate(InboundSource $source): RedirectResponse
    {
        $secret = Str::random(64);
        $source->update(['secret' => $secret]);

        return back()
            ->with('status', 'Segreto ruotato. Quello precedente non è più valido.')
            ->with('webhook_credentials', ['id' => $source->id, 'key' => $source->key, 'secret' => $secret]);
    }
}

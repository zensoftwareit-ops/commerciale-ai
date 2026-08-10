<?php

namespace App\Http\Controllers;

use App\Models\InboundSource;
use App\Services\Leads\InboundDomainGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InboundSourceController extends Controller
{
    public function index(): View
    {
        return view('settings.sources', [
            'sources' => InboundSource::query()->orderBy('name')->get(),
            'legacyEndpoint' => url('/api/v1/inbound/leads'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'allowed_domains_text' => ['required', 'string', 'max:5000'],
        ]);
        $domains = $this->domains($data['allowed_domains_text']);
        $this->ensureDomains($domains);
        $secret = Str::random(64);
        $endpointToken = Str::random(64);
        $source = InboundSource::create([
            'name' => $data['name'],
            'key' => Str::slug($data['name']).'-'.Str::lower(Str::random(10)),
            'secret' => $secret,
            'allowed_domains' => $domains,
            'endpoint_token_hash' => hash('sha256', $endpointToken),
            'is_active' => true,
        ]);

        return back()
            ->with('status', 'Sorgente creata. Copia ora l’endpoint: non verrà mostrato di nuovo.')
            ->with('webhook_credentials', [
                'id' => $source->id,
                'endpoint' => url('/api/v1/inbound/leads/'.$endpointToken),
                'key' => $source->key,
                'secret' => $secret,
            ]);
    }

    public function update(Request $request, InboundSource $source): RedirectResponse
    {
        $data = $request->validate(['allowed_domains_text' => ['required', 'string', 'max:5000']]);
        $domains = $this->domains($data['allowed_domains_text']);
        $this->ensureDomains($domains);
        $source->update(['allowed_domains' => $domains]);

        return back()->with('status', 'Domini consentiti aggiornati.');
    }

    public function rotateEndpoint(InboundSource $source): RedirectResponse
    {
        $endpointToken = Str::random(64);
        $source->update(['endpoint_token_hash' => hash('sha256', $endpointToken)]);

        return back()
            ->with('status', 'Endpoint rigenerato. Quello precedente non è più valido.')
            ->with('webhook_credentials', [
                'id' => $source->id,
                'endpoint' => url('/api/v1/inbound/leads/'.$endpointToken),
            ]);
    }

    public function rotate(InboundSource $source): RedirectResponse
    {
        $secret = Str::random(64);
        $source->update(['secret' => $secret]);

        return back()
            ->with('status', 'Segreto ruotato. Quello precedente non è più valido.')
            ->with('webhook_credentials', ['id' => $source->id, 'key' => $source->key, 'secret' => $secret]);
    }

    /** @return list<string> */
    private function domains(string $input): array
    {
        return collect(preg_split('/[\s,;]+/', $input) ?: [])
            ->map(InboundDomainGuard::normalizeDomain(...))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function ensureDomains(array $domains): void
    {
        if ($domains === []) {
            throw ValidationException::withMessages(['allowed_domains_text' => 'Inserisci almeno un dominio valido.']);
        }
    }
}

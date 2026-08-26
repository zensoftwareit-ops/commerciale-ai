@extends('layouts.admin')
@section('title', 'Clienti · Daria Super Admin')
@section('content')
<div class="toolbar">
    <div><h1>Clienti e organizzazioni</h1><p class="muted">Controllo centralizzato dei tenant attivi sulla piattaforma Daria.</p></div>
    <span class="badge">{{ $organizations->total() }} organizzazioni</span>
</div>

<section class="card">
    <div class="table"><table>
        <thead><tr><th>Organizzazione</th><th>Owner</th><th>Stato</th><th>Configurazione</th><th>Utilizzo</th><th>Licenza</th><th>Azioni</th></tr></thead>
        <tbody>
        @forelse($organizations as $organization)
            @php($owner=$organization->users->firstWhere('pivot.role', 'owner'))
            @php($license=$organization->licenses->sortByDesc('created_at')->first())
            <tr>
                <td><strong>{{ $organization->name }}</strong><br><span class="muted">{{ $organization->slug }} · {{ $organization->locale }} · {{ $organization->timezone }}</span></td>
                <td>{{ $owner?->name ?: '—' }}<br><span class="muted">{{ $owner?->email ?: 'Owner mancante' }}</span><br><span class="badge">{{ $organization->users->count() }} utenti</span></td>
                <td><span class="badge @if($organization->status==='active') success @elseif($organization->status==='suspended') hot @endif">{{ $organization->status }}</span>@if($organization->suspension_reason)<br><span class="muted">{{ str_replace('_', ' ', $organization->suspension_reason) }}</span>@endif</td>
                <td>
                    <span class="badge">Profilo {{ $organization->settings?->completeness ?? 0 }}%</span><br>
                    <span class="muted">IMAP attive: {{ $organization->active_mailboxes_count }}</span>
                </td>
                <td><strong>{{ $organization->leads_count }}</strong> lead totali<br><span class="muted">AI mese: {{ number_format($organization->current_month_ai_tokens, 0, ',', '.') }} token · € {{ number_format($organization->current_month_ai_cost, 4, ',', '.') }}</span>@if($license?->plan?->monthly_ai_token_limit)<br><span class="badge @if($organization->current_month_ai_tokens >= $license->plan->monthly_ai_token_limit) hot @elseif($organization->current_month_ai_tokens >= $license->plan->monthly_ai_token_limit * .8) warm @endif">{{ min(100, (int) floor(($organization->current_month_ai_tokens / $license->plan->monthly_ai_token_limit) * 100)) }}%</span>@endif</td>
                <td>
                    @if($license)
                        <span class="badge">{{ $license->status }}</span><br>
                        {{ $license->plan?->name ?: 'Piano non disponibile' }}<br>
                        <span class="muted">Scadenza: {{ $license->ends_at?->format('d/m/Y') ?: ($license->current_period_ends_at?->format('d/m/Y') ?: '—') }}</span>
                    @else
                        <span class="error">Nessuna licenza</span>
                    @endif
                </td>
                <td>
                    @if($organization->licenses->every(fn($item) => $item->source === 'manual'))
                        <details>
                            <summary class="muted">Elimina cliente</summary>
                            <form method="post" action="{{ route('admin.organizations.destroy', $organization) }}" onsubmit="return confirm('Eliminare definitivamente il cliente e tutti i suoi dati?')">
                                @csrf @method('delete')
                                <label>Scrivi “{{ $organization->name }}”</label>
                                <input name="confirmation" required autocomplete="off">
                                <br><button class="btn-danger">Elimina definitivamente</button>
                            </form>
                        </details>
                    @else
                        <span class="muted">Gestito da Stripe</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7">Nessuna organizzazione configurata.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $organizations->links() }}
</section>
@endsection

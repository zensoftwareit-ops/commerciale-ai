@extends('layouts.admin')
@section('title', 'Clienti · Daria Super Admin')
@section('content')
<div class="toolbar">
    <div><h1>Clienti e organizzazioni</h1><p class="muted">Controllo centralizzato dei tenant attivi sulla piattaforma Daria.</p></div>
    <span class="badge">{{ $organizations->total() }} organizzazioni</span>
</div>

<section class="card">
    <div class="table"><table>
        <thead><tr><th>Organizzazione</th><th>Owner</th><th>Configurazione</th><th>Utilizzo</th><th>Licenza</th></tr></thead>
        <tbody>
        @forelse($organizations as $organization)
            @php($owner=$organization->users->firstWhere('pivot.role', 'owner'))
            @php($license=$organization->licenses->sortByDesc('created_at')->first())
            <tr>
                <td><strong>{{ $organization->name }}</strong><br><span class="muted">{{ $organization->slug }} · {{ $organization->locale }} · {{ $organization->timezone }}</span></td>
                <td>{{ $owner?->name ?: '—' }}<br><span class="muted">{{ $owner?->email ?: 'Owner mancante' }}</span><br><span class="badge">{{ $organization->users->count() }} utenti</span></td>
                <td>
                    <span class="badge">Profilo {{ $organization->settings?->completeness ?? 0 }}%</span><br>
                    <span class="muted">IMAP attive: {{ $organization->active_mailboxes_count }}</span>
                </td>
                <td><strong>{{ $organization->leads_count }}</strong> lead totali</td>
                <td>
                    @if($license)
                        <span class="badge">{{ $license->status }}</span><br>
                        {{ $license->plan?->name ?: 'Piano non disponibile' }}<br>
                        <span class="muted">Scadenza: {{ $license->ends_at?->format('d/m/Y') ?: ($license->current_period_ends_at?->format('d/m/Y') ?: '—') }}</span>
                    @else
                        <span class="error">Nessuna licenza</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">Nessuna organizzazione configurata.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $organizations->links() }}
</section>
@endsection

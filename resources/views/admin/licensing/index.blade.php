@extends('layouts.admin')
@section('title', 'Licenze · Daria Super Admin')
@section('content')
<div class="toolbar">
    <div>
        <h1>Clienti, pacchetti e licenze</h1>
        <p class="muted">Step 1: attivazione manuale dei clienti. WordPress e Stripe restano disabilitati.</p>
    </div>
    <span class="badge">{{ $licenses->total() }} licenze</span>
</div>

<section class="card">
    <div class="toolbar">
        <div>
            <h2>Registra un nuovo cliente</h2>
            <p class="muted">Crea in un solo passaggio account owner, azienda, ambiente iniziale e licenza annuale.</p>
        </div>
        <span class="badge">Step 1</span>
    </div>

    @if($plans->isEmpty())
        <div class="error">Prima crea almeno un pacchetto nella sezione sottostante.</div>
    @else
        <form method="post" action="{{ route('admin.licenses.store') }}">
            @csrf
            <div class="grid">
                <div>
                    <label>Ragione sociale / nome azienda</label>
                    <input name="company_name" required maxlength="160" value="{{ old('company_name') }}" placeholder="Azienda Demo Srl">
                </div>
                <div>
                    <label>Nome e cognome owner</label>
                    <input name="owner_name" required maxlength="160" value="{{ old('owner_name') }}" placeholder="Mario Rossi">
                </div>
                <div>
                    <label>Email owner</label>
                    <input type="email" name="owner_email" required value="{{ old('owner_email') }}" placeholder="mario@azienda.it">
                </div>
                <div>
                    <label>Pacchetto</label>
                    <select name="license_plan_id" required>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" @selected(old('license_plan_id') === $plan->id)>{{ $plan->name }} · {{ $plan->seat_limit }} utenti</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Stato iniziale</label>
                    <select name="status">
                        <option value="active" @selected(old('status', 'active') === 'active')>Attiva</option>
                        <option value="trialing" @selected(old('status') === 'trialing')>Prova</option>
                        <option value="suspended" @selected(old('status') === 'suspended')>Sospesa</option>
                    </select>
                </div>
                <div>
                    <label>Fine periodo annuale</label>
                    <input type="datetime-local" name="current_period_ends_at" value="{{ old('current_period_ends_at') }}">
                    <span class="muted">Se lasci vuoto, viene impostata tra un anno.</span>
                </div>
            </div>
            <button>Registra cliente e attiva licenza</button>
        </form>
    @endif
</section>

<h2>I tre pacchetti</h2>
<div class="grid">
    @foreach($plans as $plan)
        <form class="card" method="post" action="{{ route('admin.plans.update', $plan) }}">
            @csrf @method('put')
            <h3>{{ $plan->name }} <span class="badge">{{ $plan->licenses_count }} licenze</span></h3>
            @include('admin.licensing.plan-fields', ['plan' => $plan])
            <button>Salva pacchetto</button>
        </form>
    @endforeach
    @if($plans->count() < 3)
        <form class="card" method="post" action="{{ route('admin.plans.store') }}">
            @csrf
            <h3>Nuovo pacchetto</h3>
            @include('admin.licensing.plan-fields', ['plan' => null])
            <button>Crea pacchetto</button>
        </form>
    @endif
</div>

<details class="card">
    <summary><strong>Assegna una licenza a un cliente già presente</strong></summary>
    <p class="muted">Usa questa funzione per rinnovi manuali o organizzazioni create in precedenza.</p>
    <form method="post" action="{{ route('admin.licenses.existing.store') }}">
        @csrf
        <div class="grid">
            <div><label>Pacchetto</label><select name="license_plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select></div>
            <div><label>Organizzazione</label><select name="organization_id" required>@foreach($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach</select></div>
            <div><label>Email owner</label><input type="email" name="owner_email" required></div>
            <div><label>Stato</label><select name="status"><option value="active">Attiva</option><option value="trialing">Prova</option><option value="suspended">Sospesa</option></select></div>
            <div><label>Fine periodo</label><input type="datetime-local" name="current_period_ends_at"><span class="muted">Vuoto = un anno.</span></div>
            <div><label>Scadenza definitiva</label><input type="datetime-local" name="ends_at"></div>
        </div>
        <button>Genera licenza</button>
    </form>
</details>

<section class="card">
    <h2>Licenze emesse</h2>
    <div class="table"><table><thead><tr><th>Licenza</th><th>Cliente</th><th>Pacchetto</th><th>Origine</th><th>Stato e scadenze</th><th>Aggiorna</th><th>Azioni</th></tr></thead><tbody>
    @forelse($licenses as $license)
        <tr>
            <td><code>{{ $license->key }}</code></td>
            <td><strong>{{ $license->organization->name }}</strong><br>{{ $license->owner->name }}<br><span class="muted">{{ $license->owner->email }}</span></td>
            <td>{{ $license->plan->name }}<br><span class="muted">{{ $license->plan->seat_limit }} utenti</span></td>
            <td><span class="badge">{{ $license->source === 'manual' ? 'Manuale' : 'Self-service' }}</span></td>
            <td><span class="badge">{{ $license->status }}</span><br><span class="muted">Rinnovo: {{ $license->current_period_ends_at?->format('d/m/Y H:i') ?: '—' }}<br>Fine: {{ $license->ends_at?->format('d/m/Y H:i') ?: '—' }}</span></td>
            <td>
                <form method="post" action="{{ route('admin.licenses.update', $license) }}">
                    @csrf @method('put')
                    <select name="license_plan_id">@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected($license->license_plan_id === $plan->id)>{{ $plan->name }}</option>@endforeach</select>
                    <select name="status">@foreach(['active','trialing','past_due','unpaid','canceled','paused','suspended'] as $status)<option value="{{ $status }}" @selected($license->status === $status)>{{ $status }}</option>@endforeach</select>
                    <input type="datetime-local" name="current_period_ends_at" value="{{ $license->current_period_ends_at?->format('Y-m-d\TH:i') }}">
                    <input type="datetime-local" name="ends_at" value="{{ $license->ends_at?->format('Y-m-d\TH:i') }}">
                    <label><input style="width:auto" type="checkbox" name="cancel_at_period_end" value="1" @checked($license->cancel_at_period_end)> Annulla a fine periodo</label>
                    <button>Aggiorna</button>
                </form>
            </td>
            <td>
                <div class="actions">
                    <form method="post" action="{{ route('admin.licenses.resend-activation', $license) }}">@csrf<button class="btn-secondary">Rimanda attivazione</button></form>
                    @if($license->source === 'manual')
                        <form method="post" action="{{ route('admin.licenses.renew', $license) }}" onsubmit="return confirm('Rinnovare questa licenza per altri 12 mesi?')">@csrf<button>Rinnova 12 mesi</button></form>
                        @if(in_array($license->status, ['active','trialing']))
                            <form method="post" action="{{ route('admin.licenses.suspend', $license) }}" onsubmit="return confirm('Disabilitare subito l’accesso del cliente?')">@csrf<button class="btn-secondary">Disabilita</button></form>
                        @else
                            <form method="post" action="{{ route('admin.licenses.activate', $license) }}">@csrf<button>Riattiva</button></form>
                        @endif
                        <form method="post" action="{{ route('admin.licenses.destroy', $license) }}" onsubmit="return confirm('Eliminare definitivamente la licenza {{ $license->key }}? Il cliente verrà sospeso.')">@csrf @method('delete')<button class="btn-danger">Elimina licenza</button></form>
                    @endif
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="7">Nessuna licenza.</td></tr>
    @endforelse
    </tbody></table></div>
    {{ $licenses->links() }}
</section>
@endsection

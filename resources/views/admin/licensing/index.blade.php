@extends('layouts.admin')
@section('title','Licenze · Super Admin')
@section('content')
<div class="toolbar"><div><h1>Pacchetti e licenze</h1><p class="muted">Commerciale AI è l’autorità sugli accessi. Stripe determina lo stato economico dell’abbonamento.</p></div><span class="badge">{{ $licenses->total() }} licenze</span></div>

<h2>I tre pacchetti</h2><div class="grid">
@foreach($plans as $plan)
<form class="card" method="post" action="{{ route('admin.plans.update',$plan) }}">@csrf @method('put')
<h3>{{ $plan->name }} <span class="badge">{{ $plan->licenses_count }} licenze</span></h3>
@include('admin.licensing.plan-fields',['plan'=>$plan])<button>Salva pacchetto</button></form>
@endforeach
@if($plans->count()<3)
<form class="card" method="post" action="{{ route('admin.plans.store') }}">@csrf<h3>Nuovo pacchetto</h3>
@include('admin.licensing.plan-fields',['plan'=>null])<button>Crea pacchetto</button></form>
@endif
</div>

<section class="card"><h2>Genera licenza manuale</h2><p class="muted">Utile per pilota, omaggi e clienti acquisiti fuori da Stripe. L’email deve appartenere all’owner dell’organizzazione.</p>
<form method="post" action="{{ route('admin.licenses.store') }}">@csrf<div class="grid"><div><label>Pacchetto</label><select name="license_plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select></div><div><label>Organizzazione</label><select name="organization_id" required>@foreach($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach</select></div><div><label>Email owner</label><input type="email" name="owner_email" required></div><div><label>Stato</label><select name="status"><option>active</option><option>trialing</option><option>suspended</option></select></div><div><label>Fine periodo</label><input type="datetime-local" name="current_period_ends_at"></div><div><label>Scadenza definitiva</label><input type="datetime-local" name="ends_at"></div></div><button>Genera licenza</button></form></section>

<section class="card"><h2>Licenze emesse</h2><div class="table"><table><thead><tr><th>Licenza</th><th>Cliente</th><th>Pacchetto</th><th>Billing</th><th>Stato e scadenze</th><th>Aggiorna</th></tr></thead><tbody>
@forelse($licenses as $license)<tr><td><code>{{ $license->key }}</code><br><span class="muted">{{ $license->source }}</span></td><td><strong>{{ $license->organization->name }}</strong><br>{{ $license->owner->email }}</td><td>{{ $license->plan->name }}<br><span class="muted">{{ $license->plan->seat_limit }} utenti</span></td><td><span class="muted">Customer</span> {{ $license->stripe_customer_id ?: '—' }}<br><span class="muted">Subscription</span> {{ $license->stripe_subscription_id ?: '—' }}</td><td><span class="badge">{{ $license->status }}</span><br><span class="muted">Rinnovo: {{ $license->current_period_ends_at?->format('d/m/Y H:i') ?: '—' }}<br>Fine: {{ $license->ends_at?->format('d/m/Y H:i') ?: '—' }}</span></td><td><form method="post" action="{{ route('admin.licenses.update',$license) }}">@csrf @method('put')<select name="license_plan_id">@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected($license->license_plan_id===$plan->id)>{{ $plan->name }}</option>@endforeach</select><select name="status">@foreach(['active','trialing','past_due','unpaid','canceled','paused','suspended'] as $status)<option @selected($license->status===$status)>{{ $status }}</option>@endforeach</select><input type="datetime-local" name="current_period_ends_at" value="{{ $license->current_period_ends_at?->format('Y-m-d\TH:i') }}"><input type="datetime-local" name="ends_at" value="{{ $license->ends_at?->format('Y-m-d\TH:i') }}"><label><input style="width:auto" type="checkbox" name="cancel_at_period_end" value="1" @checked($license->cancel_at_period_end)> Annulla a fine periodo</label><button>Aggiorna</button></form></td></tr>@empty<tr><td colspan="6">Nessuna licenza.</td></tr>@endforelse
</tbody></table></div>{{ $licenses->links() }}</section>
@endsection


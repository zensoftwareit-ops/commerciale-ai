@extends('layouts.app')
@section('title', 'Email da associare · Commerciale AI')
@section('content')
<div class="toolbar">
    <div>
        <h1>Email da associare</h1>
        <p class="muted">Messaggi che non hanno prove sufficienti per essere collegati automaticamente. Verifica mittente e contenuto prima di scegliere il lead.</p>
    </div>
    <span class="badge">{{ $emails->total() }} IN ATTESA</span>
</div>

@forelse($emails as $email)
    <section class="card" style="margin-bottom:1rem">
        <div class="toolbar">
            <div>
                <h2 style="margin-bottom:.25rem">{{ $email->subject }}</h2>
                <div class="muted">Da {{ $email->from_name ? $email->from_name.' &lt;'.$email->from_address.'&gt;' : $email->from_address }} · {{ $email->received_at->format('d/m/Y H:i') }}</div>
            </div>
            <span class="badge warm">DA VERIFICARE</span>
        </div>
        <div class="email-preview">{!! nl2br(e($email->body)) !!}</div>
        <form method="post" action="{{ route('inbound-emails.link', $email) }}">
            @csrf
            <label>Associa al lead</label>
            <select name="lead_id" required>
                <option value="">Seleziona…</option>
                @foreach($leads as $lead)
                    <option value="{{ $lead->id }}" @selected(old('lead_id') === $lead->id)>{{ $lead->name }} · {{ $lead->email }}{{ $lead->company ? ' · '.$lead->company : '' }}</option>
                @endforeach
            </select>
            <label style="font-weight:400"><input style="width:auto" type="checkbox" name="add_secondary_contact" value="1"> Memorizza {{ $email->from_address }} come indirizzo secondario del lead</label>
            <p class="muted">La bozza continuerà a essere indirizzata all’email principale del lead. Potrai modificarla prima dell’invio.</p>
            <button class="btn" type="submit" onclick="return confirm('Confermi l’associazione di questa email al lead selezionato?')">Associa email</button>
        </form>
        <form method="post" action="{{ route('inbound-emails.destroy', $email) }}" style="margin-top:1rem" onsubmit="return confirm('Eliminare definitivamente questa email dalla coda? L’operazione non può essere annullata.')">
            @csrf @method('delete')
            <button class="btn" style="background:#b42318" type="submit">Elimina email</button>
        </form>
    </section>
@empty
    <section class="card"><p>Nessuna email in attesa di associazione.</p></section>
@endforelse

{{ $emails->links() }}
@endsection

@extends('layouts.app')
@section('title', 'Notifiche · Commerciale AI')
@section('content')
<div class="toolbar">
    <div><div class="page-kicker">Centro operativo</div><h1>Notifiche</h1><p class="muted">Segnalazioni che richiedono l’intervento del team commerciale.</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-muted" type="button" id="enable-browser-notifications">Attiva notifiche browser</button>
        <form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-muted" type="submit">Archivia tutte</button></form>
    </div>
</div>
<div id="browser-notification-status" class="notice" style="display:none"></div>
<section class="card table-card">
    <div class="table-wrap"><table><thead><tr><th>Stato</th><th>Segnalazione</th><th>Lead</th><th>Ricevuta</th><th></th></tr></thead><tbody>
    @forelse($notifications as $notification)
        <tr style="{{ $notification->read_at ? '' : 'background:#fafbff' }}">
            <td><span class="badge {{ $notification->read_at ? '' : 'warm' }}">{{ $notification->read_at ? 'Archiviata' : 'Da gestire' }}</span></td>
            <td><strong>{{ $notification->title }}</strong><div class="muted">{{ $notification->message }}</div></td>
            <td>@if($notification->lead)<a href="{{ route('notifications.open',$notification) }}"><strong>{{ $notification->lead->name }}</strong></a><div class="muted">{{ $notification->lead->email }}</div>@else — @endif</td>
            <td>{{ $notification->created_at->format('d/m/Y H:i') }}</td>
            <td>@unless($notification->read_at)<form method="post" action="{{ route('notifications.read',$notification) }}">@csrf @method('patch')<button class="btn btn-muted" type="submit">Archivia</button></form>@endunless</td>
        </tr>
    @empty
        <tr><td colspan="5" style="padding:40px;text-align:center"><strong>Nessuna notifica</strong><div class="muted">Le richieste di intervento umano compariranno qui.</div></td></tr>
    @endforelse
    </tbody></table></div>
    <div class="pagination">{{ $notifications->links() }}</div>
</section>
@endsection


@extends('layouts.admin')
@section('title', 'Audit amministrativo · Daria')
@section('content')
<div class="toolbar"><div><h1>Audit amministrativo</h1><p class="muted">Registro non modificabile delle operazioni eseguite nel pannello di piattaforma.</p></div><span class="badge">{{ $logs->total() }} eventi</span></div>
<section class="card"><div class="table"><table>
<thead><tr><th>Data</th><th>Amministratore</th><th>Operazione</th><th>Oggetto</th><th>Campi interessati</th></tr></thead>
<tbody>@forelse($logs as $log)<tr>
<td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
<td>{{ $log->actor?->name ?: 'Account eliminato' }}<br><span class="muted">{{ $log->actor?->email }}</span></td>
<td><strong>{{ $log->action }}</strong><br><span class="badge">{{ $log->method }}</span></td>
<td>{{ $log->subject_type ?: '—' }}<br><code>{{ $log->subject_id ?: '—' }}</code></td>
<td><span class="muted">{{ implode(', ', $log->metadata['changed_fields'] ?? []) ?: 'Nessun campo dati' }}</span></td>
</tr>@empty<tr><td colspan="5">Nessuna operazione registrata.</td></tr>@endforelse</tbody>
</table></div>{{ $logs->links() }}</section>
@endsection

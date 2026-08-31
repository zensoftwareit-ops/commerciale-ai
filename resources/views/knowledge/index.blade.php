@extends('layouts.app')
@section('title', 'Knowledge base · Daria')
@section('content')
@php($isOwner=auth()->user()->roleFor(app(\App\Support\Tenancy\TenantContext::class)->organization()) === 'owner')
<div class="toolbar"><div><h1>Knowledge base</h1><p class="muted">Solo i contenuti attivi vengono usati per preparare le analisi.</p></div>@if($isOwner)<div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn btn-muted" href="{{ route('setup-wizard.create') }}">Genera con Daria</a><a class="btn" href="{{ route('knowledge.create') }}">+ Aggiungi contenuto</a></div>@endif</div>
<div class="card"><table><thead><tr><th>Titolo</th><th>Tipo</th><th>Stato</th><th>Aggiornato</th><th></th></tr></thead><tbody>
@forelse($documents as $document)<tr><td><strong>{{ $document->title }}</strong><div class="muted">{{ \Illuminate\Support\Str::limit($document->content,100) }}</div></td><td>{{ $document->type }}</td><td><span class="badge">{{ $document->status }}</span></td><td>{{ $document->updated_at->format('d/m/Y H:i') }}</td><td>@if($isOwner)<a href="{{ route('knowledge.edit',$document) }}">Modifica</a>@endif</td></tr>@empty<tr><td colspan="5">La knowledge base è vuota.</td></tr>@endforelse
</tbody></table>{{ $documents->links() }}</div>
@endsection

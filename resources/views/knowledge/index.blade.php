@extends('layouts.app')
@section('title', 'Knowledge base · Commerciale AI')
@section('content')
<div class="toolbar"><div><h1>Knowledge base</h1><p class="muted">Solo i contenuti attivi vengono usati per preparare le analisi.</p></div>@if($activeRole==='owner')<a class="btn" href="{{ route('knowledge.create') }}">+ Aggiungi contenuto</a>@endif</div>
<div class="card"><table><thead><tr><th>Titolo</th><th>Tipo</th><th>Stato</th><th>Aggiornato</th><th></th></tr></thead><tbody>
@forelse($documents as $document)<tr><td><strong>{{ $document->title }}</strong><div class="muted">{{ \Illuminate\Support\Str::limit($document->content,100) }}</div></td><td>{{ $document->type }}</td><td><span class="badge">{{ $document->status }}</span></td><td>{{ $document->updated_at->format('d/m/Y H:i') }}</td><td>@if($activeRole==='owner')<a href="{{ route('knowledge.edit',$document) }}">Modifica</a>@endif</td></tr>@empty<tr><td colspan="5">La knowledge base è vuota.</td></tr>@endforelse
</tbody></table>{{ $documents->links() }}</div>
@endsection

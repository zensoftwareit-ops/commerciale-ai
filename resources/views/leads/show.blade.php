@extends('layouts.app')
@section('title', $lead->name.' · Commerciale AI')
@section('content')
<div class="toolbar"><div><a href="{{ route('leads.index') }}">← Lead inbox</a><h1>{{ $lead->name }}</h1><div class="muted">{{ $lead->company }} · {{ $lead->email }} · {{ $lead->phone }}</div></div><span class="badge {{ $lead->temperature }}">{{ strtoupper($lead->temperature) }} · {{ $lead->score }}/100</span></div>
<div class="grid grid-2"><section class="card"><h2>Richiesta</h2><p><strong>Origine:</strong> {{ $lead->source_label }}</p><p><strong>Servizio:</strong> {{ $lead->requested_service ?: 'Non indicato' }}</p><p>{{ data_get($lead->request_data,'message','Nessun messaggio') }}</p><form method="post" action="{{ route('leads.update',$lead) }}">@csrf @method('patch')
<label>Pipeline</label><select name="pipeline_stage_id">@foreach(\App\Models\PipelineStage::query()->orderBy('position')->get() as $stage)<option value="{{ $stage->id }}" @selected($lead->pipeline_stage_id===$stage->id)>{{ $stage->name }}</option>@endforeach</select>
<label>Stato operativo</label><select name="operational_status">@foreach(['needs_action','awaiting_approval','awaiting_customer','follow_up_scheduled','paused','closed'] as $status)<option @selected($lead->operational_status===$status)>{{ $status }}</option>@endforeach</select><br><br><button class="btn">Aggiorna</button></form></section>
<section class="card"><h2>Timeline</h2><div class="timeline">@foreach($lead->activities as $activity)<div class="event"><strong>{{ $activity->title }}</strong><div class="muted">{{ $activity->occurred_at->format('d/m/Y H:i') }} · {{ $activity->actor?->name ?? 'Sistema' }}</div></div>@endforeach</div></section></div>
@endsection

@extends('layouts.app')
@section('title', $lead->name.' · Commerciale AI')
@section('content')
<div class="toolbar">
    <div>
        <a href="{{ route('leads.index') }}">← Lead inbox</a>
        <h1>{{ $lead->name }}</h1>
        <div class="muted">{{ $lead->company }} · {{ $lead->email }} · {{ $lead->phone }}</div>
    </div>
    <span class="badge {{ $lead->temperature }}">{{ strtoupper($lead->temperature) }} · {{ $lead->score }}/100</span>
</div>

<div class="grid grid-2">
    <section class="card">
        <h2>Richiesta</h2>
        <p><strong>Origine:</strong> {{ $lead->source_label }}</p>
        <p><strong>Servizio:</strong> {{ $lead->requested_service ?: 'Non indicato' }}</p>
        @if(filled(data_get($lead->request_data, 'message')))<p>{{ data_get($lead->request_data, 'message') }}</p>@endif
        @if(filled($lead->request_data))
            <table><tbody>
            @foreach($lead->request_data as $key => $value)
                @continue($key === 'message')
                <tr><th>{{ str($key)->replace('_', ' ')->title() }}</th><td>
                    @if(is_array($value))
                        {{ collect($value)->map(fn($item) => is_scalar($item) ? $item : json_encode($item, JSON_UNESCAPED_UNICODE))->implode(', ') ?: '—' }}
                    @elseif(is_bool($value))
                        {{ $value ? 'Sì' : 'No' }}
                    @else
                        {{ filled($value) ? $value : '—' }}
                    @endif
                </td></tr>
            @endforeach
            </tbody></table>
        @endif
        <form method="post" action="{{ route('leads.update', $lead) }}">
            @csrf @method('patch')
            <label>Pipeline</label>
            <select name="pipeline_stage_id">
                @foreach(\App\Models\PipelineStage::query()->orderBy('position')->get() as $stage)
                    <option value="{{ $stage->id }}" @selected($lead->pipeline_stage_id === $stage->id)>{{ $stage->name }}</option>
                @endforeach
            </select>
            <label>Stato operativo</label>
            <select name="operational_status">
                @foreach(['needs_action', 'awaiting_approval', 'awaiting_customer', 'follow_up_scheduled', 'paused', 'closed'] as $status)
                    <option @selected($lead->operational_status === $status)>{{ $status }}</option>
                @endforeach
            </select><br><br>
            <button class="btn">Aggiorna</button>
        </form>
    </section>

    <section class="card">
        <h2>Timeline</h2>
        <div class="timeline">
            @foreach($lead->activities as $activity)
                <div class="event">
                    <strong>{{ $activity->title }}</strong>
                    <div class="muted">{{ $activity->occurred_at->format('d/m/Y H:i') }} · {{ $activity->actor?->name ?? 'Sistema' }}</div>
                </div>
            @endforeach
        </div>
    </section>
</div>

<section class="card" style="margin-top:1rem">
    <div class="toolbar">
        <div><h2>Analisi commerciale</h2><p class="muted">Punteggio AI e regole vengono conservati separatamente.</p></div>
        <form method="post" action="{{ route('leads.analyze', $lead) }}">@csrf
            <button class="btn">{{ $lead->analyses->isEmpty() ? 'Analizza lead' : 'Ripeti analisi' }}</button>
        </form>
    </div>
    @error('analysis')<div class="error">{{ $message }}</div>@enderror
    @if($analysis = $lead->analyses->first())
        <div class="grid grid-2">
            <div>
                <div class="grid grid-2">
                    <div><span class="muted">AI score</span><div class="stat">{{ $analysis->ai_score }}</div></div>
                    <div><span class="muted">Rule score</span><div class="stat">{{ $analysis->rule_score }}</div></div>
                    <div><span class="muted">Score finale</span><div class="stat">{{ $analysis->final_score }}</div></div>
                    <div><span class="muted">Priorità</span><div class="stat">{{ strtoupper($analysis->priority) }}</div></div>
                </div>
                <h3>Sintesi</h3><p>{{ $analysis->summary }}</p>
                <h3>Prossima azione</h3><p>{{ $analysis->recommended_next_action }}</p>
                <p class="muted">Provider {{ $analysis->run->provider }} · modello {{ $analysis->run->model }} · policy {{ $analysis->run->policy_version }} · versione {{ $analysis->version }}</p>
            </div>
            <form method="post" action="{{ route('analyses.update', [$lead, $analysis]) }}">
                @csrf @method('patch')
                <h3>Correzione operatore</h3>
                <label>Sintesi</label><textarea name="summary" rows="4" required>{{ $analysis->summary }}</textarea>
                <label>Intento</label><input name="intent" value="{{ $analysis->intent }}" required>
                <div class="grid grid-2">
                    <div><label>Urgenza</label><select name="urgency">@foreach(['low', 'medium', 'high', 'unknown'] as $value)<option @selected($analysis->urgency === $value)>{{ $value }}</option>@endforeach</select></div>
                    <div><label>Priorità</label><select name="priority">@foreach(['low', 'medium', 'high'] as $value)<option @selected($analysis->priority === $value)>{{ $value }}</option>@endforeach</select></div>
                </div>
                <label>Score finale</label><input type="number" min="0" max="100" name="final_score" value="{{ $analysis->final_score }}">
                <label>Prossima azione</label><textarea name="recommended_next_action" rows="3" required>{{ $analysis->recommended_next_action }}</textarea>
                <label>Informazioni mancanti, una per riga</label><textarea name="missing_information_text" rows="3">{{ implode("\n", $analysis->missing_information) }}</textarea>
                <label>Rischi, uno per riga</label><textarea name="risk_flags_text" rows="3">{{ implode("\n", $analysis->risk_flags) }}</textarea>
                <label>Domande, una per riga</label><textarea name="qualification_questions_text" rows="3">{{ implode("\n", $analysis->qualification_questions) }}</textarea><br>
                <button class="btn">Salva correzione</button>
            </form>
        </div>
    @else
        <p>Nessuna analisi disponibile. Avvia l’analisi per ottenere qualificazione e bozza email.</p>
    @endif
</section>

@if($lead->inboundEmails->isNotEmpty())
<section class="card" style="margin-top:1rem">
    <div class="toolbar"><div><h2>Risposte ricevute</h2><p class="muted">Messaggi importati dalla casella IMAP e collegati a questo lead.</p></div><span class="badge">{{ $lead->inboundEmails->count() }}</span></div>
    @foreach($lead->inboundEmails as $email)
        <article class="email-preview">
            <div class="toolbar" style="margin-bottom:.5rem">
                <div><strong>{{ $email->subject }}</strong><div class="muted">Da {{ $email->from_name ?: $email->from_address }} · {{ $email->received_at->format('d/m/Y H:i') }}</div></div>
                <span class="badge">RICEVUTA</span>
            </div>
            @if($email->sender_differs)
                <div class="warning">
                    Il messaggio è stato associato alla conversazione, ma arriva da <strong>{{ $email->from_address }}</strong>
                    invece che dall’indirizzo principale <strong>{{ $lead->email }}</strong>. Verifica l’identità prima di rispondere o usare il nuovo indirizzo.
                </div>
            @endif
            {!! nl2br(e($email->body)) !!}
        </article>
    @endforeach
</section>
@endif

@if($reply = $lead->replies->first())
<section class="card" style="margin-top:1rem">
    <div class="toolbar">
        <div>
            <h2>Risposta al lead</h2>
            <p class="muted">Bozza generata dall’AI: controllala sempre prima dell’invio.</p>
        </div>
        <span class="badge">{{ $reply->status === 'sent' ? 'INVIATA' : 'DA APPROVARE' }}</span>
    </div>
    @error('reply')<div class="error">{{ $message }}</div>@enderror
    @if($quotation = $lead->quotations->first())
        <div class="notice"><strong>Preventivo v{{ $quotation->version }}:</strong> € {{ number_format($quotation->minimum_price,0,',','.') }}–{{ number_format($quotation->maximum_price,0,',','.') }} + IVA · affidabilità {{ $quotation->confidence }}%. @if($quotation->auto_send_eligible) Idoneo all’automazione interna. @else Invio automatico bloccato: {{ implode(', ',$quotation->automation_blockers ?? []) }}. @endif</div>
    @endif
    @if($reply->status === 'sent')
        <p><strong>A:</strong> {{ $reply->recipient }}</p>
        <p><strong>Oggetto:</strong> {{ $reply->subject }}</p>
        <div class="email-preview">{!! nl2br(e($reply->body)) !!}</div>
        <p class="notice">Inviata il {{ $reply->sent_at->format('d/m/Y H:i') }} da {{ $reply->approver?->name ?? 'Operatore' }}.</p>
        @if($reply->follow_up_at)<p><strong>Follow-up:</strong> {{ $reply->follow_up_at->format('d/m/Y H:i') }}</p>@endif
    @else
        @if(config('mail.default') === 'log')
            <div class="warning">La posta è in modalità <strong>log</strong>: configura SMTP prima di inviare email reali.</div>
        @endif
        <form method="post" action="{{ route('replies.update', [$lead, $reply]) }}">
            @csrf @method('patch')
            <label>Destinatario</label><input type="email" name="recipient" value="{{ old('recipient', $reply->recipient) }}" required>
            <label>Oggetto</label><input name="subject" value="{{ old('subject', $reply->subject) }}" required>
            <label>Testo</label><textarea name="body" rows="12" required>{{ old('body', $reply->body) }}</textarea>
            <label>Follow-up opzionale</label>
            <input type="datetime-local" name="follow_up_at" value="{{ old('follow_up_at', $reply->follow_up_at?->format('Y-m-d\TH:i')) }}">
            <p class="muted">La data verrà mostrata come prossima azione del lead dopo l’invio.</p>
            <button class="btn btn-muted">Salva bozza</button>
        </form>
        <form method="post" action="{{ route('replies.send', [$lead, $reply]) }}" style="margin-top:1rem" onsubmit="return confirm('Confermi l’approvazione e l’invio di questa email?')">
            @csrf
            <button class="btn">Approva e invia la versione salvata</button>
        </form>
        @if($reply->last_error)<p class="error">Ultimo invio non riuscito. Controlla SMTP e riprova.</p>@endif
    @endif
</section>
@elseif($lead->analyses->isNotEmpty() && filled($lead->email))
<section class="card" style="margin-top:1rem">
    @error('reply')<div class="error">{{ $message }}</div>@enderror
    <p>Nessuna bozza disponibile. Ripeti l’analisi per generarne una.</p>
</section>
@endif
@endsection

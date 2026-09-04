@extends('layouts.app')
@section('title', $lead->name.' · Daria')
@section('content')
<div class="toolbar">
    <div>
        <a class="back-link" href="{{ route('leads.index') }}">← Torna alla Lead inbox</a>
        <div class="lead-heading">
            <span class="lead-avatar">{{ mb_strtoupper(mb_substr($lead->name,0,1)) }}</span>
            <div><div class="page-kicker">Scheda lead</div><h1>{{ $lead->name }}</h1></div>
        </div>
        <div class="contact-line" style="margin-top:10px">
            @if($lead->company)<span>{{ $lead->company }}</span><span class="muted">·</span>@endif
            @if($lead->email)<a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a>@endif
            @if($lead->phone)<span class="muted">·</span><a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a>@endif
        </div>
    </div>
    <span class="badge {{ $lead->temperature }}">{{ strtoupper($lead->temperature) }} · Score {{ $lead->score }}/100</span>
</div>

@php
    $handoffActivity = $lead->activities->firstWhere('type', 'conversation_handoff');
    $handoffReasonLabels = [
        'no_pricing_rule_after_conversation_turn' => 'Il cliente ha richiesto un prezzo, ma non esiste un listino applicabile.',
        'qualification_limit_reached' => 'Mancano dati essenziali dopo il tentativo di qualificazione.',
        'unsupported_whatsapp_message_type' => 'Il messaggio WhatsApp ricevuto non è testuale.',
    ];
    $handoffReasonCode = $handoffActivity ? data_get($handoffActivity->data, 'reason') : null;
    $handoffReason = $handoffReasonLabels[$handoffReasonCode] ?? 'Daria non può proseguire questa conversazione in modo affidabile.';
@endphp
@if($lead->operational_status === 'needs_action' && $handoffActivity)
    <div class="warning" style="margin-bottom:16px">
        <strong>Intervento umano richiesto.</strong> {{ $handoffReason }} Un commerciale deve valutare la risposta e decidere come proseguire.
        @if(data_get($handoffActivity->data, 'inbound_email_id'))
            <form method="post" action="{{ route('leads.retry-conversation', $lead) }}" style="margin-top:12px">
                @csrf
                <button class="btn btn-muted" type="submit">Riprova con Daria</button>
            </form>
        @endif
    </div>
@endif

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
        @if($lead->initial_automation_error)
            <div class="error"><strong>Ultimo tentativo automatico non riuscito.</strong><br>{{ $lead->initial_automation_error }}</div>
            <p class="muted">Tentativi: {{ $lead->initial_automation_attempts }} · Ultimo tentativo: {{ $lead->initial_automation_attempted_at?->format('d/m/Y H:i:s') ?: '—' }}@if($lead->initial_automation_next_attempt_at) · Prossimo retry: {{ $lead->initial_automation_next_attempt_at->format('d/m/Y H:i:s') }}@endif</p>
        @elseif($lead->initial_automation_attempted_at)
            <div class="notice">Il ciclo automatico ha preso in carico il lead il {{ $lead->initial_automation_attempted_at->format('d/m/Y H:i:s') }}.</div>
        @else
            <div class="warning">Il lead non è ancora stato preso in carico dall’automazione. Controlla che la cron <code>commerciale:run</code> sia attiva e che “Analizza automaticamente i nuovi lead” sia selezionato.</div>
        @endif
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

@if($lead->whatsappMessages->isNotEmpty())
<section class="card" style="margin-top:1rem">
    <div class="toolbar"><div><h2>Conversazione WhatsApp <span class="badge warm">BETA</span></h2><p class="muted">Messaggi ricevuti e inviati tramite la WhatsApp Cloud API.</p></div><span class="badge">{{ $lead->whatsappMessages->count() }}</span></div>
    @foreach($lead->whatsappMessages->sortBy('received_at') as $message)
        <article class="email-preview" style="margin-left:{{ $message->direction === 'outbound' ? '8%' : '0' }};background:{{ $message->direction === 'outbound' ? '#eaf9f5' : '#fbfcfe' }}">
            <div class="toolbar" style="margin-bottom:.4rem"><strong>{{ $message->direction === 'outbound' ? 'Daria' : $message->from_number }}</strong><span class="badge {{ $message->direction === 'outbound' ? 'success' : '' }}">{{ $message->direction === 'outbound' ? 'INVIATO' : 'RICEVUTO' }}</span></div>
            {!! nl2br(e($message->body)) !!}
            <div class="muted" style="margin-top:8px">{{ ($message->received_at ?? $message->sent_at ?? $message->created_at)->format('d/m/Y H:i') }} · {{ $message->status }}</div>
        </article>
    @endforeach
</section>
@endif

@if($reply = $lead->replies->first())
<section class="card" style="margin-top:1rem">
    <div class="toolbar">
        <div>
            <h2>Risposta al lead @if($reply->channel === 'whatsapp')<span class="badge warm">WHATSAPP BETA</span>@endif</h2>
            <p class="muted">Bozza generata dall’AI per {{ $reply->channel === 'whatsapp' ? 'WhatsApp' : 'email' }}: controllala prima dell’invio.</p>
        </div>
        <span class="badge">{{ $reply->status === 'sent' ? 'INVIATA' : 'DA APPROVARE' }}</span>
    </div>
    @error('reply')<div class="error">{{ $message }}</div>@enderror
    @if($reply->status !== 'sent' && $lead->initial_automation_error)
        <div class="error"><strong>Invio automatico non riuscito.</strong><br>{{ $lead->initial_automation_error }}</div>
    @elseif($reply->status !== 'sent' && ! $reply->automation_eligible)
        @php
            $automationBlockerLabels = [
                'auto_send_initial_email_disabled' => 'l’invio automatico della prima email non è attivo',
                'recipient_not_allowed' => 'il destinatario non è nella lista dei test interni',
                'recipient_not_in_internal_allowlist' => 'il destinatario non è nella lista dei test interni',
                'sender_domain_not_verified' => 'il dominio mittente non è verificato',
                'auto_send_quotes_disabled' => 'l’invio automatico dei preventivi non è attivo',
                'amount_over_limit' => 'il preventivo supera la soglia autorizzata',
                'missing_required_fields' => 'mancano dati necessari per formulare il preventivo',
                'ambiguous_pricing_rule' => 'più fasce di prezzo risultano compatibili',
                'automatic_reply_limit_reached' => 'è stato raggiunto il limite di risposte automatiche',
                'sender_requires_verification' => 'il mittente della risposta richiede verifica',
                'whatsapp_auto_reply_disabled' => 'le risposte automatiche WhatsApp non sono attive',
                'whatsapp_recipient_not_allowed' => 'il numero non è nella lista dei test WhatsApp',
            ];
            $visibleBlockers = collect($reply->automation_blockers ?? [])
                ->map(fn ($blocker) => $automationBlockerLabels[$blocker] ?? str_replace('_', ' ', $blocker))
                ->values();
        @endphp
        <div class="warning">
            <strong>La bozza non è stata inviata automaticamente.</strong>
            @if($visibleBlockers->isNotEmpty()) Motivo: {{ $visibleBlockers->implode('; ') }}.@endif
        </div>
    @endif
    @if($quotation = $lead->quotations->first())
        <div class="notice"><div class="toolbar" style="margin:0"><div><strong>Preventivo {{ $quotation->document_number ?: 'v'.$quotation->version }}:</strong> € {{ number_format($quotation->minimum_price,0,',','.') }}–{{ number_format($quotation->maximum_price,0,',','.') }} + IVA · affidabilità {{ $quotation->confidence }}%. @if($quotation->valid_until) Valido fino al {{ $quotation->valid_until->format('d/m/Y') }}. @endif @if($quotation->auto_send_eligible) Idoneo all’automazione interna. @else Invio automatico bloccato: {{ implode(', ',$quotation->automation_blockers ?? []) }}. @endif</div>@if($quotation->reply && str_contains($quotation->reply->reply_kind,'quotation'))<a class="btn btn-muted" href="{{ route('leads.quotations.pdf',[$lead,$quotation]) }}">Scarica PDF</a>@endif</div></div>
    @endif
    @if($reply->status === 'sent')
        <p><strong>A:</strong> {{ $reply->recipient }}</p>
        @if($reply->channel === 'email')<p><strong>Oggetto:</strong> {{ $reply->subject }}</p>@endif
        <div class="email-preview">{!! nl2br(e($reply->body)) !!}</div>
        <p class="notice">Inviata via {{ $reply->channel === 'whatsapp' ? 'WhatsApp' : 'email' }} il {{ $reply->sent_at->format('d/m/Y H:i') }} da {{ $reply->approver?->name ?? 'Daria' }}.</p>
        @if($reply->follow_up_at)<p><strong>Follow-up:</strong> {{ $reply->follow_up_at->format('d/m/Y H:i') }}</p>@endif
    @else
        @if($reply->channel === 'email' && config('mail.default') === 'log')
            <div class="warning">La posta è in modalità <strong>log</strong>: configura SMTP prima di inviare email reali.</div>
        @endif
        <form method="post" action="{{ route('replies.update', [$lead, $reply]) }}">
            @csrf @method('patch')
            <label>Destinatario</label><input type="{{ $reply->channel === 'email' ? 'email' : 'text' }}" name="recipient" value="{{ old('recipient', $reply->recipient) }}" required>
            @if($reply->channel === 'email')<label>Oggetto</label><input name="subject" value="{{ old('subject', $reply->subject) }}" required>@else<input type="hidden" name="subject" value="WhatsApp">@endif
            <label>Testo</label><textarea name="body" rows="12" required>{{ old('body', $reply->body) }}</textarea>
            <label>Follow-up opzionale</label>
            <input type="datetime-local" name="follow_up_at" value="{{ old('follow_up_at', $reply->follow_up_at?->format('Y-m-d\TH:i')) }}">
            <p class="muted">La data verrà mostrata come prossima azione del lead dopo l’invio.</p>
            <button class="btn btn-muted">Salva bozza</button>
        </form>
        <form method="post" action="{{ route('replies.send', [$lead, $reply]) }}" style="margin-top:1rem" onsubmit="return confirm('Confermi l’approvazione e l’invio di questa risposta?')">
            @csrf
            <button class="btn">Approva e invia la versione salvata</button>
        </form>
        @if($reply->last_error)<p class="error">Ultimo invio non riuscito: {{ $reply->last_error }}</p>@endif
    @endif
</section>
@elseif($lead->analyses->isNotEmpty() && filled($lead->email))
<section class="card" style="margin-top:1rem">
    @error('reply')<div class="error">{{ $message }}</div>@enderror
    <p>Nessuna bozza disponibile. Ripeti l’analisi per generarne una.</p>
</section>
@endif

@if(auth()->user()->roleFor(app(\App\Support\Tenancy\TenantContext::class)->organization()) === 'owner')
<section class="card" style="margin-top:1rem;border-color:#fda29b">
    <h2 style="color:#b42318">Elimina definitivamente il lead</h2>
    <p>Questa operazione elimina il lead e tutti i dati collegati: contatti, analisi, email, bozze, preventivi, attività e dati tecnici di elaborazione. Non può essere annullata.</p>
    <form method="post" action="{{ route('leads.destroy', $lead) }}" onsubmit="return confirm('Confermi la cancellazione DEFINITIVA di questo lead e di tutti i dati collegati?')">
        @csrf @method('delete')
        <label>Scrivi ELIMINA per confermare</label>
        <input name="confirmation" autocomplete="off" required pattern="ELIMINA">
        @error('confirmation')<div class="error">{{ $message }}</div>@enderror
        <br><button class="btn" style="background:#b42318">Elimina definitivamente</button>
    </form>
</section>
@endif
@endsection

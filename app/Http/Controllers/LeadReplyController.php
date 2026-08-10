<?php

namespace App\Http\Controllers;

use App\Mail\LeadReplyMail;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LeadReplyController extends Controller
{
    public function update(Request $request, string $lead, string $reply): RedirectResponse
    {
        $lead = Lead::query()->findOrFail($lead);
        $reply = LeadReply::query()->where('lead_id', $lead->id)->findOrFail($reply);
        abort_if($reply->status === 'sent', 422, 'Una email già inviata non può essere modificata.');
        $data = $request->validate([
            'recipient' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'follow_up_at' => ['nullable', 'date', 'after:now'],
        ]);
        $reply->update([...$data, 'last_error' => null]);
        $lead->update([
            'next_action_at' => $data['follow_up_at'] ?? null,
            'operational_status' => 'awaiting_approval',
            'last_activity_at' => now(),
        ]);
        Activity::create([
            'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
            'actor_id' => $request->user()->id, 'type' => 'reply_draft_updated',
            'title' => 'Bozza email aggiornata',
            'data' => ['reply_id' => $reply->id, 'follow_up_at' => $data['follow_up_at'] ?? null],
            'occurred_at' => now(),
        ]);

        return back()->with('status', 'Bozza salvata. Ora puoi approvarla e inviarla.');
    }

    public function send(Request $request, string $lead, string $reply): RedirectResponse
    {
        $lead = Lead::query()->findOrFail($lead);
        $reply = LeadReply::query()->where('lead_id', $lead->id)->findOrFail($reply);
        abort_if($reply->status === 'sent', 422, 'Questa email è già stata inviata.');
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withErrors(['reply' => 'Invio bloccato: configura un servizio SMTP reale nel file .env.']);
        }

        try {
            Mail::to($reply->recipient)->send(new LeadReplyMail($reply));
        } catch (Throwable $exception) {
            report($exception);
            $reply->update(['last_error' => mb_substr($exception->getMessage(), 0, 2000)]);

            return back()->withErrors(['reply' => 'Invio non riuscito. Controlla la configurazione SMTP e riprova.']);
        }

        $reply->update([
            'status' => 'sent', 'approved_by' => $request->user()->id,
            'approved_at' => now(), 'sent_at' => now(), 'last_error' => null,
        ]);
        $hasFollowUp = $reply->follow_up_at !== null;
        $lead->update([
            'operational_status' => $hasFollowUp ? 'follow_up_scheduled' : 'awaiting_customer',
            'next_action_at' => $reply->follow_up_at,
            'last_activity_at' => now(),
        ]);
        Activity::create([
            'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
            'actor_id' => $request->user()->id, 'type' => 'email_sent',
            'title' => 'Email approvata e inviata',
            'data' => ['reply_id' => $reply->id, 'recipient' => $reply->recipient, 'subject' => $reply->subject],
            'occurred_at' => now(),
        ]);
        if ($hasFollowUp) {
            Activity::create([
                'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                'actor_id' => $request->user()->id, 'type' => 'follow_up_scheduled',
                'title' => 'Follow-up pianificato',
                'data' => ['reply_id' => $reply->id, 'follow_up_at' => $reply->follow_up_at->toIso8601String()],
                'occurred_at' => now(),
            ]);
        }

        return back()->with('status', 'Email inviata a '.$reply->recipient.'.');
    }
}

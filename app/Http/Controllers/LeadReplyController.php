<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadReply;
use App\Services\Mail\SendLeadReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class LeadReplyController extends Controller
{
    public function update(Request $request, string $lead, string $reply): RedirectResponse
    {
        $lead = Lead::query()->findOrFail($lead);
        $reply = LeadReply::query()->where('lead_id', $lead->id)->findOrFail($reply);
        abort_if($reply->status === 'sent', 422, 'Una risposta già inviata non può essere modificata.');
        $recipientRules = $reply->channel === 'whatsapp'
            ? ['required', 'string', 'max:40', 'regex:/^\+?[0-9][0-9\s().-]{6,38}$/']
            : ['required', 'email', 'max:255'];
        $data = $request->validate([
            'recipient' => $recipientRules,
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'follow_up_at' => ['nullable', 'date', 'after:now'],
        ]);
        $reply->update([...$data, 'last_error' => null, 'automation_eligible' => false, 'automation_blockers' => ['manually_edited']]);
        $lead->update([
            'next_action_at' => $data['follow_up_at'] ?? null,
            'operational_status' => 'awaiting_approval',
            'last_activity_at' => now(),
        ]);
        Activity::create([
            'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
            'actor_id' => $request->user()->id, 'type' => 'reply_draft_updated',
            'title' => $reply->channel === 'whatsapp' ? 'Bozza WhatsApp aggiornata' : 'Bozza email aggiornata',
            'data' => ['reply_id' => $reply->id, 'channel' => $reply->channel, 'follow_up_at' => $data['follow_up_at'] ?? null],
            'occurred_at' => now(),
        ]);

        return back()->with('status', 'Bozza salvata. Ora puoi approvarla e inviarla.');
    }

    public function send(Request $request, string $lead, string $reply, SendLeadReply $sender): RedirectResponse
    {
        $lead = Lead::query()->findOrFail($lead);
        $reply = LeadReply::query()->where('lead_id', $lead->id)->findOrFail($reply);
        abort_if($reply->status === 'sent', 422, 'Questa risposta è già stata inviata.');
        try {
            $sender->handle($reply, $request->user()->id);
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['reply' => 'Invio non riuscito: '.$exception->getMessage()]);
        }

        return back()->with('status', ($reply->channel === 'whatsapp' ? 'Messaggio WhatsApp' : 'Email').' inviato a '.$reply->recipient.'.');
    }
}

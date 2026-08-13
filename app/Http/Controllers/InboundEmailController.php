<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\InboundEmail;
use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\LeadReply;
use App\Services\Ai\GenerateLeadReply;
use App\Services\Leads\LeadData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class InboundEmailController extends Controller
{
    public function index(): View
    {
        $emails = InboundEmail::query()
            ->where('status', 'pending')
            ->latest('received_at')
            ->paginate(25);
        $leads = Lead::query()
            ->whereHas('replies', fn ($query) => $query->where('status', 'sent'))
            ->latest('last_activity_at')
            ->limit(150)
            ->get(['id', 'name', 'company', 'email']);

        return view('inbound-emails.index', compact('emails', 'leads'));
    }

    public function link(Request $request, string $email, GenerateLeadReply $replyGenerator): RedirectResponse
    {
        $data = $request->validate([
            'lead_id' => ['required', 'uuid'],
            'add_secondary_contact' => ['nullable', 'boolean'],
        ]);
        $inbound = InboundEmail::query()->where('status', 'pending')->findOrFail($email);
        $lead = Lead::query()->findOrFail($data['lead_id']);
        $sentReply = LeadReply::query()
            ->where('lead_id', $lead->id)
            ->where('status', 'sent')
            ->latest('sent_at')
            ->first();

        DB::transaction(function () use ($request, $data, $inbound, $lead, $sentReply): void {
            $hadFollowUp = $lead->next_action_at !== null
                || ($sentReply?->follow_up_at !== null && $sentReply?->follow_up_cancelled_at === null);
            $senderDiffers = LeadData::normalizeEmail($inbound->from_address) !== $lead->email_normalized;

            $inbound->update([
                'lead_id' => $lead->id,
                'lead_reply_id' => $sentReply?->id,
                'status' => 'linked',
                'match_confidence' => 'manual',
                'match_reason' => 'manual',
                'sender_differs' => $senderDiffers,
                'linked_by' => $request->user()->id,
                'linked_at' => now(),
            ]);
            if ($sentReply && $hadFollowUp) {
                $sentReply->update(['follow_up_cancelled_at' => now()]);
            }
            $lead->update(['operational_status' => 'needs_action', 'next_action_at' => null, 'last_activity_at' => now()]);
            Activity::create([
                'organization_id' => $lead->organization_id,
                'lead_id' => $lead->id,
                'actor_id' => $request->user()->id,
                'type' => 'email_received',
                'title' => 'Email associata manualmente',
                'data' => ['inbound_email_id' => $inbound->id, 'from' => $inbound->from_address],
                'occurred_at' => $inbound->received_at,
            ]);
            if ($hadFollowUp) {
                Activity::create([
                    'organization_id' => $lead->organization_id,
                    'lead_id' => $lead->id,
                    'actor_id' => $request->user()->id,
                    'type' => 'follow_up_cancelled',
                    'title' => 'Follow-up annullato dopo la risposta',
                    'data' => ['inbound_email_id' => $inbound->id],
                    'occurred_at' => now(),
                ]);
            }

            if (($data['add_secondary_contact'] ?? false) && $senderDiffers) {
                $normalized = LeadData::normalizeEmail($inbound->from_address);
                LeadContact::query()->firstOrCreate(
                    ['lead_id' => $lead->id, 'email_normalized' => $normalized],
                    [
                        'organization_id' => $lead->organization_id,
                        'name' => $inbound->from_name ?: $inbound->from_address,
                        'email' => $inbound->from_address,
                        'company' => $lead->company,
                        'is_primary' => false,
                    ],
                );
            }
        });

        $analysis = $lead->analyses()->first();
        if ($analysis) {
            try {
                $replyGenerator->handle($lead->fresh(), $analysis, $request->user()->id, [
                    'incoming_email' => [
                        'message_id' => $inbound->message_id,
                        'from' => $inbound->from_address,
                        'subject' => $inbound->subject,
                        'body' => mb_substr($inbound->body, 0, 12000),
                        'received_at' => $inbound->received_at->toIso8601String(),
                    ],
                ]);
            } catch (Throwable $exception) {
                report($exception);

                return redirect()->route('leads.show', $lead)
                    ->with('status', 'Email associata. La bozza automatica non è stata generata: puoi ripetere l’analisi.');
            }
        }

        return redirect()->route('leads.show', $lead)->with('status', 'Email associata al lead.');
    }
}

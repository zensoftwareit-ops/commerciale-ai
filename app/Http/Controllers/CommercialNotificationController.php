<?php

namespace App\Http\Controllers;

use App\Models\CommercialNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommercialNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = CommercialNotification::query()
            ->where('user_id', $request->user()->id)
            ->with('lead')
            ->latest()
            ->paginate(30);

        return view('notifications.index', compact('notifications'));
    }

    public function unread(Request $request): JsonResponse
    {
        $query = CommercialNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at');
        $items = (clone $query)->with('lead')->latest()->limit(10)->get()->map(fn (CommercialNotification $notification) => [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'created_at' => $notification->created_at->toIso8601String(),
            'url' => route('notifications.open', $notification),
        ]);

        return response()->json(['count' => $query->count(), 'items' => $items]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $notification = $this->forUser($request, $notification);
        $notification->update(['read_at' => now()]);

        return back()->with('status', 'Notifica archiviata.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        CommercialNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Tutte le notifiche sono state archiviate.');
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $notification = $this->forUser($request, $notification);
        $notification->update(['read_at' => now()]);

        return redirect()->route('leads.show', $notification->lead_id);
    }

    private function forUser(Request $request, string $id): CommercialNotification
    {
        return CommercialNotification::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}


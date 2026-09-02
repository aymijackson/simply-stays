<?php

namespace Modules\Admin\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Events\Models\Event;

class AdminEventController extends Controller
{
    public function index(): View
    {
        $pendingEvents = Event::with('creator')->where('status', 'pending_approval')->latest()->get();
        $publishedEvents = Event::with('creator')->where('status', 'published')->latest()->paginate(20);

        return view('admin::events.index', compact('pendingEvents', 'publishedEvents'));
    }

    public function approve(Event $event): RedirectResponse
    {
        abort_unless($event->status === 'pending_approval', 422);

        $event->update([
            'status' => 'published',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return back()->with('status', 'Event approved and published.');
    }

    public function reject(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->status === 'pending_approval', 422);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $event->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return back()->with('status', 'Event rejected.');
    }
}

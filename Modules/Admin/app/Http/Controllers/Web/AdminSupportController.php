<?php

namespace Modules\Admin\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Support\Models\SupportConversation;

class AdminSupportController extends Controller
{
    public function index(Request $request): View
    {
        $conversations = SupportConversation::with('user')
            ->withCount(['messages as unread_count' => function ($query) {
                $query->where('sender_type', 'customer')->whereNull('read_by_admin_at');
            }])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();

        $activeId = (int) $request->query('conversation', $conversations->first()?->id);
        $active = $conversations->firstWhere('id', $activeId);

        if ($active) {
            $active->messages()
                ->where('sender_type', 'customer')
                ->whereNull('read_by_admin_at')
                ->update(['read_by_admin_at' => now()]);
        }

        return view('admin::support.index', [
            'conversations' => $conversations,
            'active' => $active,
            'messages' => $active?->messages()->get() ?? collect(),
        ]);
    }

    public function messages(SupportConversation $conversation): JsonResponse
    {
        $conversation->messages()
            ->where('sender_type', 'customer')
            ->whereNull('read_by_admin_at')
            ->update(['read_by_admin_at' => now()]);

        return response()->json([
            'status' => $conversation->status,
            'messages' => $conversation->messages()->get()->map(fn ($message) => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_type' => $message->sender_type,
                'sender_name' => $message->sender_type === 'admin' ? ($message->sender?->name ?? 'Support team') : $conversation->displayName(),
                'created_at' => $message->created_at->format('g:i A'),
            ]),
        ]);
    }

    public function reply(Request $request, SupportConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $conversation->messages()->create([
            'sender_type' => 'admin',
            'sender_id' => Auth::id(),
            'body' => $validated['body'],
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'assigned_admin_id' => $conversation->assigned_admin_id ?? Auth::id(),
            'status' => 'open',
        ]);

        return back()->with('status', 'Reply sent.');
    }

    public function close(SupportConversation $conversation): RedirectResponse
    {
        $conversation->update([
            'status' => $conversation->status === 'closed' ? 'open' : 'closed',
        ]);

        return back()->with('status', $conversation->status === 'closed' ? 'Conversation closed.' : 'Conversation reopened.');
    }
}

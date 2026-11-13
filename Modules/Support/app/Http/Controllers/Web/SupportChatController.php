<?php

namespace Modules\Support\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Modules\Support\Models\SupportConversation;

class SupportChatController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $cookieToken = null;

        if (Auth::check()) {
            $conversation = SupportConversation::firstOrCreate(
                ['user_id' => Auth::id()],
                ['status' => 'open']
            );
        } else {
            $cookieToken = $request->cookie('support_guest_token');
            $conversation = $cookieToken ? SupportConversation::where('guest_token', $cookieToken)->first() : null;

            if (! $conversation) {
                $cookieToken = (string) Str::uuid();
                $conversation = SupportConversation::create([
                    'guest_token' => $cookieToken,
                    'status' => 'open',
                ]);
            }

            if (! empty($validated['name']) && ! $conversation->guest_name) {
                $conversation->guest_name = $validated['name'];
            }
            if (! empty($validated['email']) && ! $conversation->guest_email) {
                $conversation->guest_email = $validated['email'];
            }
            if ($conversation->isDirty()) {
                $conversation->save();
            }
        }

        $response = response()->json([
            'conversation_id' => $conversation->public_id,
            'status' => $conversation->status,
            'messages' => $this->formatMessages($conversation),
        ]);

        if ($cookieToken) {
            $response->withCookie(Cookie::make('support_guest_token', $cookieToken, 60 * 24 * 365));
        }

        return $response;
    }

    public function messages(Request $request, SupportConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $afterId = (int) $request->query('after_id', 0);

        $messages = $conversation->messages()->where('id', '>', $afterId)->get();

        $conversation->messages()
            ->where('sender_type', 'admin')
            ->whereNull('read_by_customer_at')
            ->update(['read_by_customer_at' => now()]);

        return response()->json([
            'status' => $conversation->status,
            'messages' => $this->formatMessages($conversation, $messages),
        ]);
    }

    public function store(Request $request, SupportConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_type' => 'customer',
            'sender_id' => Auth::id(),
            'body' => $validated['body'],
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'status' => 'open',
        ]);

        return response()->json([
            'status' => $conversation->status,
            'messages' => $this->formatMessages($conversation, collect([$message])),
        ]);
    }

    private function authorizeConversation(Request $request, SupportConversation $conversation): void
    {
        if (Auth::check()) {
            abort_unless($conversation->user_id === Auth::id(), 403);

            return;
        }

        $cookieToken = $request->cookie('support_guest_token');
        abort_unless($cookieToken && $conversation->guest_token === $cookieToken, 403);
    }

    private function formatMessages(SupportConversation $conversation, $messages = null)
    {
        return ($messages ?? $conversation->messages)->map(fn ($message) => [
            'id' => $message->id,
            'body' => $message->body,
            'sender_type' => $message->sender_type,
            'sender_name' => $message->sender_type === 'admin' ? ($message->sender?->name ?? 'Support team') : null,
            'created_at' => $message->created_at->format('g:i A'),
        ])->values();
    }
}

@extends('admin::layouts.admin')

@section('title', 'Support inbox')

@section('content')
<div class="page page--tight">
    <div class="container">
        <h1 style="margin-top: 0;">Support inbox</h1>

        <div class="support-inbox">
            <div class="support-inbox__list">
                @forelse ($conversations as $conversation)
                    <a href="{{ route('admin.support.index', ['conversation' => $conversation->id]) }}"
                       class="support-inbox__item @if ($active && $active->id === $conversation->id) is-active @endif">
                        <span class="avatar" style="width:34px;height:34px;font-size:0.8rem;">{{ strtoupper(substr($conversation->displayName(), 0, 1)) }}</span>
                        <span class="support-inbox__item-body">
                            <span class="support-inbox__item-name">{{ $conversation->displayName() }}</span>
                            <span class="support-inbox__item-meta">
                                {{ $conversation->last_message_at?->diffForHumans() ?? 'No messages yet' }}
                                @if ($conversation->status === 'closed') &middot; Closed @endif
                            </span>
                        </span>
                        @if ($conversation->unread_count > 0)
                            <span class="badge badge--gold">{{ $conversation->unread_count }}</span>
                        @endif
                    </a>
                @empty
                    <p class="form-hint" style="padding: 1rem;">No conversations yet.</p>
                @endforelse
            </div>

            <div class="support-inbox__thread">
                @if ($active)
                    <div class="support-inbox__thread-head">
                        <div>
                            <strong style="color: var(--color-navy-950);">{{ $active->displayName() }}</strong>
                            @if ($active->guest_email || $active->user?->email)
                                <div class="form-hint" style="margin: 0;">{{ $active->user?->email ?? $active->guest_email }}</div>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.support.close', $active) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-outline btn-sm">
                                {{ $active->status === 'closed' ? 'Reopen' : 'Close' }}
                            </button>
                        </form>
                    </div>

                    <div class="support-inbox__messages"
                         data-support-admin-thread
                         data-messages-url="{{ route('admin.support.messages', $active) }}"
                         data-last-id="{{ $messages->last()->id ?? 0 }}">
                        @foreach ($messages as $message)
                            <div class="support-message support-message--{{ $message->sender_type }}" data-message-id="{{ $message->id }}">
                                <div class="support-message__bubble">{{ $message->body }}</div>
                                <div class="support-message__meta">{{ $message->created_at->format('g:i A') }}</div>
                            </div>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('admin.support.reply', $active) }}" class="support-inbox__reply">
                        @csrf
                        <textarea name="body" rows="2" class="form-control" placeholder="Type a reply&hellip;" required></textarea>
                        <button type="submit" class="btn btn-primary">Send</button>
                    </form>
                @else
                    <div class="support-inbox__empty">
                        <p>Select a conversation to view messages.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    var $thread = $('[data-support-admin-thread]');
    if (!$thread.length) return;

    var messagesUrl = $thread.data('messages-url');
    var lastId = $thread.data('last-id');
    var $messages = $thread;

    function poll() {
        axios.get(messagesUrl, { params: { after_id: lastId } }).then(function (res) {
            res.data.messages.forEach(function (message) {
                if (message.id <= lastId) return;
                lastId = message.id;
                var $bubble = $('<div>').addClass('support-message support-message--' + message.sender_type).attr('data-message-id', message.id);
                $('<div>').addClass('support-message__bubble').text(message.body).appendTo($bubble);
                $('<div>').addClass('support-message__meta').text(message.created_at).appendTo($bubble);
                $messages.append($bubble);
            });
            $messages.scrollTop($messages.prop('scrollHeight'));
        });
    }

    $messages.scrollTop($messages.prop('scrollHeight'));
    setInterval(poll, 4000);
});
</script>
@endpush

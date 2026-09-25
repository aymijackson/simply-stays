<div class="support-widget" data-support-widget data-start-url="{{ route('support.start') }}">
    <div class="support-widget__panel">
        <div class="support-widget__header">
            <div>
                <strong>Chat with us</strong>
                <span>We usually reply within a few minutes</span>
            </div>
            <button type="button" class="support-widget__close" data-support-close aria-label="Close chat">
                <x-icon name="x" style="width:18px;height:18px;" />
            </button>
        </div>

        <div class="support-widget__messages" data-support-messages>
            <p class="support-widget__empty" data-support-empty>Send us a message and we'll get back to you shortly.</p>
        </div>

        <form class="support-widget__composer" data-support-form>
            <input type="text" name="body" placeholder="Type a message&hellip;" autocomplete="off" required>
            <button type="submit" class="support-widget__send" aria-label="Send message">
                <x-icon name="arrow-right" style="width:18px;height:18px;" />
            </button>
        </form>
    </div>

    <button type="button" class="support-widget__bubble" data-support-toggle aria-label="Open support chat">
        <x-icon name="headset" style="width:24px;height:24px;" />
        <span class="support-widget__bubble-badge" data-support-badge style="display:none;"></span>
    </button>
</div>

{{--
    Live new-order alerts for staff who handle service orders (admins and vendors).
    Polls the order feed, then shows a card, plays a chime and badges the tab title.
    Browsers only allow sound after the person has clicked something on the page,
    so the switch below doubles as the "unlock audio" click.
--}}
<div class="oa" data-order-alerts data-feed="{{ route('provider.services.orders.feed') }}" data-orders="{{ route('provider.services.orders') }}" hidden>
    <div class="oa__stack" data-oa-stack aria-live="assertive"></div>
    <button type="button" class="oa__switch" data-oa-switch aria-pressed="false" title="New-order sound">
        <span class="oa__dot"></span>
        <span data-oa-label>Order alerts: sound off</span>
        <span class="oa__count" data-oa-count hidden></span>
    </button>
</div>

<script>
(function () {
    var root = document.querySelector('[data-order-alerts]');
    if (!root || !window.fetch) { return; }
    root.hidden = false;

    var feed = root.dataset.feed, ordersUrl = root.dataset.orders;
    var stack = root.querySelector('[data-oa-stack]');
    var sw = root.querySelector('[data-oa-switch]');
    var label = root.querySelector('[data-oa-label]');
    var countEl = root.querySelector('[data-oa-count]');
    var baseTitle = document.title;
    var latest = null, audio = null, timer = null;
    var KEY = 'orderAlertsSound';
    var onOrdersPage = location.pathname.replace(/\/$/, '') === new URL(ordersUrl).pathname.replace(/\/$/, '');

    function soundOn() { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
    function render(on) {
        sw.setAttribute('aria-pressed', on ? 'true' : 'false');
        sw.classList.toggle('is-on', on);
        label.textContent = 'Order alerts: sound ' + (on ? 'on' : 'off');
    }
    // Only called from a click, so the browser allows the test chime.
    function setSound(on) {
        try { localStorage.setItem(KEY, on ? '1' : '0'); } catch (e) {}
        render(on);
        if (on) { unlock(); chime(); }
    }

    function unlock() {
        var Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) { return; }
        audio = audio || new Ctx();
        if (audio.state === 'suspended') { audio.resume(); }
    }

    // Two rising tones, about half a second. No audio file needed.
    function chime() {
        if (!soundOn() || !audio) { return; }
        [[880, 0], [1320, 0.18]].forEach(function (n) {
            var osc = audio.createOscillator(), gain = audio.createGain(), t = audio.currentTime + n[1];
            osc.type = 'sine';
            osc.frequency.value = n[0];
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(0.35, t + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.3);
            osc.connect(gain).connect(audio.destination);
            osc.start(t);
            osc.stop(t + 0.32);
        });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function card(order) {
        var el = document.createElement('a');
        el.className = 'oa__card';
        el.href = order.url;
        el.innerHTML = '<strong>New order #' + order.id + '</strong>' +
            '<span>' + escapeHtml(order.title) + ' · ' + escapeHtml(order.total) + '</span>' +
            '<span>' + escapeHtml(order.who) + (order.where ? ' — ' + escapeHtml(order.where) : '') + '</span>';
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'oa__close';
        close.setAttribute('aria-label', 'Dismiss');
        close.innerHTML = '&times;';
        close.addEventListener('click', function (e) { e.preventDefault(); el.remove(); });
        el.appendChild(close);
        stack.prepend(el);
        while (stack.children.length > 4) { stack.lastElementChild.remove(); }
    }

    function showUnseen(n) {
        countEl.hidden = !n;
        countEl.textContent = n;
        document.title = (n ? '(' + n + ') ' : '') + baseTitle;
    }

    function poll() {
        fetch(feed + '?after=' + (latest || 0), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (data) {
                if (latest !== null && data.orders.length) {
                    data.orders.forEach(card);
                    chime();
                    if (onOrdersPage) { showReloadBanner(data.orders.length); }
                }
                latest = Math.max(latest || 0, data.latest_id || 0);
                showUnseen(data.unseen);
            })
            .catch(function () { /* offline or signed out: try again next tick */ })
            .finally(function () { timer = setTimeout(poll, 10000); });
    }

    function showReloadBanner(n) {
        var existing = document.querySelector('[data-oa-reload]');
        var total = n + (existing ? parseInt(existing.dataset.count, 10) : 0);
        if (!existing) {
            existing = document.createElement('button');
            existing.type = 'button';
            existing.className = 'oa__reload';
            existing.setAttribute('data-oa-reload', '');
            existing.addEventListener('click', function () { location.reload(); });
            var main = document.querySelector('.portal-main .container') || document.body;
            main.insertBefore(existing, main.firstChild);
        }
        existing.dataset.count = total;
        existing.textContent = total + ' new order' + (total === 1 ? '' : 's') + ' — show';
    }

    sw.addEventListener('click', function () { setSound(!soundOn()); });
    // Any click unlocks audio for the rest of the visit, if sound is switched on.
    document.addEventListener('click', function () { if (soundOn()) { unlock(); } }, { once: true });

    render(soundOn());
    poll();
})();
</script>

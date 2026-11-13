axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

var csrfMeta = document.querySelector('meta[name="csrf-token"]');
if (csrfMeta) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content');
}

$(function () {
    $('[data-nav-toggle]').on('click', function () {
        $('[data-nav-menu]').toggleClass('is-open');
    });

    $('[data-alert-dismiss]').on('click', function () {
        $(this).closest('[data-alert]').fadeOut(150);
    });

    $('[data-portal-sidebar-toggle]').on('click', function () {
        $('[data-portal-shell]').toggleClass('is-sidebar-open');
    });

    $('[data-portal-sidebar-backdrop]').on('click', function () {
        $('[data-portal-shell]').removeClass('is-sidebar-open');
    });

    $('[data-star-picker]').each(function () {
        var $picker = $(this);
        var $input = $('#' + $picker.data('input'));
        var $stars = $picker.find('.star-picker__star');

        function paint(value) {
            $stars.each(function () {
                $(this).toggleClass('is-active', $(this).data('value') <= value);
            });
        }

        paint($input.val() || 0);

        $stars.on('click', function () {
            var value = $(this).data('value');
            $input.val(value);
            paint(value);
        });
    });

    // ---------- Photo gallery: page slideshow + full-screen viewer ----------

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var $lightbox = $('#lightbox-overlay');
    var $stage = $('[data-lightbox-stage]');
    var $count = $('[data-lightbox-count]');
    var $thumbs = $('[data-lightbox-thumbs]');
    var $play = $('[data-lightbox-play]');
    var lightboxItems = [];
    var lightboxIndex = 0;
    var lightboxTimer = null;
    var lastFocus = null;

    function renderLightbox() {
        var item = lightboxItems[lightboxIndex];
        if (!item) return;

        $stage.empty();

        if (item.type === 'video') {
            $('<video controls autoplay></video>').attr('src', item.src).appendTo($stage);
        } else if (item.type === 'embed') {
            $('<iframe allowfullscreen frameborder="0"></iframe>').attr('src', item.src).appendTo($stage);
        } else {
            $('<img class="lightbox__img">').attr('src', item.src).attr('alt', item.alt || '').appendTo($stage);
        }

        $count.text((lightboxIndex + 1) + ' / ' + lightboxItems.length);
        $thumbs.children().removeClass('is-active').attr('aria-current', null)
            .eq(lightboxIndex).addClass('is-active').attr('aria-current', 'true')
            .each(function () { this.scrollIntoView({ block: 'nearest', inline: 'center' }); });

        // Warm the neighbours so next/previous feel instant.
        [lightboxIndex - 1, lightboxIndex + 1].forEach(function (i) {
            var next = lightboxItems[(i + lightboxItems.length) % lightboxItems.length];
            if (next && next.type === 'image') { new Image().src = next.src; }
        });
    }

    function buildThumbs() {
        $thumbs.empty().toggle(lightboxItems.length > 1);
        lightboxItems.forEach(function (item, i) {
            var $b = $('<button type="button" class="lightbox__thumb"></button>').attr('aria-label', 'Show item ' + (i + 1)).data('index', i);
            if (item.type === 'image') {
                $('<img loading="lazy" alt="">').attr('src', item.src).appendTo($b);
            } else {
                $b.addClass('lightbox__thumb--video').text('▶');
            }
            $b.appendTo($thumbs);
        });
    }

    function go(delta) {
        if (!lightboxItems.length) return;
        lightboxIndex = (lightboxIndex + delta + lightboxItems.length) % lightboxItems.length;
        renderLightbox();
    }

    function setPlaying(on) {
        clearInterval(lightboxTimer);
        lightboxTimer = on ? setInterval(function () { go(1); }, 3500) : null;
        $play.attr('aria-pressed', on ? 'true' : 'false').toggleClass('is-on', on);
        $('[data-lightbox-play-label]').text(on ? 'Pause slideshow' : 'Play slideshow');
    }

    function openLightbox(items, index) {
        lightboxItems = items;
        lightboxIndex = index;
        lastFocus = document.activeElement;
        buildThumbs();
        $play.toggle(items.length > 1);
        $('.lightbox__prev, .lightbox__next').toggle(items.length > 1);
        renderLightbox();
        $lightbox.css('display', 'flex');
        $('body').addClass('lightbox-open');
        $lightbox.find('[data-lightbox-close]').trigger('focus');
    }

    function closeLightbox() {
        setPlaying(false);
        $lightbox.hide();
        $stage.empty();
        $('body').removeClass('lightbox-open');
        if (lastFocus) { lastFocus.focus(); }
    }

    $(document).on('click', '[data-gallery-open]', function (e) {
        e.preventDefault();
        var $container = $(this).closest('[data-gallery]');
        var items = $container.data('items') || [];
        // The main tile opens at whichever photo the slideshow is showing.
        var index = $(this).closest('[data-gallery-show]').length
            ? parseInt($container.find('[data-gallery-show]').attr('data-current') || 0, 10)
            : ($(this).data('index') || 0);
        openLightbox(items, index);
    });

    $('[data-lightbox-close]').on('click', closeLightbox);
    $lightbox.on('click', function (e) {
        if (e.target === this) closeLightbox();
    });
    $('[data-lightbox-prev]').on('click', function () { setPlaying(false); go(-1); });
    $('[data-lightbox-next]').on('click', function () { setPlaying(false); go(1); });
    $play.on('click', function () { setPlaying(!lightboxTimer); });
    $thumbs.on('click', '.lightbox__thumb', function () {
        setPlaying(false);
        lightboxIndex = $(this).data('index');
        renderLightbox();
    });

    $(document).on('keydown', function (e) {
        if ($lightbox.css('display') === 'none') return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') { setPlaying(false); go(-1); }
        if (e.key === 'ArrowRight') { setPlaying(false); go(1); }
        if (e.key === ' ' && e.target === document.body) { e.preventDefault(); setPlaying(!lightboxTimer); }
    });

    // Swipe left/right on touch screens.
    (function () {
        var startX = null;
        $stage.on('touchstart', function (e) { startX = e.originalEvent.touches[0].clientX; });
        $stage.on('touchend', function (e) {
            if (startX === null) return;
            var dx = e.originalEvent.changedTouches[0].clientX - startX;
            startX = null;
            if (Math.abs(dx) > 40) { setPlaying(false); go(dx < 0 ? 1 : -1); }
        });
    })();

    // Main gallery tile: slideshow that advances on its own, pauses on hover/focus/touch.
    $('[data-gallery-show]').each(function () {
        var $show = $(this);
        var $slides = $show.find('.gallery-show__slide');
        var $dots = $show.find('[data-show-dot]');
        var current = 0;
        var timer = null;
        if ($slides.length < 2) return;

        function show(i) {
            current = (i + $slides.length) % $slides.length;
            $slides.removeClass('is-active').eq(current).addClass('is-active');
            $dots.removeClass('is-active').eq(current).addClass('is-active');
            $show.attr('data-current', current);
        }
        function start() {
            stop();
            if (!reduceMotion) { timer = setInterval(function () { show(current + 1); }, 4500); }
        }
        function stop() { clearInterval(timer); timer = null; }

        $show.on('click', '[data-show-prev]', function (e) { e.stopPropagation(); show(current - 1); start(); });
        $show.on('click', '[data-show-next]', function (e) { e.stopPropagation(); show(current + 1); start(); });
        $show.on('click', '[data-show-dot]', function (e) { e.stopPropagation(); show(parseInt($(this).data('show-dot'), 10)); start(); });
        $show.on('mouseenter focusin touchstart', stop).on('mouseleave focusout', start);
        document.addEventListener('visibilitychange', function () { document.hidden ? stop() : start(); });

        // Swipe the main tile on phones.
        var sx = null;
        $show.on('touchstart', function (e) { sx = e.originalEvent.touches[0].clientX; });
        $show.on('touchend', function (e) {
            if (sx === null) return;
            var dx = e.originalEvent.changedTouches[0].clientX - sx;
            sx = null;
            if (Math.abs(dx) > 40) { e.preventDefault(); show(current + (dx < 0 ? 1 : -1)); }
            start();
        });

        start();
    });

    $('[data-menu-item]').each(function () {
        var $item = $(this);
        var basePrice = parseInt($item.data('price'), 10) || 0;
        var $qty = $item.find('[data-qty-input]');
        var $total = $item.find('[data-item-total-display]');
        var $suggestions = $item.find('[data-suggestions-panel]');

        function recompute() {
            var quantity = parseInt($qty.val(), 10) || 0;
            var perUnitDelta = 0;

            $item.find('[data-option-toggle]:checked').each(function () {
                perUnitDelta += parseInt($(this).data('price-delta'), 10) || 0;
            });

            $item.find('[data-option-qty]').each(function () {
                var q = parseInt($(this).val(), 10) || 0;
                perUnitDelta += q * (parseInt($(this).data('price-delta'), 10) || 0);
            });

            var lineTotal = quantity * (basePrice + perUnitDelta);
            $total.text(quantity > 0 ? (lineTotal / 100).toFixed(2) : '');
            $suggestions.toggle(quantity > 0);
        }

        $item.on('change input', 'input', recompute);
        recompute();
    });

    $(document).on('click', '[data-add-suggestion]', function () {
        var id = $(this).data('add-suggestion');
        var $target = $('input[name="items[' + id + '][quantity]"]');

        if ($target.length) {
            var current = parseInt($target.val(), 10) || 0;
            $target.val(current + 1).trigger('change');
            $target[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    $(document).on('click', '[data-fill-address]', function () {
        $('#details_delivery_address').val($(this).data('fill-address'));
    });

    $('[data-hero-carousel]').each(function () {
        var $carousel = $(this);
        var $hero = $carousel.closest('.hero');
        var $slides = $carousel.find('.hero-carousel__slide');
        var $dots = $hero.find('[data-hero-dot]');
        var current = 0;
        var timer = null;

        function show(index) {
            current = (index + $slides.length) % $slides.length;
            $slides.removeClass('is-active').eq(current).addClass('is-active');
            $dots.removeClass('is-active').eq(current).addClass('is-active');
        }

        function start() {
            stop();
            if ($slides.length > 1) {
                timer = setInterval(function () { show(current + 1); }, 6000);
            }
        }

        function stop() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        $dots.on('click', function () {
            show($(this).index());
            start();
        });

        $hero.on('mouseenter', stop).on('mouseleave', start);

        start();
    });

    // Home page rows (stays, services, events): advance one card at a time on
    // their own, loop back at the end, and pause while the visitor hovers,
    // touches or uses the arrows. Arrows still work.
    function scrollTrack($track, direction) {
        var $item = $track.children().first();
        var step = $item.outerWidth(true) || 300;
        var max = $track[0].scrollWidth - $track[0].clientWidth;
        var target = $track.scrollLeft() + step * direction;

        if (direction > 0 && $track.scrollLeft() >= max - 4) { target = 0; }
        if (direction < 0 && $track.scrollLeft() <= 4) { target = max; }

        $track.stop(true).animate({ scrollLeft: target }, 500);
    }

    $('[data-carousel-prev], [data-carousel-next]').on('click', function () {
        var $track = $(this).closest('.section, .card').find('[data-carousel-track]').first();
        if (!$track.length) return;
        scrollTrack($track, $(this).is('[data-carousel-prev]') ? -1 : 1);
        $track.trigger('carousel:pause');
    });

    $('[data-carousel-track]').each(function () {
        var $track = $(this);
        var $section = $track.closest('.section, .card');
        var timer = null;
        var resumeTimer = null;
        if (reduceMotion) return;

        function overflowing() { return this.scrollWidth > this.clientWidth + 8; }
        function start() {
            stop();
            if (!overflowing.call($track[0])) return;
            timer = setInterval(function () {
                if (!document.hidden) { scrollTrack($track, 1); }
            }, 4000);
        }
        function stop() { clearInterval(timer); timer = null; }

        $section.on('mouseenter focusin', stop).on('mouseleave focusout', start);
        $track.on('touchstart', stop);
        // After someone scrolls or clicks an arrow, give them a moment before moving again.
        $track.on('touchend carousel:pause', function () {
            stop();
            clearTimeout(resumeTimer);
            resumeTimer = setTimeout(start, 8000);
        });
        $(window).on('resize', start);

        start();
    });

    $(document).on('change', '[data-theme-option]', function () {
        document.documentElement.setAttribute('data-theme', this.value);
    });

    $(document).on('change', '[data-layout-option]', function () {
        document.documentElement.setAttribute('data-layout', this.value);
    });

    if ('IntersectionObserver' in window) {
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        document.querySelectorAll('.reveal').forEach(function (el) {
            revealObserver.observe(el);
        });
    } else {
        $('.reveal').addClass('is-visible');
    }

    var $supportWidget = $('[data-support-widget]');
    if ($supportWidget.length) {
        var $toggle = $supportWidget.find('[data-support-toggle]');
        var $close = $supportWidget.find('[data-support-close]');
        var $messages = $supportWidget.find('[data-support-messages]');
        var $empty = $supportWidget.find('[data-support-empty]');
        var $form = $supportWidget.find('[data-support-form]');
        var $badge = $supportWidget.find('[data-support-badge]');
        var startUrl = $supportWidget.data('start-url');

        var conversationId = null;
        var lastId = 0;
        var pollTimer = null;
        var startPromise = null;

        function messagesUrl() {
            return '/support/conversations/' + conversationId + '/messages';
        }

        function renderMessage(message) {
            $empty.hide();
            var $bubble = $('<div>').addClass('support-message support-message--' + message.sender_type);
            $('<div>').addClass('support-message__bubble').text(message.body).appendTo($bubble);
            $('<div>').addClass('support-message__meta').text(message.created_at).appendTo($bubble);
            $messages.append($bubble);
            $messages.scrollTop($messages.prop('scrollHeight'));
        }

        function poll() {
            if (!conversationId) return;
            axios.get(messagesUrl(), { params: { after_id: lastId } }).then(function (res) {
                res.data.messages.forEach(function (message) {
                    if (message.id <= lastId) return;
                    lastId = message.id;
                    renderMessage(message);
                    if (!$supportWidget.hasClass('is-open') && message.sender_type === 'admin') {
                        $badge.text('1').show();
                    }
                });
            });
        }

        function ensureStarted() {
            if (startPromise) return startPromise;

            startPromise = axios.post(startUrl, {}).then(function (res) {
                conversationId = res.data.conversation_id;
                res.data.messages.forEach(function (message) {
                    lastId = Math.max(lastId, message.id);
                    renderMessage(message);
                });
                if (!pollTimer) pollTimer = setInterval(poll, 4000);
            });

            return startPromise;
        }

        $toggle.on('click', function () {
            $supportWidget.toggleClass('is-open');
            if ($supportWidget.hasClass('is-open')) {
                $badge.hide().text('');
                ensureStarted();
            }
        });

        $close.on('click', function () {
            $supportWidget.removeClass('is-open');
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            var $input = $form.find('input[name="body"]');
            var body = $input.val().trim();
            if (!body) return;
            $input.val('');

            ensureStarted().then(function () {
                axios.post(messagesUrl(), { body: body }).then(function (res) {
                    res.data.messages.forEach(function (message) {
                        lastId = Math.max(lastId, message.id);
                        renderMessage(message);
                    });
                });
            });
        });
    }
});

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

    var $lightbox = $('#lightbox-overlay');
    var $stage = $('[data-lightbox-stage]');
    var lightboxItems = [];
    var lightboxIndex = 0;

    function renderLightbox() {
        var item = lightboxItems[lightboxIndex];
        if (!item) return;

        $stage.empty();

        if (item.type === 'video') {
            $('<video controls autoplay></video>').attr('src', item.src).appendTo($stage);
        } else if (item.type === 'embed') {
            $('<iframe allowfullscreen frameborder="0"></iframe>').attr('src', item.src).appendTo($stage);
        } else {
            $('<img>').attr('src', item.src).attr('alt', item.alt || '').appendTo($stage);
        }
    }

    function openLightbox(items, index) {
        lightboxItems = items;
        lightboxIndex = index;
        renderLightbox();
        $lightbox.css('display', 'flex');
    }

    function closeLightbox() {
        $lightbox.hide();
        $stage.empty();
    }

    $(document).on('click', '[data-gallery-open]', function (e) {
        e.preventDefault();
        var $container = $(this).closest('[data-gallery]');
        var items = $container.data('items') || [];
        openLightbox(items, $(this).data('index') || 0);
    });

    $('[data-lightbox-close]').on('click', closeLightbox);
    $lightbox.on('click', function (e) {
        if (e.target === this) closeLightbox();
    });

    $('[data-lightbox-prev]').on('click', function () {
        lightboxIndex = (lightboxIndex - 1 + lightboxItems.length) % lightboxItems.length;
        renderLightbox();
    });

    $('[data-lightbox-next]').on('click', function () {
        lightboxIndex = (lightboxIndex + 1) % lightboxItems.length;
        renderLightbox();
    });

    $(document).on('keydown', function (e) {
        if ($lightbox.css('display') === 'none') return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') $('[data-lightbox-prev]').trigger('click');
        if (e.key === 'ArrowRight') $('[data-lightbox-next]').trigger('click');
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

    $('[data-carousel-prev], [data-carousel-next]').on('click', function () {
        var $track = $(this).closest('.section, .card').find('[data-carousel-track]').first();
        if (!$track.length) return;

        var $item = $track.children().first();
        var amount = ($item.outerWidth(true) || 300) * 2;
        var direction = $(this).is('[data-carousel-prev]') ? -1 : 1;

        $track.stop(true).animate({ scrollLeft: $track.scrollLeft() + amount * direction }, 400);
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

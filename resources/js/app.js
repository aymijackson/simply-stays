import './bootstrap';
import $ from 'jquery';

window.$ = window.jQuery = $;

$(function () {
    $('[data-nav-toggle]').on('click', function () {
        $('[data-nav-menu]').toggleClass('is-open');
    });

    $('[data-alert-dismiss]').on('click', function () {
        $(this).closest('[data-alert]').fadeOut(150);
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
});

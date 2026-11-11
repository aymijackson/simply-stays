{{--
    Draw-to-sign pad. Writes a PNG data URL into the hidden `signature` input on submit.
    Leaving the pad untouched keeps any existing signature ($existing).
--}}
@php
    // A signature drawn before a failed validation round-trip is resubmitted as-is unless redrawn.
    $pending = old('signature');
    $existing = $pending ?: ($existing ?? null);
@endphp
<div class="signature-pad" data-signature-pad>
    @if (! empty($existing))
        <div class="signature-pad__existing" @if ($pending) data-signature-pending @endif>
            <img src="{{ $existing }}" alt="Current signature">
            <p class="form-hint">Signed. Draw below to replace this signature.</p>
        </div>
    @endif

    <canvas class="signature-pad__canvas" data-signature-canvas aria-label="Signature area: draw your signature with a mouse, finger or stylus"></canvas>
    <div class="signature-pad__footer">
        <span class="form-hint" style="margin: 0;">Sign above with your mouse, finger or stylus.</span>
        <button type="button" class="btn btn-outline btn-sm" data-signature-clear>Clear</button>
    </div>
    <input type="hidden" name="signature" value="{{ $pending }}" data-signature-input data-required="{{ $required && ! $pending ? '1' : '0' }}">
    @error('signature')
        <p class="form-error">{{ $message === 'The signature field is required.' ? 'Please sign the form.' : $message }}</p>
    @enderror
    <p class="form-error" data-signature-error hidden>Please sign the form before submitting.</p>
</div>

@once
@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-signature-pad]').forEach(function (pad) {
        var canvas = pad.querySelector('[data-signature-canvas]');
        var input = pad.querySelector('[data-signature-input]');
        var error = pad.querySelector('[data-signature-error]');
        var ctx = canvas.getContext('2d');
        var drawing = false;
        var dirty = false;
        var last = null;

        function resize() {
            // Match the backing store to the displayed size so strokes aren't blurry or offset.
            var ratio = window.devicePixelRatio || 1;
            var rect = canvas.getBoundingClientRect();
            var snapshot = dirty ? canvas.toDataURL() : null;
            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineWidth = 2.2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#0a2540';
            if (snapshot) {
                var img = new Image();
                img.onload = function () { ctx.drawImage(img, 0, 0, rect.width, rect.height); };
                img.src = snapshot;
            }
        }

        function point(e) {
            var rect = canvas.getBoundingClientRect();
            return { x: e.clientX - rect.left, y: e.clientY - rect.top };
        }

        canvas.addEventListener('pointerdown', function (e) {
            drawing = true;
            last = point(e);
            canvas.setPointerCapture(e.pointerId);
            ctx.beginPath();
            ctx.arc(last.x, last.y, 1.1, 0, Math.PI * 2);
            ctx.fillStyle = ctx.strokeStyle;
            ctx.fill();
            dirty = true;
            e.preventDefault();
        });
        canvas.addEventListener('pointermove', function (e) {
            if (!drawing) return;
            var p = point(e);
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            last = p;
            e.preventDefault();
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (evt) {
            canvas.addEventListener(evt, function () { drawing = false; });
        });

        pad.querySelector('[data-signature-clear]').addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            dirty = false;
            input.value = '';
            var existing = pad.querySelector('[data-signature-pending]');
            if (existing) existing.hidden = true;
        });

        var form = pad.closest('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (dirty) input.value = canvas.toDataURL('image/png');
                if (input.dataset.required === '1' && !input.value) {
                    e.preventDefault();
                    error.hidden = false;
                    canvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }

        window.addEventListener('resize', resize);
        resize();
    });
})();
</script>
@endpush
@endonce

@extends(auth()->user()->portalLayout())

@section('title', 'Book ' . $listing->title)

@section('content')
<div class="page page--tight">
    <div class="container" style="max-width: 640px;">
        <a href="{{ route('listings.show', $listing) }}">&larr; Back to listing</a>

        <div class="card" style="margin-top: 1rem;">
            <h1>Request to book</h1>

            <div style="display: flex; gap: 1rem; align-items: center; padding: 1rem; background: var(--color-bg-alt); border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
                <div style="width: 72px; height: 72px; border-radius: var(--radius-sm); overflow: hidden; flex-shrink: 0;">
                    <x-photo :url="$listing->coverPhotoUrl()" :type="$listing->property->property_type" :seed="$listing->id" :alt="$listing->title" />
                </div>
                <div>
                    <strong>{{ $listing->title }}</strong>
                    <p style="margin: 0.15rem 0 0; font-size: 0.88rem;">{{ $listing->property->city }}, {{ $listing->property->country }}</p>
                </div>
            </div>

            @if ($errors->any())
                <x-alert type="danger">{{ $errors->first() }}</x-alert>
            @endif

            <form method="POST" action="{{ route('bookings.store') }}" id="booking-form">
                @csrf
                <input type="hidden" name="listing_id" value="{{ $listing->id }}">

                <div class="form-row">
                    <x-input name="check_in" type="date" label="Check-in" required value="{{ old('check_in') }}" data-price-per-night="{{ $listing->price()->toDecimal() }}" />
                    <x-input name="check_out" type="date" label="Check-out" required value="{{ old('check_out') }}" />
                </div>

                <x-input name="guests_count" type="number" label="Guests" required min="1" max="{{ $listing->max_guests }}" value="{{ old('guests_count', 1) }}" />
                <x-input name="referral_code" label="Referral code" hint="Optional &mdash; got a code from an agent?" value="{{ old('referral_code', session('referral_code')) }}" />

                <div id="booking-estimate-box" style="display: none; border: 1px dashed var(--color-border-strong); border-radius: var(--radius-sm); padding: 0.9rem 1rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: var(--color-text-muted);">
                        <span id="booking-estimate-detail"></span>
                        <strong id="booking-estimate-total" style="color: var(--color-navy-950);"></strong>
                    </div>
                    @if ($listing->property->service_charge_percent > 0 || $listing->property->tax_percent > 0)
                        <p class="form-hint" style="margin: 0.4rem 0 0;">Excludes service charge{{ $listing->property->tax_percent > 0 ? ' and tax' : '' }} &mdash; shown on the next step.</p>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary btn-block">Request to book</button>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    var pricePerNight = {{ $listing->price()->toDecimal() }};
    var currency = @json($listing->currency);

    function updateEstimate() {
        var checkIn = new Date($('#check_in').val());
        var checkOut = new Date($('#check_out').val());
        var nights = Math.round((checkOut - checkIn) / (1000 * 60 * 60 * 24));

        if (nights > 0) {
            $('#booking-estimate-detail').text(nights + ' night(s) x ' + pricePerNight.toFixed(2));
            $('#booking-estimate-total').text((nights * pricePerNight).toFixed(2) + ' ' + currency);
            $('#booking-estimate-box').show();
        } else {
            $('#booking-estimate-box').hide();
        }
    }

    $('#check_in, #check_out').on('change', updateEstimate);
});
</script>
@endsection

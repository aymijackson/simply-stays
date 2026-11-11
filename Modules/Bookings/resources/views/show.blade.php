@extends(auth()->user()->portalLayout())

@section('title', 'Booking details')

@section('content')
<div class="page page--tight">
    <div class="container" style="max-width: 640px;">
        <a href="{{ route('bookings.index') }}">&larr; Back to bookings</a>

        <div class="card" style="margin-top: 1rem;">
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                <x-status-badge :status="$booking->status" />
                <x-status-badge :status="$booking->isPaid() ? 'paid' : 'pending'" />
            </div>

            <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem;">
                <div style="width: 80px; height: 80px; border-radius: var(--radius-sm); overflow: hidden; flex-shrink: 0;">
                    <x-photo :url="$booking->listing->coverPhotoUrl()" :type="$booking->listing->property->property_type" :seed="$booking->listing_id" :alt="$booking->listing->title" />
                </div>
                <div>
                    <h1 style="margin-bottom: 0.15rem;">{{ $booking->listing->title }}</h1>
                    <p style="margin: 0;"><x-icon name="map-pin" style="width:14px;height:14px;vertical-align:-2px;" /> {{ $booking->listing->property->address }}, {{ $booking->listing->property->city }}, {{ $booking->listing->property->country }}</p>
                </div>
            </div>

            <div class="form-row" style="background: var(--color-bg-alt); border-radius: var(--radius-sm); padding: 1rem;">
                <div><span class="form-label" style="margin-bottom: 0.2rem;">Check-in</span>{{ $booking->check_in->format('D, M j, Y') }}</div>
                <div><span class="form-label" style="margin-bottom: 0.2rem;">Check-out</span>{{ $booking->check_out->format('D, M j, Y') }}</div>
            </div>

            <p style="margin-top: 1.25rem; margin-bottom: 0.25rem;">{{ $booking->nights() }} night(s) &middot; {{ $booking->guests_count }} guest(s)</p>

            @php $currency = \Modules\Core\Enums\Currency::from($booking->currency); @endphp
            @if ($booking->price_breakdown)
                <div style="background: var(--color-bg-alt); border-radius: var(--radius-sm); padding: 0.9rem 1rem; margin-top: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.88rem;"><span>Subtotal</span><span>{{ \Modules\Core\Support\Money::fromMinorUnits($booking->price_breakdown['subtotal'], $currency)->format() }}</span></div>
                    @if (($booking->price_breakdown['service_charge'] ?? 0) > 0)
                        <div style="display: flex; justify-content: space-between; font-size: 0.88rem;"><span>Service charge</span><span>{{ \Modules\Core\Support\Money::fromMinorUnits($booking->price_breakdown['service_charge'], $currency)->format() }}</span></div>
                    @endif
                    @if (($booking->price_breakdown['tax'] ?? 0) > 0)
                        <div style="display: flex; justify-content: space-between; font-size: 0.88rem;"><span>Tax</span><span>{{ \Modules\Core\Support\Money::fromMinorUnits($booking->price_breakdown['tax'], $currency)->format() }}</span></div>
                    @endif
                </div>
            @endif
            <p style="font-size: 1.15rem; margin-top: 0.75rem;"><strong style="color: var(--color-navy-950);">Total: {{ $booking->total()->format() }}</strong></p>

            <hr>

            <p style="display: flex; align-items: center; gap: 0.5rem;"><span class="avatar" style="width:26px;height:26px;font-size:0.75rem;">{{ strtoupper(substr($booking->guest->name, 0, 1)) }}</span> Guest: {{ $booking->guest->name }}</p>
            <p style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0;"><span class="avatar" style="width:26px;height:26px;font-size:0.75rem;">{{ strtoupper(substr($booking->listing->property->hostDisplayName(), 0, 1)) }}</span> Host: {{ $booking->listing->property->hostDisplayName() }}</p>

            <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem; flex-wrap: wrap;">
                @if (! $booking->isPaid() && $booking->guest_id === auth()->id() && in_array($booking->status, ['pending', 'confirmed']))
                    <a href="{{ route('checkout.choose', $booking) }}" class="btn btn-primary">Pay now</a>
                @endif

                @if ($booking->guest_id === auth()->id() || $booking->listing->property->host_id === auth()->id() || auth()->user()->hasRole('Admin'))
                    @if ($registration = \Modules\Bookings\Models\GuestRegistration::where('booking_id', $booking->id)->latest()->first())
                        <a href="{{ route('guest-forms.show', $registration) }}" class="btn btn-outline">Registration form</a>
                    @else
                        <a href="{{ route('guest-forms.create', ['booking' => $booking->id]) }}" class="btn btn-outline">Fill registration form</a>
                    @endif
                @endif

                @if ($canReview)
                    <a href="{{ route('bookings.review.create', $booking) }}" class="btn btn-primary">Leave a review</a>
                @endif

                @if ($booking->status === 'pending' && $booking->listing->property->host_id === auth()->id())
                    <form method="POST" action="{{ route('bookings.confirm', $booking) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-primary">Confirm booking</button>
                    </form>
                @endif

                @if (in_array($booking->status, ['pending', 'confirmed']))
                    <form method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking?');">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-danger-outline">Cancel booking</button>
                    </form>
                @endif
            </div>
        </div>

        @if (isset($complimentaryRules) && $complimentaryRules->isNotEmpty())
            @foreach ($complimentaryRules as $rule)
                <div class="card" style="margin-top: 1.5rem;">
                    <h2 style="display:flex; align-items:center; gap:0.5rem;"><x-icon name="gift" style="color: var(--color-accent-dark);" /> {{ $rule->label }}</h2>
                    <p class="form-hint">Complimentary for stays of {{ $rule->min_guests }}+ guests &mdash; choose up to {{ $rule->allowance($booking->guests_count) }} item(s) from the menu below, on us.</p>

                    <form method="POST" action="{{ route('bookings.complimentary.claim', [$booking, $rule]) }}">
                        @csrf
                        <div class="entity-grid">
                            @foreach ($rule->menuItems as $item)
                                <label class="radio-card" style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>{{ $item->name }} <span class="form-hint">({{ $item->price()->format() }} value)</span></span>
                                    <input type="number" name="items[{{ $item->id }}]" min="0" max="{{ $rule->allowance($booking->guests_count) }}" value="0" class="form-control" style="width: 70px;">
                                </label>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Claim complimentary items</button>
                    </form>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection

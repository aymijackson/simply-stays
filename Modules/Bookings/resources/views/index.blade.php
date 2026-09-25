@extends(auth()->user()->portalLayout())

@section('title', 'Bookings')

@section('content')
<div class="page page--tight">
    <div class="container">
        <h1>Bookings I've made</h1>

        @if ($asGuest->isEmpty())
            <div class="card text-center" style="padding: 3rem; margin-bottom: 2.5rem;">
                <p style="margin-bottom: 1rem;">You haven't booked a stay yet.</p>
                <a href="{{ route('listings.index') }}" class="btn btn-primary">Browse listings</a>
            </div>
        @else
            <div class="entity-grid" style="margin-bottom: 2.5rem;">
                @foreach ($asGuest as $booking)
                    <a href="{{ route('bookings.show', $booking) }}" class="entity-card">
                        <div class="entity-card__media">
                            <x-photo :url="$booking->listing->coverPhotoUrl()" :type="$booking->listing->property->property_type" :seed="$booking->listing_id" :alt="$booking->listing->title" />
                            <span class="entity-card__badge"><x-status-badge :status="$booking->status" /></span>
                        </div>
                        <div class="entity-card__body">
                            <div class="entity-card__title">{{ $booking->listing->title }}</div>
                            <div class="entity-card__meta">{{ $booking->check_in->format('M j') }} &ndash; {{ $booking->check_out->format('M j, Y') }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($asHost->isNotEmpty())
            <h1>Bookings on my properties</h1>
            <div class="entity-grid">
                @foreach ($asHost as $booking)
                    <a href="{{ route('bookings.show', $booking) }}" class="entity-card">
                        <div class="entity-card__media">
                            <x-photo :url="$booking->listing->coverPhotoUrl()" :type="$booking->listing->property->property_type" :seed="$booking->listing_id" :alt="$booking->listing->title" />
                            <span class="entity-card__badge"><x-status-badge :status="$booking->status" /></span>
                        </div>
                        <div class="entity-card__body">
                            <div class="entity-card__title">{{ $booking->listing->title }}</div>
                            <div class="entity-card__meta">{{ $booking->guest->name }} &middot; {{ $booking->check_in->format('M j') }} &ndash; {{ $booking->check_out->format('M j, Y') }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Find your next stay')

@section('content')
<section class="hero">
    <div class="container">
        <div class="hero__content">
            <h1>Stays, transport, and laundry &mdash; all in one place</h1>
            <p>Book a place to stay, then add the services that make it easy: airport pickups, laundry, and more.</p>
        </div>

        <form method="GET" action="{{ route('listings.index') }}" class="search-widget">
            <div class="search-widget__row">
                <div class="search-widget__field">
                    <label for="home-city">Where to</label>
                    <input type="text" id="home-city" name="city" placeholder="City or neighborhood" value="{{ request('city') }}">
                </div>
                <div class="search-widget__field">
                    <label for="home-guests">Guests</label>
                    <input type="number" id="home-guests" name="guests" min="1" placeholder="Add guests" value="{{ request('guests') }}">
                </div>
                <div class="search-widget__submit">
                    <button type="submit" aria-label="Search">
                        <x-icon name="search" />
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>

<div class="container">
    <section class="section" style="padding-top: 2.5rem;">
        <div class="section-head">
            <div>
                <h2>Everything for the trip, in one booking</h2>
                <p>Add auxiliary services to any stay.</p>
            </div>
        </div>

        <div class="tile-grid">
            <a href="{{ route('listings.index') }}" class="tile">
                <span class="tile__icon"><x-icon name="bed" /></span>
                <span class="tile__label">Stays</span>
            </a>
            @foreach ($categories as $category)
                @php
                    $tileIcon = match ($category->fulfillment_type) {
                        'laundry' => 'washing-machine',
                        'logistics' => 'truck',
                        default => 'sparkles',
                    };
                @endphp
                <a href="{{ route('services.index', ['category' => $category->id]) }}" class="tile">
                    <span class="tile__icon"><x-icon :name="$tileIcon" /></span>
                    <span class="tile__label">{{ $category->name }}</span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($featuredListings->isNotEmpty())
        <section class="section">
            <div class="section-head">
                <div>
                    <h2>Popular stays</h2>
                    <p>Freshly listed places ready to book.</p>
                </div>
                <a href="{{ route('listings.index') }}" class="btn btn-outline btn-sm">See all stays</a>
            </div>

            <div class="entity-grid">
                @foreach ($featuredListings as $listing)
                    <a href="{{ route('listings.show', $listing) }}" class="entity-card">
                        <div class="entity-card__media">
                            <x-photo :url="$listing->coverPhotoUrl()" :type="$listing->property->property_type" :seed="$listing->id" :alt="$listing->title" />
                        </div>
                        <div class="entity-card__body">
                            <div class="entity-card__title">{{ $listing->title }}</div>
                            <div class="entity-card__meta">{{ $listing->property->city }}, {{ $listing->property->country }}</div>
                            @if ($listing->reviews_avg_rating)
                                <div class="rating"><x-icon name="star" /> {{ round($listing->reviews_avg_rating, 1) }} <span class="text-muted" style="font-weight:400;">({{ $listing->reviews_count }})</span></div>
                            @endif
                            <div class="entity-card__price">{{ $listing->price()->format() }} <span>/ night</span></div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($featuredServices->isNotEmpty())
        <section class="section">
            <div class="section-head">
                <div>
                    <h2>Popular services</h2>
                    <p>Book alongside your stay or on their own.</p>
                </div>
                <a href="{{ route('services.index') }}" class="btn btn-outline btn-sm">See all services</a>
            </div>

            <div class="entity-grid">
                @foreach ($featuredServices as $service)
                    <a href="{{ route('services.show', $service) }}" class="entity-card">
                        <div class="entity-card__media">
                            <x-photo :type="$service->category->fulfillment_type" :seed="$service->id" :alt="$service->title" />
                            <span class="entity-card__badge"><span class="badge badge--dark">{{ $service->category->name }}</span></span>
                        </div>
                        <div class="entity-card__body">
                            <div class="entity-card__title">{{ $service->title }}</div>
                            @if ($service->reviews_avg_rating)
                                <div class="rating"><x-icon name="star" /> {{ round($service->reviews_avg_rating, 1) }} <span class="text-muted" style="font-weight:400;">({{ $service->reviews_count }})</span></div>
                            @endif
                            <div class="entity-card__price">{{ $service->price()->format() }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="trust-strip">
        <div class="trust-item">
            <span class="trust-item__icon"><x-icon name="shield-check" /></span>
            <div>
                <h3>Secure payments</h3>
                <p>Pay with Stripe, Paystack, or Flutterwave &mdash; whichever works where you are.</p>
            </div>
        </div>
        <div class="trust-item">
            <span class="trust-item__icon"><x-icon name="headset" /></span>
            <div>
                <h3>Support when you need it</h3>
                <p>Hosts and providers confirm bookings directly, with your details on hand.</p>
            </div>
        </div>
        <div class="trust-item">
            <span class="trust-item__icon"><x-icon name="globe" /></span>
            <div>
                <h3>Built for travel</h3>
                <p>Multiple currencies and payment methods, so bookings work wherever you land.</p>
            </div>
        </div>
    </section>

    <section class="section" style="padding-bottom: 1rem;">
        <div class="card" style="background: linear-gradient(135deg, var(--color-navy-950), var(--color-action)); border: none; color: #fff; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <h2 style="color: #fff;">Have a property or a service to offer?</h2>
                <p style="color: rgba(255,255,255,0.82); margin: 0;">List a stay, offer transport or laundry, or become a referral agent &mdash; all from one account.</p>
            </div>
            <a href="{{ route('register') }}" class="btn btn-primary" style="background: #fff; color: var(--color-navy-950);">Get started</a>
        </div>
    </section>
</div>
@endsection

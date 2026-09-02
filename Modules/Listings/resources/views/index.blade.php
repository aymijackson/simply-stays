@extends('layouts.app')

@section('title', 'Find a stay')

@section('content')
<div class="page page--tight">
    <div class="container">
        <h1>Find your next stay</h1>

        <form method="GET" action="{{ route('listings.index') }}" class="search-widget search-widget--compact" style="margin-bottom: 2rem;">
            <div class="search-widget__row">
                <div class="search-widget__field">
                    <label for="f-city"><x-icon name="map-pin" style="width:14px;height:14px;vertical-align:-2px;" /> City</label>
                    <input type="text" id="f-city" name="city" placeholder="Any city" value="{{ request('city') }}">
                </div>
                <div class="search-widget__field">
                    <label for="f-guests"><x-icon name="users" style="width:14px;height:14px;vertical-align:-2px;" /> Guests</label>
                    <input type="number" id="f-guests" name="guests" min="1" placeholder="Any" value="{{ request('guests') }}">
                </div>
                <div class="search-widget__field">
                    <label for="f-min">Min price</label>
                    <input type="number" id="f-min" name="min_price" placeholder="No min" value="{{ request('min_price') }}">
                </div>
                <div class="search-widget__field">
                    <label for="f-max">Max price</label>
                    <input type="number" id="f-max" name="max_price" placeholder="No max" value="{{ request('max_price') }}">
                </div>
                <div class="search-widget__submit">
                    <button type="submit" aria-label="Search"><x-icon name="search" /></button>
                </div>
            </div>
        </form>

        @if (request()->hasAny(['city', 'guests', 'min_price', 'max_price']))
            <div style="margin: -1.25rem 0 1.5rem;">
                <a href="{{ route('listings.index') }}" class="btn btn-ghost btn-sm">&larr; Clear filters</a>
            </div>
        @endif

        @if ($listings->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin: 0;">No stays match your search yet. Try adjusting your filters.</p>
            </div>
        @else
            <p class="text-muted">{{ $listings->total() }} {{ \Illuminate\Support\Str::plural('stay', $listings->total()) }} found</p>

            <div class="entity-grid">
                @foreach ($listings as $listing)
                    <a href="{{ route('listings.show', $listing) }}" class="entity-card">
                        <div class="entity-card__media">
                            <x-photo :url="$listing->coverPhotoUrl()" :type="$listing->property->property_type" :seed="$listing->id" :alt="$listing->title" />
                            <span class="entity-card__badge"><span class="badge badge--dark">{{ ucfirst($listing->property->property_type) }}</span></span>
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

            <div style="margin-top: 2.5rem;">
                {{ $listings->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

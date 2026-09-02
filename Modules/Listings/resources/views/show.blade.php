@extends('layouts.app')

@section('title', $listing->title)

@php
    $property = $listing->property;
    $amenityIcon = function (string $name): string {
        $name = strtolower($name);
        return match (true) {
            str_contains($name, 'wifi') => 'wifi',
            str_contains($name, 'air') => 'snowflake',
            str_contains($name, 'kitchen') => 'utensils',
            str_contains($name, 'park') => 'parking',
            str_contains($name, 'wash') => 'washing-machine',
            str_contains($name, 'tv') => 'tv',
            str_contains($name, 'pool') => 'waves',
            str_contains($name, 'breakfast') => 'coffee',
            str_contains($name, 'work') => 'briefcase',
            str_contains($name, 'pet') => 'paw',
            default => 'check',
        };
    };
@endphp

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('listings.index') }}">&larr; Back to search</a>

        <h1 style="margin-top: 1rem; margin-bottom: 0.35rem;">{{ $listing->title }}</h1>
        <p class="text-muted" style="margin: 0 0 0.35rem;">at {{ $property->name }}</p>
        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <span class="text-muted"><x-icon name="map-pin" style="width:15px;height:15px;vertical-align:-3px;" /> {{ $property->address }}, {{ $property->city }}, {{ $property->country }}</span>
            @if ($listing->averageRating())
                <span class="rating"><x-icon name="star" /> {{ $listing->averageRating() }} <span class="text-muted" style="font-weight:400;">({{ $listing->reviewsCount() }} {{ \Illuminate\Support\Str::plural('review', $listing->reviewsCount()) }})</span></span>
            @endif
        </div>

        @php $photos = $listing->photoUrls()->isNotEmpty() ? $listing->photoUrls() : $property->photoUrls(); @endphp
        <x-gallery :images="$photos" :video="$listing->video_url" :alt="$listing->title" />

        <div class="detail-layout">
            <div>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <h2>About this place</h2>
                    <p>{{ $listing->description ?: 'No description provided yet.' }}</p>

                    <div class="form-row" style="margin-top: 1.25rem; flex-wrap: wrap; gap: 1.5rem;">
                        <div style="display:flex; align-items:center; gap:0.5rem;"><x-icon name="users" style="color: var(--color-primary);" /> <strong>{{ $listing->max_guests }}</strong>&nbsp;guests</div>
                        <div style="display:flex; align-items:center; gap:0.5rem;"><x-icon name="bed" style="color: var(--color-primary);" /> <strong>{{ $listing->bedrooms }}</strong>&nbsp;bedrooms</div>
                        <div style="display:flex; align-items:center; gap:0.5rem;"><x-icon name="door" style="color: var(--color-primary);" /> <strong>{{ $listing->bathrooms }}</strong>&nbsp;bathrooms</div>
                        <div style="display:flex; align-items:center; gap:0.5rem;"><x-icon name="home" style="color: var(--color-primary);" /> {{ ucfirst($property->property_type) }}</div>
                    </div>
                </div>

                @if ($listing->amenities->isNotEmpty())
                    <div class="card" style="margin-bottom: 1.5rem;">
                        <h2>Amenities</h2>
                        <div class="amenity-grid">
                            @foreach ($listing->amenities as $amenity)
                                <div class="amenity-item">
                                    <x-icon :name="$amenityIcon($amenity->name)" />
                                    {{ $amenity->name }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($siblingListings->isNotEmpty())
                    <div class="card" style="margin-bottom: 1.5rem;">
                        <h2>More unit types at {{ $property->name }}</h2>
                        <div class="entity-grid">
                            @foreach ($siblingListings as $sibling)
                                <a href="{{ route('listings.show', $sibling) }}" class="entity-card">
                                    <div class="entity-card__media">
                                        <x-photo :url="$sibling->coverPhotoUrl()" :type="$property->property_type" :seed="$sibling->id" :alt="$sibling->title" />
                                    </div>
                                    <div class="entity-card__body">
                                        <div class="entity-card__title">{{ $sibling->title }}</div>
                                        <div class="entity-card__meta">{{ $sibling->bedrooms }} bed &middot; {{ $sibling->bathrooms }} bath &middot; sleeps {{ $sibling->max_guests }}</div>
                                        <div class="entity-card__price">{{ $sibling->price()->format() }} <span>/ night</span></div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($availableServices->isNotEmpty())
                    <div class="card" style="margin-bottom: 1.5rem;">
                        <h2>Available at this property</h2>
                        <div class="entity-grid">
                            @foreach ($availableServices as $service)
                                <a href="{{ route('services.show', $service) }}" class="entity-card">
                                    <div class="entity-card__media">
                                        <x-photo :type="$service->category->fulfillment_type" :seed="$service->id" :alt="$service->title" />
                                        <span class="entity-card__badge"><span class="badge badge--dark">{{ $service->category->name }}</span></span>
                                    </div>
                                    <div class="entity-card__body">
                                        <div class="entity-card__title">{{ $service->title }}</div>
                                        <div class="entity-card__price">{{ $service->price()->format() }}</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="card" style="margin-bottom: 1.5rem;">
                    <h2>Location</h2>
                    <x-map :latitude="$property->latitude" :longitude="$property->longitude" :label="$property->name" />
                </div>

                <div class="card">
                    <h2>Reviews</h2>
                    @if ($listing->reviews->isEmpty())
                        <p>No reviews yet.</p>
                    @else
                        @foreach ($listing->reviews as $review)
                            <div class="review-card">
                                <div class="review-card__head">
                                    <span class="avatar">{{ strtoupper(substr($review->reviewer->name, 0, 1)) }}</span>
                                    <div>
                                        <strong>{{ $review->reviewer->name }}</strong>
                                        <div class="rating" style="font-size: 0.8rem;"><x-icon name="star" style="width:12px;height:12px;" /> {{ $review->rating }}</div>
                                    </div>
                                </div>
                                <p style="margin: 0;">{{ $review->comment }}</p>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="card summary-card">
                <h2>{{ $listing->price()->format() }} <span style="font-size: 0.9rem; color: var(--color-text-muted); font-weight: 400;">/ night</span></h2>
                <p style="display: flex; align-items: center; gap: 0.5rem;">
                    <span class="avatar" style="width:28px;height:28px;font-size:0.8rem;">{{ strtoupper(substr($property->host->name, 0, 1)) }}</span>
                    Hosted by {{ $property->host->name }}
                </p>
                @auth
                    <a href="{{ route('bookings.create', $listing) }}" class="btn btn-primary btn-block">Request to book</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-block">Log in to book</a>
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection

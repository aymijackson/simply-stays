@extends('layouts.app')

@section('title', $service->title)

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('services.index') }}">&larr; Back to services</a>

        <div class="gallery-grid" style="height: 280px; margin-top: 1rem;">
            <div class="gallery-grid__main">
                <x-photo :type="$service->category->fulfillment_type" :seed="$service->id" :alt="$service->title" />
            </div>
            <div class="gallery-grid__cell"><x-photo :type="$service->category->fulfillment_type" :seed="$service->id + 1" :alt="$service->title" /></div>
            <div class="gallery-grid__cell"><x-photo :type="$service->category->fulfillment_type" :seed="$service->id + 2" :alt="$service->title" /></div>
        </div>

        <span class="badge" style="margin-bottom: 0.5rem; display: inline-block;">{{ $service->category->name }}</span>
        @if ($service->audience === 'guests_only')
            <span class="badge badge--gold" style="margin-bottom: 0.5rem; display: inline-block;">Guests with a booking only</span>
        @elseif ($service->audience === 'non_guests_only')
            <span class="badge badge--dark" style="margin-bottom: 0.5rem; display: inline-block;">Non-guests only</span>
        @endif
        <h1 style="margin-bottom: 0.35rem;">{{ $service->title }}</h1>
        @if ($service->averageRating())
            <div class="rating" style="margin-bottom: 1.5rem;"><x-icon name="star" /> {{ $service->averageRating() }} <span class="text-muted" style="font-weight:400;">({{ $service->reviewsCount() }} {{ \Illuminate\Support\Str::plural('review', $service->reviewsCount()) }})</span></div>
        @endif

        <div class="detail-layout">
            <div>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <h2>About this service</h2>
                    <p>{{ $service->description ?: 'No description provided yet.' }}</p>
                    <p style="display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                        <span class="avatar" style="width:28px;height:28px;font-size:0.8rem;">{{ strtoupper(substr($service->provider->name, 0, 1)) }}</span>
                        Offered by {{ $service->provider->name }}
                    </p>
                </div>

                @if ($service->category->fulfillment_type === 'food' && $service->menuItems->isNotEmpty())
                    <div class="card" style="margin-bottom: 1.5rem;">
                        <h2>Menu</h2>
                        <div class="entity-grid">
                            @foreach ($service->menuItems->where('is_available', true) as $item)
                                <a href="{{ route('services.menu-item', [$service, $item]) }}" class="card card--flush entity-card">
                                    <div class="entity-card__media" style="aspect-ratio: 4/3;">
                                        <x-photo :url="$item->coverPhotoUrl()" type="other" :seed="$item->id" :alt="$item->name" />
                                        @if (! empty($item->allergens))
                                            <span class="entity-card__badge"><span class="badge badge--danger">Allergens</span></span>
                                        @endif
                                    </div>
                                    <div style="padding: 0.85rem 1rem 1rem;">
                                        <div class="entity-card__title">{{ $item->name }}</div>
                                        <div class="entity-card__meta">{{ $item->category ?? 'Menu item' }}</div>
                                        <div class="entity-card__price">{{ $item->price()->format() }}</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="card">
                    <h2>Reviews</h2>
                    @if ($service->reviews->isEmpty())
                        <p>No reviews yet.</p>
                    @else
                        @foreach ($service->reviews as $review)
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
                <h2>{{ $service->category->fulfillment_type === 'food' ? 'See menu' : $service->price()->format() }}</h2>
                @if ($service->delivery_fee_amount > 0 || $service->free_delivery_over_amount)
                    <p class="form-hint" style="margin-top: -0.5rem;">
                        Delivery: {{ $service->delivery_fee_amount > 0 ? \Modules\Core\Support\Money::fromMinorUnits($service->delivery_fee_amount, \Modules\Core\Enums\Currency::from($service->currency))->format() : 'Free' }}
                        @if ($service->free_delivery_over_amount)
                            &middot; free over {{ \Modules\Core\Support\Money::fromMinorUnits($service->free_delivery_over_amount, \Modules\Core\Enums\Currency::from($service->currency))->format() }}
                        @endif
                    </p>
                @endif
                @auth
                    <a href="{{ route('service-orders.create', $service) }}" class="btn btn-primary btn-block">Order this service</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-block">Log in to order</a>
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection

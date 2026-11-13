@extends('layouts.app')

@section('title', 'Services')

@section('content')
<div class="page page--tight">
    <div class="container">
        <h1>Auxiliary services</h1>
        <p>Laundry, logistics, and other extras hosts and providers offer alongside your stay.</p>

        <div class="tile-grid" style="margin-bottom: 2rem;">
            <a href="{{ route('services.index') }}" class="tile" style="{{ request('category') ? '' : 'border-color: var(--color-action); box-shadow: var(--shadow-sm);' }}">
                <span class="tile__icon"><x-icon name="sparkles" /></span>
                <span class="tile__label">All</span>
            </a>
            @foreach ($categories as $category)
                @php
                    $tileIcon = match ($category->fulfillment_type) {
                        'laundry' => 'washing-machine',
                        'logistics' => 'truck',
                        default => 'sparkles',
                    };
                    $isActive = (string) request('category') === $category->slug;
                @endphp
                <a href="{{ route('services.index', ['category' => $category]) }}" class="tile" style="{{ $isActive ? 'border-color: var(--color-action); box-shadow: var(--shadow-sm);' : '' }}">
                    <span class="tile__icon"><x-icon :name="$tileIcon" /></span>
                    <span class="tile__label">{{ $category->name }}</span>
                </a>
            @endforeach
        </div>

        @if ($services->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin: 0;">No services match yet.</p>
            </div>
        @else
            <p class="text-muted">{{ $services->total() }} {{ \Illuminate\Support\Str::plural('service', $services->total()) }} found</p>

            <div class="entity-grid">
                @foreach ($services as $service)
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

            <div style="margin-top: 2.5rem;">
                {{ $services->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

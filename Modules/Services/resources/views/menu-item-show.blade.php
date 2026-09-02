@extends('layouts.app')

@section('title', $menuItem->name)

@section('content')
<div class="page page--tight">
    <div class="container" style="max-width: 720px;">
        <a href="{{ route('services.show', $service) }}">&larr; Back to {{ $service->title }}</a>

        <div class="card" style="margin-top: 1rem;">
            <div style="border-radius: var(--radius-lg); overflow: hidden; aspect-ratio: 16/9; margin-bottom: 1.25rem;">
                <x-photo :url="$menuItem->coverPhotoUrl()" type="other" :seed="$menuItem->id" :alt="$menuItem->name" />
            </div>

            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                <div>
                    <h1 style="margin-bottom: 0.15rem;">{{ $menuItem->name }}</h1>
                    @if ($menuItem->category)
                        <span class="badge">{{ $menuItem->category }}</span>
                    @endif
                </div>
                <strong style="color: var(--color-navy-950); font-size: 1.2rem;">{{ $menuItem->price()->format() }}</strong>
            </div>

            @if ($menuItem->description)
                <p style="margin-top: 1rem;">{{ $menuItem->description }}</p>
            @endif

            @if ($menuItem->ingredients)
                <h3 style="margin-bottom: 0.35rem;">Ingredients</h3>
                <p>{{ $menuItem->ingredients }}</p>
            @endif

            @if (! empty($menuItem->allergens))
                <h3 style="margin-bottom: 0.5rem;">Allergen advice</h3>
                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 1rem;">
                    @foreach ($menuItem->allergens as $allergen)
                        <span class="badge badge--danger">{{ $allergen }}</span>
                    @endforeach
                </div>
            @endif

            @if ($menuItem->optionGroups->isNotEmpty())
                <h3 style="margin-bottom: 0.5rem;">Customize it</h3>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.25rem;">
                    @foreach ($menuItem->optionGroups as $group)
                        <div>
                            <strong>{{ $group->name }}</strong>
                            <span class="form-hint">({{ $group->isSingle() ? 'choose one' : 'add extras' }}{{ $group->is_required ? ', required' : '' }})</span>
                            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.3rem;">
                                @foreach ($group->options as $option)
                                    <span class="badge">{{ $option->name }}@if ($option->price_delta_amount > 0) (+{{ $option->priceDelta($menuItem->currency)->format() }})@endif</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @auth
                <a href="{{ route('service-orders.create', $service) }}" class="btn btn-primary btn-block">Order this meal</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary btn-block">Log in to order</a>
            @endauth
        </div>
    </div>
</div>
@endsection

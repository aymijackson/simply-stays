@extends('layouts.app')

@section('title', 'My properties')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('host.listings.index') }}">&larr; Back to my listings</a>

        <div style="display: flex; align-items: center; justify-content: space-between; margin: 1rem 0 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <h1 style="margin: 0;">My properties</h1>
            <a href="{{ route('host.properties.create') }}" class="btn btn-primary">
                <x-icon name="home" style="width:16px;height:16px;" /> Add a property
            </a>
        </div>

        @if ($properties->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin-bottom: 1rem;">You haven't added a property yet. A property is the building or compound &mdash; you'll add individual unit types (listings) to it next.</p>
                <a href="{{ route('host.properties.create') }}" class="btn btn-primary">Create your first property</a>
            </div>
        @else
            <div class="entity-grid">
                @foreach ($properties as $property)
                    <div class="card card--flush">
                        <div class="entity-card__media" style="aspect-ratio: 16/9;">
                            <x-photo :url="$property->photoUrls()->first()" :type="$property->property_type" :seed="$property->id" :alt="$property->name" />
                        </div>
                        <div style="padding: 1rem 1.1rem 1.2rem;">
                            <div class="entity-card__title">{{ $property->name }}</div>
                            <div class="entity-card__meta">
                                {{ $property->city }}, {{ $property->country }} &middot; {{ $property->listings_count }} {{ \Illuminate\Support\Str::plural('unit type', $property->listings_count) }}
                                @auth
                                    @if (auth()->user()->hasRole('Admin') && $property->host_id !== auth()->id())
                                        &middot; Host: {{ $property->host?->name }}
                                    @endif
                                @endauth
                            </div>

                            <div style="display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                                <a href="{{ route('host.properties.edit', $property) }}" class="btn btn-outline btn-sm">Edit</a>
                                <a href="{{ route('host.listings.create') }}" class="btn btn-outline btn-sm">Add unit type</a>
                                <form method="POST" action="{{ route('host.properties.destroy', $property) }}" onsubmit="return confirm('Delete this property? It must have no listings first.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger-outline btn-sm">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'My listings')

@section('content')
<div class="page page--tight">
    <div class="container">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <h1 style="margin: 0;">My listings</h1>
            <div style="display: flex; gap: 0.5rem;">
                <a href="{{ route('host.properties.index') }}" class="btn btn-outline">
                    <x-icon name="home" style="width:16px;height:16px;" /> Manage properties
                </a>
                <a href="{{ route('host.listings.create') }}" class="btn btn-primary">
                    <x-icon name="building" style="width:16px;height:16px;" /> Add a listing
                </a>
            </div>
        </div>

        @if ($listings->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin-bottom: 1rem;">You haven't listed a property yet.</p>
                <a href="{{ route('host.listings.create') }}" class="btn btn-primary">Create your first listing</a>
            </div>
        @else
            <div class="entity-grid">
                @foreach ($listings as $listing)
                    <div class="card card--flush">
                        <div class="entity-card__media" style="aspect-ratio: 16/9;">
                            <x-photo :url="$listing->coverPhotoUrl()" :type="$listing->property->property_type" :seed="$listing->id" :alt="$listing->title" />
                            <span class="entity-card__badge"><x-status-badge :status="$listing->status" /></span>
                        </div>
                        <div style="padding: 1rem 1.1rem 1.2rem;">
                            <div class="entity-card__title">{{ $listing->title }}</div>
                            <div class="entity-card__meta">{{ $listing->property->name }} &middot; {{ $listing->property->city }}, {{ $listing->property->country }} &middot; {{ $listing->price()->format() }}/night</div>

                            <div style="display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                                <a href="{{ route('host.listings.edit', $listing) }}" class="btn btn-outline btn-sm">Edit</a>
                                <form method="POST" action="{{ route('host.listings.publish', $listing) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-outline btn-sm">{{ $listing->status === 'published' ? 'Unpublish' : 'Publish' }}</button>
                                </form>
                                <form method="POST" action="{{ route('host.listings.destroy', $listing) }}" onsubmit="return confirm('Delete this listing?');">
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

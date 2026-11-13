@extends(auth()->user()->portalLayout())

@section('title', $isAdmin ? 'All services' : 'My services')

@section('content')
@php $filtered = (bool) array_filter(request()->only(['q', 'status', 'provider'])); @endphp
<div class="page page--tight">
    <div class="container">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="margin: 0;">{{ $isAdmin ? 'All services' : 'My services' }}</h1>
                @if ($isAdmin)<p class="text-muted" style="margin: 0.25rem 0 0;">Every provider's services, including the ones live on the website.</p>@endif
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="{{ route('provider.services.orders') }}" class="btn btn-outline">{{ $isAdmin ? 'All orders' : 'Incoming orders' }}</a>
                <a href="{{ route('provider.services.create') }}" class="btn btn-primary">Add a service</a>
            </div>
        </div>

        <form method="GET" class="ops-filters" role="search">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control ops-filters__search" placeholder="Search services" aria-label="Search services">
            @if ($isAdmin)
                <select name="provider" class="form-control" aria-label="Provider" onchange="this.form.submit()">
                    <option value="">All providers</option>
                    <option value="in_house" @selected(request('provider') === 'in_house')>In-house only</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->getRouteKey() }}" @selected(request('provider') === $provider->getRouteKey())>{{ $provider->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="status" class="form-control" aria-label="Status" onchange="this.form.submit()">
                <option value="">Any status</option>
                <option value="published" @selected(request('status') === 'published')>Published (live)</option>
                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            </select>
            <button class="btn btn-primary">Search</button>
            @if ($filtered)
                <a href="{{ route('provider.services.index') }}" class="ops-filters__clear">Clear filters</a>
            @endif
        </form>

        @if ($services->isEmpty() && $filtered)
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin: 0;">No services match these filters.</p>
            </div>
        @elseif ($services->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin-bottom: 1rem;">{{ $isAdmin ? 'No services have been listed yet.' : "You haven't listed a service yet." }}</p>
                <a href="{{ route('provider.services.create') }}" class="btn btn-primary">Create your first service</a>
            </div>
        @else
            <div class="entity-grid">
                @foreach ($services as $service)
                    <div class="card card--flush">
                        <div class="entity-card__media" style="aspect-ratio: 16/9;">
                            <x-photo :url="$service->coverPhotoUrl()" :type="$service->category->fulfillment_type ?? 'other'" :seed="$service->id" :alt="$service->title" />
                            <span class="entity-card__badge"><x-status-badge :status="$service->status" /></span>
                        </div>
                        <div style="padding: 1rem 1.1rem 1.2rem;">
                            <div class="entity-card__title">{{ $service->title }}</div>
                            <div class="entity-card__meta">
                                {{ $service->price()->format() }}
                                &middot; {{ $service->properties->isEmpty() ? 'Available everywhere' : $service->properties->pluck('name')->join(', ') }}
                            </div>
                            @if ($isAdmin)
                                <div class="entity-card__meta">
                                    @if ($service->isInHouse())
                                        <span class="badge badge--gold">In-house</span>
                                    @else
                                        Vendor: <strong>{{ $service->provider?->name ?? 'Deleted account' }}</strong>
                                    @endif
                                </div>
                            @endif

                            <div style="display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                                <a href="{{ route('provider.services.edit', $service) }}" class="btn btn-outline btn-sm">Edit</a>
                                @if ($service->isLaundry())
                                    <a href="{{ route('provider.services.price-list.edit', $service) }}" class="btn btn-outline btn-sm">Price list</a>
                                @endif
                                <a href="{{ route('provider.services.qr', $service) }}" class="btn btn-outline btn-sm">QR codes</a>
                                <form method="POST" action="{{ route('provider.services.publish', $service) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-outline btn-sm">{{ $service->status === 'published' ? 'Unpublish' : 'Publish' }}</button>
                                </form>
                                <form method="POST" action="{{ route('provider.services.destroy', $service) }}" onsubmit="return confirm('Delete this service?');">
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

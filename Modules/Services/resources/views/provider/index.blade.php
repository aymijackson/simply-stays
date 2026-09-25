@extends(auth()->user()->portalLayout())

@section('title', 'My services')

@section('content')
<div class="page page--tight">
    <div class="container">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <h1 style="margin: 0;">My services</h1>
            <div style="display: flex; gap: 0.5rem;">
                <a href="{{ route('provider.services.orders') }}" class="btn btn-outline">Incoming orders</a>
                <a href="{{ route('provider.services.create') }}" class="btn btn-primary">Add a service</a>
            </div>
        </div>

        @if ($services->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin-bottom: 1rem;">You haven't listed a service yet.</p>
                <a href="{{ route('provider.services.create') }}" class="btn btn-primary">Create your first service</a>
            </div>
        @else
            <div class="entity-grid">
                @foreach ($services as $service)
                    <div class="card card--flush">
                        <div class="entity-card__media" style="aspect-ratio: 16/9;">
                            <x-photo :type="$service->category->fulfillment_type ?? 'other'" :seed="$service->id" :alt="$service->title" />
                            <span class="entity-card__badge"><x-status-badge :status="$service->status" /></span>
                        </div>
                        <div style="padding: 1rem 1.1rem 1.2rem;">
                            <div class="entity-card__title">{{ $service->title }}</div>
                            <div class="entity-card__meta">
                                {{ $service->price()->format() }}
                                &middot; {{ $service->properties->isEmpty() ? 'Available everywhere' : $service->properties->pluck('name')->join(', ') }}
                            </div>

                            <div style="display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                                <a href="{{ route('provider.services.edit', $service) }}" class="btn btn-outline btn-sm">Edit</a>
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

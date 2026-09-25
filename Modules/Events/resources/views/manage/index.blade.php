@extends(auth()->user()->portalLayout())

@section('title', 'My events')

@section('content')
<div class="page page--tight">
    <div class="container">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <h1 style="margin: 0;">My events</h1>
            <a href="{{ route('events.manage.create') }}" class="btn btn-primary">
                <x-icon name="calendar" style="width:16px;height:16px;" /> Add an event
            </a>
        </div>

        @if (session('status'))
            <x-alert type="success">{{ session('status') }}</x-alert>
        @endif

        @if ($events->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin-bottom: 1rem;">You haven't created an event yet.</p>
                <a href="{{ route('events.manage.create') }}" class="btn btn-primary">Create your first event</a>
            </div>
        @else
            <div class="entity-grid">
                @foreach ($events as $event)
                    <div class="card card--flush">
                        <div class="entity-card__media" style="aspect-ratio: 16/9;">
                            <x-photo :url="$event->coverPhotoUrl()" type="other" :seed="$event->id" :alt="$event->title" />
                            <span class="entity-card__badge"><x-status-badge :status="$event->status" /></span>
                        </div>
                        <div style="padding: 1rem 1.1rem 1.2rem;">
                            <div class="entity-card__title">{{ $event->title }}</div>
                            <div class="entity-card__meta">{{ $event->recurrenceSummary() }} &middot; {{ $event->occurrences->count() }} date(s)</div>
                            @if ($event->status === 'rejected')
                                <p class="form-hint" style="color: var(--color-danger);">{{ $event->rejection_reason }}</p>
                            @endif

                            <div style="display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                                <a href="{{ route('events.manage.edit', $event) }}" class="btn btn-outline btn-sm">Edit</a>
                                @if ($event->status === 'published')
                                    <a href="{{ route('events.show', $event) }}" class="btn btn-outline btn-sm">View live</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

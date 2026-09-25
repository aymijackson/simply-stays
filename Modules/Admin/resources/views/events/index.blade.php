@extends('admin::layouts.admin')

@section('title', 'Manage events')

@section('content')
<div class="page page--tight">
    <div class="container">
        <div style="display: flex; align-items: center; justify-content: space-between; margin: 0 0 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <h1 style="margin: 0;">Manage events</h1>
            <a href="{{ route('admin.events.settings.edit') }}" class="btn btn-outline btn-sm">Event settings</a>
        </div>

        <h2>Pending approval</h2>
        @if ($pendingEvents->isEmpty())
            <div class="card text-center" style="padding: 2rem; margin-bottom: 2rem;">
                <p style="margin: 0;">Nothing waiting for review.</p>
            </div>
        @else
            <div class="entity-grid" style="margin-bottom: 2rem;">
                @foreach ($pendingEvents as $event)
                    <div class="card">
                        <div class="entity-card__title">{{ $event->title }}</div>
                        <div class="entity-card__meta">
                            By {{ $event->creator->name }} &middot; {{ $event->recurrenceSummary() }} &middot; {{ $event->venueLabel() }}
                            @if ($event->is_ticketed) &middot; {{ $event->price()->format() }}/ticket @endif
                        </div>

                        <div style="display: flex; gap: 0.5rem; margin-top: 0.85rem; flex-wrap: wrap;">
                            <form method="POST" action="{{ route('admin.events.approve', $event) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                            </form>

                            <form method="POST" action="{{ route('admin.events.reject', $event) }}" style="display: flex; gap: 0.4rem; align-items: center;">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="rejection_reason" class="form-control" placeholder="Reason for rejection" required style="min-width: 220px;">
                                <button type="submit" class="btn btn-danger-outline btn-sm">Reject</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <h2>Published events</h2>
        @if ($publishedEvents->isEmpty())
            <div class="card text-center" style="padding: 2rem;">
                <p style="margin: 0;">No published events yet.</p>
            </div>
        @else
            <div class="card" style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Organizer</th>
                            <th>Schedule</th>
                            <th>Ticketed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($publishedEvents as $event)
                            <tr>
                                <td><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></td>
                                <td>{{ $event->creator->name }}</td>
                                <td>{{ $event->recurrenceSummary() }}</td>
                                <td>{{ $event->is_ticketed ? $event->price()->format() : 'Free info only' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 2rem;">
                {{ $publishedEvents->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

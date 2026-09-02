@extends('layouts.app')

@section('title', 'Events')

@section('content')
<div class="page page--tight">
    <div class="container">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
            <h1 style="margin: 0;">What's on</h1>
            <a href="{{ route('events.calendar') }}" class="btn btn-outline btn-sm">
                <x-icon name="calendar" style="width:15px;height:15px;" /> Calendar view
            </a>
        </div>

        <form method="GET" action="{{ route('events.index') }}" class="search-widget search-widget--compact" style="margin-bottom: 2rem;">
            <div class="search-widget__row">
                <div class="search-widget__field">
                    <label for="f-city"><x-icon name="map-pin" style="width:14px;height:14px;vertical-align:-2px;" /> City</label>
                    <input type="text" id="f-city" name="city" placeholder="Any city" value="{{ request('city') }}">
                </div>
                <div class="search-widget__submit">
                    <button type="submit" aria-label="Search"><x-icon name="search" /></button>
                </div>
            </div>
        </form>

        @if ($occurrences->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin: 0;">No upcoming events match your search yet.</p>
            </div>
        @else
            <div class="entity-grid">
                @foreach ($occurrences as $occurrence)
                    @php $event = $occurrence->event; @endphp
                    <a href="{{ route('events.show', $event) }}" class="entity-card">
                        <div class="entity-card__media">
                            <x-photo :url="$event->coverPhotoUrl()" type="other" :seed="$event->id" :alt="$event->title" />
                            <span class="entity-card__badge">
                                @if ($event->is_ticketed)
                                    <span class="badge badge--dark">{{ $event->price_amount > 0 ? $event->price()->format() : 'Free ticket' }}</span>
                                @else
                                    <span class="badge badge--gold">Info only</span>
                                @endif
                            </span>
                        </div>
                        <div class="entity-card__body">
                            <div class="entity-card__title">{{ $event->title }}</div>
                            <div class="entity-card__meta">
                                {{ $occurrence->date->format('D, M j') }} &middot; {{ \Illuminate\Support\Str::of($occurrence->start_time)->limit(5, '') }}
                            </div>
                            <div class="entity-card__meta">{{ $event->venueLabel() }}</div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div style="margin-top: 2.5rem;">
                {{ $occurrences->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

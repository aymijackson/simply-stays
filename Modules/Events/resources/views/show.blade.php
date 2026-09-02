@extends('layouts.app')

@section('title', $event->title)

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('events.index') }}">&larr; Back to events</a>

        <h1 style="margin-top: 1rem; margin-bottom: 0.35rem;">{{ $event->title }}</h1>
        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <span class="text-muted"><x-icon name="map-pin" style="width:15px;height:15px;vertical-align:-3px;" /> {{ $event->venueLabel() }}</span>
            <span class="text-muted"><x-icon name="clock" style="width:15px;height:15px;vertical-align:-3px;" /> {{ $event->recurrenceSummary() }}</span>
            @if ($event->category)
                <span class="badge">{{ $event->category }}</span>
            @endif
        </div>

        <x-gallery :images="$event->photoUrls()" :alt="$event->title" />

        <div class="detail-layout">
            <div>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <h2>About this event</h2>
                    <p>{{ $event->description ?: 'No description provided yet.' }}</p>
                    <p style="display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                        <span class="avatar" style="width:28px;height:28px;font-size:0.8rem;">{{ strtoupper(substr($event->creator->name, 0, 1)) }}</span>
                        Hosted by {{ $event->creator->name }}
                    </p>
                </div>

                <div class="card">
                    <h2>Upcoming dates</h2>
                    @if ($occurrences->isEmpty())
                        <p>No upcoming dates scheduled.</p>
                    @else
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            @foreach ($occurrences as $occurrence)
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--color-bg-alt); border-radius: var(--radius-sm); flex-wrap: wrap; gap: 0.75rem;">
                                    <div>
                                        <strong style="color: var(--color-navy-950);">{{ $occurrence->date->format('D, M j, Y') }}</strong>
                                        <div class="form-hint">
                                            {{ \Illuminate\Support\Str::of($occurrence->start_time)->limit(5, '') }}
                                            @if ($occurrence->end_time) &ndash; {{ \Illuminate\Support\Str::of($occurrence->end_time)->limit(5, '') }} @endif
                                        </div>
                                        @if ($occurrence->capacity !== null)
                                            <div class="form-hint">{{ $occurrence->remainingCapacity() }} spot(s) left</div>
                                        @endif
                                    </div>

                                    @auth
                                        @if ($event->is_ticketed)
                                            @if ($occurrence->remainingCapacity() === 0)
                                                <span class="badge badge--danger">Sold out</span>
                                            @else
                                                <form method="POST" action="{{ route('event-bookings.store', $occurrence) }}" style="display: flex; gap: 0.4rem; align-items: center;">
                                                    @csrf
                                                    <input type="number" name="quantity" value="1" min="1" max="{{ $occurrence->capacity !== null ? min(50, $occurrence->remainingCapacity()) : 50 }}" class="form-control" style="width: 64px;">
                                                    <button type="submit" class="btn btn-primary btn-sm">{{ $event->price_amount > 0 ? 'Get tickets' : 'Reserve free' }}</button>
                                                </form>
                                            @endif
                                        @endif
                                    @else
                                        @if ($event->is_ticketed)
                                            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Log in to book</a>
                                        @endif
                                    @endauth
                                </div>
                            @endforeach
                        </div>

                        @unless ($event->is_ticketed)
                            <p class="form-hint" style="margin-top: 1rem;">This is an open, informational event &mdash; no booking required.</p>
                        @endunless
                    @endif
                </div>
            </div>

            <div class="card summary-card">
                @if ($event->is_ticketed)
                    <h2>{{ $event->price_amount > 0 ? $event->price()->format().' / ticket' : 'Free' }}</h2>
                @else
                    <h2>Open to all</h2>
                @endif
                <x-map :latitude="$event->property?->latitude" :longitude="$event->property?->longitude" :label="$event->venueLabel()" />
            </div>
        </div>
    </div>
</div>
@endsection

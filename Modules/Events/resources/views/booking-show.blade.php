@extends('layouts.app')

@section('title', 'Your tickets')

@section('content')
<div class="page page--tight">
    <div class="container" style="max-width: 640px;">
        <a href="{{ route('events.show', $booking->occurrence->event) }}">&larr; Back to event</a>

        <div class="card" style="margin-top: 1rem;">
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                <x-status-badge :status="$booking->status" />
                @if ($booking->total_amount > 0)
                    <x-status-badge :status="$booking->isPaid() ? 'paid' : 'pending'" />
                @endif
            </div>

            <h1 style="margin-bottom: 0.15rem;">{{ $booking->occurrence->event->title }}</h1>
            <p style="margin: 0 0 1.25rem;">
                <x-icon name="calendar" style="width:14px;height:14px;vertical-align:-2px;" />
                {{ $booking->occurrence->date->format('D, M j, Y') }}
                &middot; {{ $booking->quantity }} {{ \Illuminate\Support\Str::plural('ticket', $booking->quantity) }}
            </p>

            <p style="font-size: 1.15rem;"><strong style="color: var(--color-navy-950);">Total: {{ $booking->total()->format() }}</strong></p>

            <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem; flex-wrap: wrap;">
                @if (! $booking->isPaid() && $booking->total_amount > 0 && $booking->guest_id === auth()->id() && $booking->status === 'pending')
                    <a href="{{ route('checkout.choose.event', $booking) }}" class="btn btn-primary">Pay now</a>
                @endif

                @if (in_array($booking->status, ['pending', 'confirmed']))
                    <form method="POST" action="{{ route('event-bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking?');">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-danger-outline">Cancel booking</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

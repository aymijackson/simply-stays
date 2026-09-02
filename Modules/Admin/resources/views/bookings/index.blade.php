@extends('layouts.app')

@section('title', 'Manage bookings')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('admin.index') }}">&larr; Back to overview</a>

        <h1 style="margin-top: 1rem;">Manage bookings</h1>

        @if (session('status'))
            <x-alert type="success">{{ session('status') }}</x-alert>
        @endif

        <div class="card" style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Listing</th>
                        <th>Guest</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bookings as $booking)
                        @php
                            $hasSucceededCharge = $booking->transactions->contains(fn ($t) => $t->type === 'charge' && $t->status === 'succeeded');
                        @endphp
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.6rem;">
                                    <div style="width: 40px; height: 40px; border-radius: var(--radius-sm); overflow: hidden; flex-shrink: 0;">
                                        <x-photo :url="$booking->listing->coverPhotoUrl()" :type="$booking->listing->property->property_type" :seed="$booking->listing_id" :alt="$booking->listing->title" />
                                    </div>
                                    <strong style="color: var(--color-navy-950);">{{ $booking->listing->title }}</strong>
                                </div>
                            </td>
                            <td>{{ $booking->guest->name }}</td>
                            <td style="white-space: nowrap;">{{ $booking->check_in->format('M j') }} &ndash; {{ $booking->check_out->format('M j, Y') }}</td>
                            <td><x-status-badge :status="$booking->status" /></td>
                            <td><x-status-badge :status="$hasSucceededCharge ? 'paid' : 'pending'" /></td>
                            <td>
                                <div style="display: flex; gap: 0.4rem;">
                                    <a href="{{ route('bookings.show', $booking) }}" class="btn btn-outline btn-sm">View</a>
                                    @if ($hasSucceededCharge && $booking->status !== 'cancelled')
                                        <form method="POST" action="{{ route('admin.bookings.refund', $booking) }}" onsubmit="return confirm('Refund this booking? This cancels the booking and reverses host/agent wallet credits.');">
                                            @csrf
                                            <button type="submit" class="btn btn-danger-outline btn-sm">Refund</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 2.5rem;">
            {{ $bookings->links() }}
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Admin overview')

@section('content')
<div class="page page--tight">
    <div class="container">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <h1 style="margin: 0;">Admin overview</h1>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline btn-sm">Users</a>
                <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline btn-sm">Bookings</a>
                <a href="{{ route('reviews.index') }}" class="btn btn-outline btn-sm">Reviews</a>
                <a href="{{ route('admin.events.index') }}" class="btn btn-outline btn-sm">Events</a>
                <a href="{{ route('admin.events.settings.edit') }}" class="btn btn-outline btn-sm">Event settings</a>
                <a href="{{ route('admin.activity.index') }}" class="btn btn-outline btn-sm">Activity log</a>
                <a href="{{ route('admin.settings.edit') }}" class="btn btn-outline btn-sm">Site settings</a>
            </div>
        </div>

        <div class="module-grid" style="margin-bottom: 1.5rem;">
            <div class="card stat-tile">
                <p>Users</p>
                <h2>{{ $usersTotal }}</h2>
                <p class="form-hint">{{ $usersSuspended }} suspended</p>
            </div>
            <div class="card stat-tile">
                <p>Listings</p>
                <h2>{{ $listingsTotal }}</h2>
                <p class="form-hint">{{ $listingsPublished }} published</p>
            </div>
            <div class="card stat-tile">
                <p>Bookings</p>
                <h2>{{ $bookingsTotal }}</h2>
            </div>
            <div class="card stat-tile">
                <p>Services</p>
                <h2>{{ $servicesTotal }}</h2>
                <p class="form-hint">{{ $servicesPublished }} published</p>
            </div>
            <div class="card stat-tile">
                <p>Reviews</p>
                <h2>{{ $reviewsTotal }}</h2>
                <p class="form-hint">{{ $reviewsHidden }} hidden</p>
            </div>
            <div class="card stat-tile">
                <p>Events</p>
                <h2>{{ $eventsPublished }}</h2>
                <p class="form-hint">{{ $eventsPending }} pending approval</p>
            </div>
        </div>

        <div class="module-grid" style="margin-bottom: 1.5rem;">
            <div class="card">
                <h3><x-icon name="users" style="width:16px;height:16px;vertical-align:-3px;color:var(--color-primary);" /> Users by role</h3>
                @forelse ($usersByRole as $role => $count)
                    <p>{{ $role }}: <strong style="color: var(--color-navy-950);">{{ $count }}</strong></p>
                @empty
                    <p>No roles assigned yet.</p>
                @endforelse
            </div>

            <div class="card">
                <h3><x-icon name="calendar" style="width:16px;height:16px;vertical-align:-3px;color:var(--color-primary);" /> Bookings by status</h3>
                @forelse ($bookingsByStatus as $status => $count)
                    <p>{{ ucfirst($status) }}: <strong style="color: var(--color-navy-950);">{{ $count }}</strong></p>
                @empty
                    <p>No bookings yet.</p>
                @endforelse
            </div>

            <div class="card">
                <h3><x-icon name="truck" style="width:16px;height:16px;vertical-align:-3px;color:var(--color-primary);" /> Service orders by status</h3>
                @forelse ($serviceOrdersByStatus as $status => $count)
                    <p>{{ ucfirst(str_replace('_', ' ', $status)) }}: <strong style="color: var(--color-navy-950);">{{ $count }}</strong></p>
                @empty
                    <p>No service orders yet.</p>
                @endforelse
            </div>

            <div class="card">
                <h3><x-icon name="credit-card" style="width:16px;height:16px;vertical-align:-3px;color:var(--color-primary);" /> Revenue (succeeded charges)</h3>
                @forelse ($revenueByCurrency as $currency => $amount)
                    <p style="font-weight: 700; color: var(--color-navy-950);">{{ \Modules\Core\Support\Money::fromMinorUnits((int) $amount, \Modules\Core\Enums\Currency::from($currency))->format() }}</p>
                @empty
                    <p>No revenue yet.</p>
                @endforelse
            </div>

            <div class="card">
                <h3><x-icon name="gift" style="width:16px;height:16px;vertical-align:-3px;color:var(--color-primary);" /> Agent commissions paid</h3>
                @forelse ($commissionByCurrency as $currency => $amount)
                    <p style="font-weight: 700; color: var(--color-navy-950);">{{ \Modules\Core\Support\Money::fromMinorUnits((int) $amount, \Modules\Core\Enums\Currency::from($currency))->format() }}</p>
                @empty
                    <p>No commissions paid yet.</p>
                @endforelse
            </div>

            <div class="card">
                <h3><x-icon name="wallet" style="width:16px;height:16px;vertical-align:-3px;color:var(--color-primary);" /> Platform commission revenue</h3>
                @forelse ($platformRevenueByCurrency as $currency => $amount)
                    <p style="font-weight: 700; color: var(--color-navy-950);">{{ \Modules\Core\Support\Money::fromMinorUnits((int) $amount, \Modules\Core\Enums\Currency::from($currency))->format() }}</p>
                @empty
                    <p>No service commissions collected yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

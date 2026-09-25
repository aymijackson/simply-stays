@extends(auth()->user()->portalLayout())

@section('title', 'Dashboard')

@php
    $links = match ($role) {
        'Host' => [
            ['route' => route('host.properties.index'), 'icon' => 'home', 'label' => 'Manage properties'],
            ['route' => route('host.listings.index'), 'icon' => 'building', 'label' => 'Manage listings'],
            ['route' => route('bookings.index'), 'icon' => 'calendar', 'label' => 'Bookings on my properties'],
            ['route' => route('events.manage.index'), 'icon' => 'calendar', 'label' => 'Manage events'],
            ['route' => route('payments.index'), 'icon' => 'wallet', 'label' => 'Payouts & earnings'],
        ],
        'Agent' => [
            ['route' => route('referrals.index'), 'icon' => 'gift', 'label' => 'My referral codes'],
            ['route' => route('payments.index'), 'icon' => 'wallet', 'label' => 'Commission & payouts'],
        ],
        'ServiceProvider' => [
            ['route' => route('provider.services.index'), 'icon' => 'sparkles', 'label' => 'Manage services'],
            ['route' => route('provider.services.orders'), 'icon' => 'truck', 'label' => 'Incoming orders'],
            ['route' => route('events.manage.index'), 'icon' => 'calendar', 'label' => 'Manage events'],
            ['route' => route('payments.index'), 'icon' => 'wallet', 'label' => 'Earnings'],
        ],
        'Admin' => [
            ['route' => route('admin.index'), 'icon' => 'briefcase', 'label' => 'Admin overview'],
            ['route' => route('admin.users.index'), 'icon' => 'users', 'label' => 'Users'],
            ['route' => route('admin.bookings.index'), 'icon' => 'calendar', 'label' => 'Bookings & refunds'],
            ['route' => route('services.index'), 'icon' => 'sparkles', 'label' => 'Services'],
            ['route' => route('admin.events.index'), 'icon' => 'calendar', 'label' => 'Events & approvals'],
            ['route' => route('referrals.index'), 'icon' => 'gift', 'label' => 'Referrals'],
            ['route' => route('reviews.index'), 'icon' => 'star', 'label' => 'Reviews'],
            ['route' => route('admin.activity.index'), 'icon' => 'clock', 'label' => 'Activity log'],
        ],
        default => [
            ['route' => route('listings.index'), 'icon' => 'bed', 'label' => 'Find a stay'],
            ['route' => route('bookings.index'), 'icon' => 'calendar', 'label' => 'My bookings'],
            ['route' => route('services.index'), 'icon' => 'sparkles', 'label' => 'Book a service'],
            ['route' => route('service-orders.index'), 'icon' => 'truck', 'label' => 'My service orders'],
            ['route' => route('events.index'), 'icon' => 'calendar', 'label' => 'Browse events'],
        ],
    };
@endphp

@section('content')
<div class="page page--tight">
    <div class="container">
        <div class="dashboard-hero">
            <span class="badge">{{ $role }}</span>
            <h1>Welcome back, {{ auth()->user()->name }}</h1>
            <p>Here's a quick jump-off point for what you can do next.</p>
        </div>

        <div class="module-grid">
            @foreach ($links as $link)
                <a href="{{ $link['route'] }}" class="card quick-link">
                    <span class="quick-link__icon"><x-icon :name="$link['icon']" /></span>
                    {{ $link['label'] }}
                    <span class="quick-link__arrow"><x-icon name="arrow-right" /></span>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection

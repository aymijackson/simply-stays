<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $siteSettings->color_theme }}" data-layout="{{ $siteSettings->layout_style }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') &mdash; @endif{{ config('app.name') }} Admin</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    @php
        $portalHomeRoute = 'admin.index';
        $portalNavGroups = [
            'Overview' => [
                ['label' => 'Dashboard', 'route' => 'admin.index', 'pattern' => 'admin.index', 'icon' => 'home'],
            ],
            'Marketplace' => [
                ['label' => 'Properties', 'route' => 'host.properties.index', 'pattern' => 'host.properties.*', 'icon' => 'building'],
                ['label' => 'Listings', 'route' => 'host.listings.index', 'pattern' => 'host.listings.*', 'icon' => 'door'],
                ['label' => 'Services', 'route' => 'provider.services.index', 'pattern' => 'provider.services.*', 'icon' => 'truck'],
            ],
            'Bookings' => [
                ['label' => 'Bookings', 'route' => 'admin.bookings.index', 'pattern' => 'admin.bookings.*', 'icon' => 'calendar'],
                ['label' => 'Guest forms', 'route' => 'guest-forms.index', 'pattern' => 'guest-forms.*', 'icon' => 'clipboard'],
            ],
            'Operations' => [
                ['label' => 'Overview', 'route' => 'ops.dashboard', 'pattern' => 'ops.dashboard', 'icon' => 'briefcase'],
                ['label' => 'Inventory', 'route' => 'ops.inventory.index', 'pattern' => 'ops.inventory.*', 'icon' => 'clipboard'],
                ['label' => 'Shifts', 'route' => 'ops.shifts.index', 'pattern' => 'ops.shifts.*', 'icon' => 'clock'],
                ['label' => 'Staff', 'route' => 'ops.staff.index', 'pattern' => 'ops.staff.*', 'icon' => 'users'],
                ['label' => 'Accounting', 'route' => 'ops.finance.index', 'pattern' => 'ops.finance.*', 'icon' => 'wallet'],
            ],
            'Events' => [
                ['label' => 'Approvals', 'route' => 'admin.events.index', 'pattern' => 'admin.events.index', 'icon' => 'check'],
                ['label' => 'Manage events', 'route' => 'events.manage.index', 'pattern' => 'events.manage.*', 'icon' => 'calendar'],
                ['label' => 'Event settings', 'route' => 'admin.events.settings.edit', 'pattern' => 'admin.events.settings.*', 'icon' => 'settings'],
            ],
            'Community' => [
                ['label' => 'Reviews', 'route' => 'reviews.index', 'pattern' => 'reviews.*', 'icon' => 'star'],
                ['label' => 'Referrals', 'route' => 'referrals.index', 'pattern' => 'referrals.*', 'icon' => 'gift'],
            ],
            'People' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'icon' => 'users'],
            ],
            'Support' => [
                ['label' => 'Support inbox', 'route' => 'admin.support.index', 'pattern' => 'admin.support.*', 'icon' => 'headset'],
            ],
            'System' => [
                ['label' => 'Activity log', 'route' => 'admin.activity.index', 'pattern' => 'admin.activity.*', 'icon' => 'clock'],
                ['label' => 'Site settings', 'route' => 'admin.settings.edit', 'pattern' => 'admin.settings.*', 'icon' => 'settings'],
            ],
        ];
    @endphp

    @include('layouts.partials.portal-shell')

    <script src="{{ asset('vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/axios.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>

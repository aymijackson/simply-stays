<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $siteSettings->color_theme }}" data-layout="{{ $siteSettings->layout_style }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') &mdash; @endif{{ config('app.name') }} Provider</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    @php
        $portalHomeRoute = 'dashboard';
        $portalNavGroups = [
            'Overview' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'home'],
            ],
            'Services' => [
                ['label' => 'My services', 'route' => 'provider.services.index', 'pattern' => 'provider.services.index', 'icon' => 'truck'],
                ['label' => 'Orders', 'route' => 'provider.services.orders', 'pattern' => 'provider.services.orders', 'icon' => 'briefcase'],
            ],
            'Events' => [
                ['label' => 'Manage events', 'route' => 'events.manage.index', 'pattern' => 'events.manage.*', 'icon' => 'calendar'],
            ],
            'Finance' => [
                ['label' => 'Payments & payouts', 'route' => 'payments.index', 'pattern' => 'payments.*', 'icon' => 'wallet'],
            ],
        ];
    @endphp

    @include('layouts.partials.portal-shell')
    @include('layouts.partials.support-widget')
    @include('layouts.partials.order-alerts')

    <script src="{{ asset('vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/axios.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>

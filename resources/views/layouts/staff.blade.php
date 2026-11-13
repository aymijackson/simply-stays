<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $siteSettings->color_theme }}" data-layout="{{ $siteSettings->layout_style }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') &mdash; @endif{{ config('app.name') }} Staff</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    @php
        // Staff can belong to several properties; show the sections their highest access level opens.
        $staffLevels = \Modules\Operations\Models\StaffMember::where('user_id', auth()->id())->active()->pluck('access_level');
        $staffCan = fn (string $level) => $staffLevels->contains(fn ($l) => array_search($l, ['basic', 'inventory', 'manager']) >= array_search($level, ['basic', 'inventory', 'manager']));

        $portalHomeRoute = 'ops.dashboard';
        $portalNavGroups = [
            'My work' => [
                ['label' => 'Overview', 'route' => 'ops.dashboard', 'pattern' => 'ops.dashboard', 'icon' => 'home'],
                ['label' => 'Shift roster', 'route' => 'ops.shifts.index', 'pattern' => 'ops.shifts.*', 'icon' => 'clock'],
            ],
        ];
        if ($staffCan('inventory')) {
            $portalNavGroups['Inventory'] = [
                ['label' => 'Stock', 'route' => 'ops.inventory.index', 'pattern' => 'ops.inventory.index', 'icon' => 'clipboard'],
                ['label' => 'Locations', 'route' => 'ops.inventory.locations.index', 'pattern' => 'ops.inventory.locations.*', 'icon' => 'door'],
                ['label' => 'Movements', 'route' => 'ops.inventory.movements.index', 'pattern' => 'ops.inventory.movements.*', 'icon' => 'truck'],
            ];
        }
        if ($staffCan('manager')) {
            $portalNavGroups['Management'] = [
                ['label' => 'Staff', 'route' => 'ops.staff.index', 'pattern' => 'ops.staff.*', 'icon' => 'users'],
                ['label' => 'Accounting', 'route' => 'ops.finance.index', 'pattern' => 'ops.finance.*', 'icon' => 'wallet'],
            ];
        }
    @endphp

    @include('layouts.partials.portal-shell')

    <script src="{{ asset('vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/axios.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>

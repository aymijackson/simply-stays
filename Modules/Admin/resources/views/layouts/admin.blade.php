<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $siteSettings->color_theme }}" data-layout="{{ $siteSettings->layout_style }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') &mdash; @endif{{ config('app.name') }} Admin</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    @php
        $adminNavGroups = [
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
            'System' => [
                ['label' => 'Activity log', 'route' => 'admin.activity.index', 'pattern' => 'admin.activity.*', 'icon' => 'clock'],
                ['label' => 'Site settings', 'route' => 'admin.settings.edit', 'pattern' => 'admin.settings.*', 'icon' => 'settings'],
            ],
        ];
    @endphp

    <div class="admin-shell" data-admin-shell>
        <div class="admin-sidebar-backdrop" data-admin-sidebar-backdrop></div>

        <aside class="admin-sidebar">
            <a href="{{ route('admin.index') }}" class="admin-sidebar__brand">
                @if ($siteSettings->logoUrl())
                    <img src="{{ $siteSettings->logoUrl() }}" alt="{{ config('app.name') }}">
                @else
                    <x-icon name="home" style="width:20px;height:20px;" />
                @endif
                <span>{{ config('app.name') }}</span>
            </a>

            <nav class="admin-nav">
                @foreach ($adminNavGroups as $groupLabel => $items)
                    <div class="admin-nav-group">
                        <p class="admin-nav-group__title">{{ $groupLabel }}</p>
                        @foreach ($items as $item)
                            <a href="{{ route($item['route']) }}" class="admin-nav-link @if (request()->routeIs($item['pattern'])) is-active @endif">
                                <x-icon :name="$item['icon']" style="width:16px;height:16px;" />
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="admin-sidebar__footer">
                <a href="{{ url('/') }}" class="admin-nav-link">
                    <x-icon name="chevron-left" style="width:16px;height:16px;" />
                    <span>Back to site</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="admin-nav-link admin-nav-link--button">
                        <x-icon name="log-out" style="width:16px;height:16px;" />
                        <span>Log out</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="admin-content">
            <header class="admin-topbar">
                <button type="button" class="admin-sidebar-toggle" data-admin-sidebar-toggle aria-label="Toggle menu">
                    <x-icon name="menu" style="width:20px;height:20px;" />
                </button>
                <h1 class="admin-topbar__title">@yield('title', 'Admin')</h1>
                <div class="admin-topbar__user">
                    <span class="avatar" style="width:30px;height:30px;font-size:0.8rem;">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span>{{ auth()->user()->name }}</span>
                </div>
            </header>

            <main class="admin-main">
                @if (session('status'))
                    <div class="container" style="padding-top: 1.5rem;">
                        <x-alert type="success">{{ session('status') }}</x-alert>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="{{ asset('vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/axios.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>

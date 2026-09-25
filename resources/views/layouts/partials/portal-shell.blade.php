{{-- Expects $portalNavGroups (array of group label => nav items) and $portalHomeRoute (route name for the brand link). --}}
<div class="portal-shell" data-portal-shell>
    <div class="portal-sidebar-backdrop" data-portal-sidebar-backdrop></div>

    <aside class="portal-sidebar">
        <a href="{{ route($portalHomeRoute) }}" class="portal-sidebar__brand">
            @if ($siteSettings->logoUrl())
                <img src="{{ $siteSettings->logoUrl() }}" alt="{{ config('app.name') }}">
            @else
                <x-icon name="home" style="width:20px;height:20px;" />
            @endif
            <span>{{ config('app.name') }}</span>
        </a>

        <nav class="portal-nav">
            @foreach ($portalNavGroups as $groupLabel => $items)
                <div class="portal-nav-group">
                    <p class="portal-nav-group__title">{{ $groupLabel }}</p>
                    @foreach ($items as $item)
                        <a href="{{ route($item['route']) }}" class="portal-nav-link @if (request()->routeIs($item['pattern'])) is-active @endif">
                            <x-icon :name="$item['icon']" style="width:16px;height:16px;" />
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="portal-sidebar__footer">
            <a href="{{ url('/') }}" class="portal-nav-link">
                <x-icon name="chevron-left" style="width:16px;height:16px;" />
                <span>Back to site</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="portal-nav-link portal-nav-link--button">
                    <x-icon name="log-out" style="width:16px;height:16px;" />
                    <span>Log out</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="portal-content">
        <header class="portal-topbar">
            <button type="button" class="portal-sidebar-toggle" data-portal-sidebar-toggle aria-label="Toggle menu">
                <x-icon name="menu" style="width:20px;height:20px;" />
            </button>
            <h1 class="portal-topbar__title">@yield('title', 'Portal')</h1>
            <div class="portal-topbar__user">
                <span class="avatar" style="width:30px;height:30px;font-size:0.8rem;">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <span>{{ auth()->user()->name }}</span>
            </div>
        </header>

        <main class="portal-main">
            @if (session('status'))
                <div class="container" style="padding-top: 1.5rem;">
                    <x-alert type="success">{{ session('status') }}</x-alert>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

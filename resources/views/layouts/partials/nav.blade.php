<header class="site-header">
    <div class="container site-header__bar">
        <a href="{{ url('/') }}" class="brand">
            @if ($siteSettings->logoUrl())
                <img src="{{ $siteSettings->logoUrl() }}" alt="{{ config('app.name') }}" style="height: 32px; width: auto;">
            @else
                <span class="brand__mark"><x-icon name="home" style="width:18px;height:18px;" /></span>
                Short Stay
            @endif
        </a>

        <button type="button" class="nav-toggle" data-nav-toggle aria-label="Toggle navigation">
            <x-icon name="menu" style="width:20px;height:20px;" />
        </button>

        <nav class="nav-menu" data-nav-menu>
            <ul class="nav-menu__links">
                <li><a href="{{ route('listings.index') }}">Stays</a></li>
                <li><a href="{{ route('services.index') }}">Services</a></li>
                <li><a href="{{ route('events.index') }}">Events</a></li>
                @auth
                    <li><a href="{{ route('bookings.index') }}">Bookings</a></li>
                @endauth
            </ul>

            <div class="nav-menu__actions">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-outline btn-sm">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline btn-sm">Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Sign up</a>
                @endauth
            </div>
        </nav>
    </div>
</header>

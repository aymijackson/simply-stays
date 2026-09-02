<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="{{ url('/') }}" class="brand" style="color: #fff;">
                    <span class="brand__mark"><x-icon name="home" style="width:18px;height:18px;" /></span>
                    Short Stay
                </a>
                <p>Stays, transport, and laundry &mdash; booked and paid for in one place, wherever you're headed next.</p>
            </div>

            <div>
                <h4>Explore</h4>
                <ul>
                    <li><a href="{{ route('listings.index') }}">Find a stay</a></li>
                    <li><a href="{{ route('services.index') }}">Book a service</a></li>
                    <li><a href="{{ route('register') }}">Create an account</a></li>
                </ul>
            </div>

            <div>
                <h4>For partners</h4>
                <ul>
                    <li><a href="{{ route('register') }}">List your property</a></li>
                    <li><a href="{{ route('register') }}">Become a service provider</a></li>
                    <li><a href="{{ route('register') }}">Become a referral agent</a></li>
                </ul>
            </div>

            <div>
                <h4>Support</h4>
                <ul>
                    <li><a href="{{ route('bookings.index') }}">Manage a booking</a></li>
                    <li><a href="{{ route('login') }}">Log in</a></li>
                    <li><a href="{{ url('/') }}">Help center</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</span>
            <span>Made for travelers, hosts, providers, and agents alike.</span>
        </div>
    </div>
</footer>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="{{ url('/') }}" class="brand" style="color: #fff;">
                    @if ($siteSettings->logoUrl())
                        <img src="{{ $siteSettings->logoUrl() }}" alt="{{ config('app.name') }}" style="height: 28px; width: auto;">
                    @else
                        <span class="brand__mark"><x-icon name="home" style="width:18px;height:18px;" /></span>
                        {{ config('app.name') }}
                    @endif
                </a>
                <p>{{ $siteSettings->footer_about ?: "Stays, transport, and laundry — booked and paid for in one place, wherever you're headed next." }}</p>

                @if ($siteSettings->facebook_url || $siteSettings->instagram_url || $siteSettings->twitter_url)
                    <div class="footer-social">
                        @if ($siteSettings->facebook_url)
                            <a href="{{ $siteSettings->facebook_url }}" target="_blank" rel="noopener" aria-label="Facebook"><x-icon name="facebook" style="width:16px;height:16px;" /></a>
                        @endif
                        @if ($siteSettings->instagram_url)
                            <a href="{{ $siteSettings->instagram_url }}" target="_blank" rel="noopener" aria-label="Instagram"><x-icon name="instagram" style="width:16px;height:16px;" /></a>
                        @endif
                        @if ($siteSettings->twitter_url)
                            <a href="{{ $siteSettings->twitter_url }}" target="_blank" rel="noopener" aria-label="X (Twitter)"><x-icon name="social-x" style="width:16px;height:16px;" /></a>
                        @endif
                    </div>
                @endif
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
                    <li><a href="{{ route('contact') }}">Contact us</a></li>
                    @if ($siteSettings->contact_email)
                        <li><a href="mailto:{{ $siteSettings->contact_email }}">{{ $siteSettings->contact_email }}</a></li>
                    @endif
                    @if ($siteSettings->contact_phone)
                        <li><a href="tel:{{ $siteSettings->contact_phone }}">{{ $siteSettings->contact_phone }}</a></li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</span>
            <span class="footer-legal-links">
                <a href="{{ route('terms') }}">Terms &amp; Conditions</a>
                <a href="{{ route('privacy') }}">Privacy Policy</a>
            </span>
        </div>
    </div>
</footer>

@props(['latitude', 'longitude', 'label' => ''])

@once
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    @endpush
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endpush
@endonce

@if ($latitude !== null && $longitude !== null)
    @php $mapId = 'map-'.\Illuminate\Support\Str::random(8); @endphp
    <div id="{{ $mapId }}" class="map-embed" {{ $attributes }}></div>
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var el = document.getElementById(@json($mapId));
                if (!el || typeof L === 'undefined') return;
                var map = L.map(el, { scrollWheelZoom: false }).setView([{{ $latitude }}, {{ $longitude }}], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19,
                }).addTo(map);
                L.marker([{{ $latitude }}, {{ $longitude }}]).addTo(map)
                    @if ($label) .bindPopup(@json($label)) @endif
                ;
            });
        </script>
    @endpush
@else
    <div class="map-embed map-embed--empty" {{ $attributes }}>
        <x-icon name="map-pin" style="width:20px;height:20px;" />
        <span>Location not pinned yet.</span>
    </div>
@endif

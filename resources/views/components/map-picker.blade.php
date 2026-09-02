@props(['latitude' => null, 'longitude' => null, 'latInput' => 'latitude', 'lngInput' => 'longitude'])

@once
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    @endpush
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endpush
@endonce

<div class="form-group">
    <label class="form-label">Pin the location on the map</label>
    <p class="form-hint" style="margin-top: -0.2rem;">Click anywhere on the map to set the exact coordinates.</p>
    <div id="map-picker" class="map-embed" style="cursor: crosshair;"></div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('map-picker');
            var latField = document.getElementById(@json($latInput));
            var lngField = document.getElementById(@json($lngInput));
            if (!el || typeof L === 'undefined' || !latField || !lngField) return;

            var startLat = parseFloat(latField.value) || {{ $latitude ?? 9.0765 }};
            var startLng = parseFloat(lngField.value) || {{ $longitude ?? 7.3986 }};

            var map = L.map(el, { scrollWheelZoom: false }).setView([startLat, startLng], latField.value ? 14 : 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            var marker = (latField.value && lngField.value) ? L.marker([startLat, startLng]).addTo(map) : null;

            map.on('click', function (e) {
                latField.value = e.latlng.lat.toFixed(7);
                lngField.value = e.latlng.lng.toFixed(7);
                if (marker) {
                    marker.setLatLng(e.latlng);
                } else {
                    marker = L.marker(e.latlng).addTo(map);
                }
            });
        });
    </script>
@endpush

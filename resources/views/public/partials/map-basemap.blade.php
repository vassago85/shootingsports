{{--
    Basemap for Leaflet pages. Google Maps when GOOGLE_MAPS_WEB_API_KEY
    is set; otherwise CARTO on the production host, then OpenStreetMap.
    Call window.ssAttachBasemap(map) after L.map(...).
--}}
@php
    $googleMapsWebKey = filled(config('services.google.maps_web_key'))
        ? (string) config('services.google.maps_web_key')
        : '';
    $cartoApiKey = filled(config('services.carto.api_key'))
        ? (string) config('services.carto.api_key')
        : '';
@endphp
@if ($googleMapsWebKey !== '')
    <script>
        window.ssOnGoogleMaps = window.ssOnGoogleMaps || function () {
            window.ssGoogleMapsLoaded = true;
        };
    </script>
    <script async src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsWebKey }}&loading=async&callback=ssOnGoogleMaps"></script>
    <script src="https://unpkg.com/leaflet.gridlayer.googlemutant@0.16.0/dist/Leaflet.GoogleMutant.js" crossorigin=""></script>
@endif
<script>
    window.ssAttachBasemap = function (map) {
        var googleKey = @json($googleMapsWebKey);
        var cartoKey = @json($cartoApiKey);

        var osm = function () {
            map.getContainer().classList.add('is-osm-fallback');
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 14,
                minZoom: 4,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);
        };

        var attachGoogle = function () {
            if (! googleKey || ! window.ssGoogleMapsLoaded) {
                return false;
            }
            if (! window.google || ! google.maps || ! L.gridLayer || typeof L.gridLayer.googleMutant !== 'function') {
                return false;
            }
            L.gridLayer.googleMutant({
                type: 'roadmap',
                maxZoom: 18,
                minZoom: 4,
            }).addTo(map);
            return true;
        };

        if (googleKey) {
            if (attachGoogle()) {
                return;
            }
            var tries = 0;
            var timer = setInterval(function () {
                tries += 1;
                if (attachGoogle()) {
                    clearInterval(timer);
                    return;
                }
                if (tries >= 50) {
                    clearInterval(timer);
                    osm();
                }
            }, 100);
            return;
        }

        var useCarto = cartoKey && location.hostname === 'shootingsports.co.za';
        if (! useCarto) {
            osm();
            return;
        }

        var tiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png?key=' + encodeURIComponent(cartoKey), {
            maxZoom: 14,
            minZoom: 4,
            subdomains: 'abcd',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
        });
        var failed = 0;
        tiles.on('tileerror', function () {
            failed += 1;
            if (failed === 1) {
                map.removeLayer(tiles);
                osm();
            }
        });
        tiles.addTo(map);
    };
</script>

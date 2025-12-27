<!-- Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    "use strict";

    function loadAllZone() {
        let map = null;
        let polygons = [];
        let searchMarkers = [];

        // Default map settings from DB or fallback
        const defaultLat = {{ app_setting('map.default_center_lat', 30.0444) }};
        const defaultLng = {{ app_setting('map.default_center_lng', 31.2357) }};
        const defaultZoom = {{ app_setting('map.default_zoom', 12) }};
        const tileUrl = @json(app_setting('map.tile_provider_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'));

        function initialize() {
            let myLatlng = [defaultLat, defaultLng];

            // Initialize Leaflet map
            map = L.map('map-canvas', {
                center: myLatlng,
                zoom: 2,
                zoomControl: true
            });

            // Add OpenStreetMap tile layer
            L.tileLayer(tileUrl, {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // Try HTML5 geolocation to center on user location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const pos = [position.coords.latitude, position.coords.longitude];
                        map.setView(pos, defaultZoom);
                    },
                    () => {
                        // Geolocation failed, use default
                        map.setView(myLatlng, 2);
                    }
                );
            }

            // Setup search box with Nominatim
            const input = document.getElementById("pac-input");
            if (input) {
                setupSearchBox(input);
            }
        }

        function setupSearchBox(input) {
            let searchTimeout = null;
            const $input = $(input);

            // Create results container
            let $resultsContainer = $input.next('.nominatim-results');
            if ($resultsContainer.length === 0) {
                $resultsContainer = $('<div class="nominatim-results"></div>').insertAfter($input);
            }

            $resultsContainer.css({
                position: 'absolute',
                zIndex: 1000,
                background: '#fff',
                border: '1px solid #ccc',
                borderRadius: '4px',
                maxHeight: '200px',
                overflowY: 'auto',
                width: $input.outerWidth() + 'px',
                display: 'none'
            });

            $input.on('input', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val().trim();
                if (query.length < 3) {
                    $resultsContainer.hide();
                    return;
                }
                searchTimeout = setTimeout(() => {
                    searchNominatim(query, $resultsContainer);
                }, 500);
            });

            $input.on('blur', function() {
                setTimeout(() => $resultsContainer.hide(), 200);
            });
        }

        function searchNominatim(query, $container) {
            $.get('https://nominatim.openstreetmap.org/search', {
                q: query,
                format: 'json',
                limit: 5
            }, function(results) {
                $container.empty();
                if (results.length === 0) {
                    $container.append('<div class="p-2 text-muted">{{ translate("no_results_found") }}</div>');
                } else {
                    results.forEach(function(result) {
                        const $item = $('<div class="p-2 nominatim-result-item" style="cursor:pointer;border-bottom:1px solid #eee;"></div>')
                            .text(result.display_name)
                            .on('click', function() {
                                const lat = parseFloat(result.lat);
                                const lng = parseFloat(result.lon);

                                // Clear existing search markers
                                searchMarkers.forEach(m => map.removeLayer(m));
                                searchMarkers = [];

                                // Add marker for search result
                                const marker = L.marker([lat, lng]).addTo(map);
                                marker.bindPopup(result.display_name).openPopup();
                                searchMarkers.push(marker);

                                // Pan to location
                                map.setView([lat, lng], 16);
                                $container.hide();
                                $('#pac-input').val(result.display_name);
                            });
                        $container.append($item);
                    });
                }
                $container.show();
            }).fail(function() {
                $container.hide();
            });
        }

        window.addEventListener('load', initialize);

        function set_all_zones() {
            $.get({
                url: '{{route('admin.zone.get-zones',['status'=>'active'])}}',
                dataType: 'json',
                success: function (data) {
                    for (let i = 0; i < data.length; i++) {
                        // Convert Google Maps format {lat, lng} to Leaflet format [lat, lng]
                        const coords = data[i].map(point => [point.lat, point.lng]);
                        const polygon = L.polygon(coords, {
                            color: '#FF0000',
                            weight: 2,
                            opacity: 0.8,
                            fillColor: '#FF0000',
                            fillOpacity: 0.1
                        }).addTo(map);
                        polygons.push(polygon);
                    }
                },
            });
        }

        set_all_zones();
    }

    loadAllZone();

    function loadMapLater() {
        let map = null;
        let polygons = [];

        @if(isset($zone))
        function set_all_zones() {
            $.get({
                url: '{{route('admin.zone.get-zones',['id'=>$zone->id,'status'=>'active'])}}',
                dataType: 'json',
                success: function (data) {
                    // Get map reference
                    const mapContainer = document.getElementById('map-canvas');
                    if (!mapContainer || !mapContainer._leaflet_id) return;

                    const mapInstance = L.DomUtil.get('map-canvas')._leaflet_map;
                    if (!mapInstance) return;

                    for (let i = 0; i < data.length; i++) {
                        const coords = data[i].map(point => [point.lat, point.lng]);
                        const polygon = L.polygon(coords, {
                            color: '#FF0000',
                            weight: 2,
                            opacity: 0.8,
                            fillColor: '#FF0000',
                            fillOpacity: 0.1
                        }).addTo(mapInstance);
                        polygons.push(polygon);
                    }
                },
            });
        }

        set_all_zones();
        @endif
    }
</script>

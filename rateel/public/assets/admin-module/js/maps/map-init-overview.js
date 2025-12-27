"use strict";

/**
 * Leaflet Map Initialization Overview (OpenStreetMap)
 * Replaces Google Maps with Leaflet + OSM tiles
 */

$(document).ready(function () {
    // Default map settings
    const defaultTileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

    function initMap(mapSelector, lat, lng, title, markersData, input, polygonData = []) {
        let bounds = L.latLngBounds();
        let polygons = [];
        let markerCluster = null;
        let searchMarkers = [];

        let zoomValue = 13;
        if (lat == 0 && lng == 0) {
            zoomValue = 2;
        }

        // Initialize Leaflet map
        const map = L.map(mapSelector, {
            center: [lat || 0, lng || 0],
            zoom: zoomValue,
            zoomControl: true
        });

        // Add OpenStreetMap tile layer
        L.tileLayer(defaultTileUrl, {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Add zone polygons
        if (zoomValue == 13 && polygonData && polygonData.length > 0) {
            for (let i = 0; i < polygonData.length; i++) {
                // Convert Google Maps format {lat, lng} to Leaflet format [lat, lng]
                const coords = polygonData[i].map(point => [point.lat, point.lng]);
                const polygon = L.polygon(coords, {
                    color: '#000000',
                    weight: 2,
                    opacity: 0.2,
                    fillColor: '#000000',
                    fillOpacity: 0.05
                }).addTo(map);
                polygons.push(polygon);
                bounds.extend(polygon.getBounds());
            }
            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [20, 20] });
            }
        }

        // Initialize marker cluster group
        markerCluster = L.markerClusterGroup({
            chunkedLoading: true,
            maxClusterRadius: 50,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true
        });
        map.addLayer(markerCluster);

        // Add markers
        if (markersData && markersData.length > 0) {
            markersData.forEach(function (data) {
                const markerOptions = {};

                if (data.icon) {
                    markerOptions.icon = L.icon({
                        iconUrl: data.icon,
                        iconSize: [32, 32],
                        iconAnchor: [16, 32],
                        popupAnchor: [0, -32]
                    });
                }

                const marker = L.marker([data.position.lat, data.position.lng], markerOptions);

                marker.on('click', function () {
                    L.popup()
                        .setLatLng(marker.getLatLng())
                        .setContent(`<div class="map-clusters-custom-window"><h6>${data.title}</h6></div>`)
                        .openOn(map);
                });

                markerCluster.addLayer(marker);
            });
        }

        // Setup search box with Nominatim
        if (input) {
            setupSearchBox(input, map, searchMarkers);
        }
    }

    function setupSearchBox(input, map, searchMarkers) {
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
                searchNominatim(query, $resultsContainer, map, searchMarkers);
            }, 500);
        });

        $input.on('blur', function() {
            setTimeout(() => $resultsContainer.hide(), 200);
        });
    }

    function searchNominatim(query, $container, map, searchMarkers) {
        $.get('https://nominatim.openstreetmap.org/search', {
            q: query,
            format: 'json',
            limit: 5
        }, function(results) {
            $container.empty();
            if (results.length === 0) {
                $container.append('<div class="p-2 text-muted">No results found</div>');
            } else {
                results.forEach(function(result) {
                    const $item = $('<div class="p-2 nominatim-result-item" style="cursor:pointer;border-bottom:1px solid #eee;"></div>')
                        .text(result.display_name)
                        .on('click', function() {
                            const lat = parseFloat(result.lat);
                            const lng = parseFloat(result.lon);

                            // Clear existing search markers
                            searchMarkers.forEach(m => map.removeLayer(m));
                            searchMarkers.length = 0;

                            // Add marker for search result
                            const marker = L.marker([lat, lng]).addTo(map);
                            marker.bindPopup(result.display_name).openPopup();
                            searchMarkers.push(marker);

                            // Pan to location
                            map.setView([lat, lng], 16);
                            $container.hide();
                        });
                    $container.append($item);
                });
            }
            $container.show();
        }).fail(function() {
            $container.hide();
        });
    }

    // Initialize maps on page load
    $(".map-container").each(function () {
        const $map = $(this).find(".map");
        const input = $(this).find(".map-search-input")[0];
        const lat = $map.data("lat") || 0;
        const lng = $map.data("lng") || 0;
        const title = $map.data("title");
        const markers = $map.data("markers") || [];
        const polygonData = $map.data("polygon") || [];

        initMap($map.attr("id"), lat, lng, title, markers, input, polygonData);
    });
});

"use strict";

/**
 * Leaflet Map Initialization Overview (OpenStreetMap)
 * Replaces Google Maps with Leaflet + OSM tiles
 *
 * FIXED VERSION: Includes coordinate validation, HTML entity decoding,
 * Egypt bounding box correction, and comprehensive debugging
 */

$(document).ready(function () {
    // Default map settings
    const defaultTileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

    // Egypt bounding box for coordinate validation
    const EGYPT_BOUNDS = {
        minLat: 22,
        maxLat: 32,
        minLng: 25,
        maxLng: 36
    };

    // Cairo fallback coordinates
    const CAIRO_CENTER = {
        lat: 30.0444,
        lng: 31.2357
    };

    /**
     * Decode HTML entities from string
     */
    function decodeHTMLEntities(text) {
        if (!text) return text;
        const textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        return textArea.value;
    }

    /**
     * Parse JSON data from data attribute with HTML entity decoding
     */
    function parseDataAttribute(element, attributeName) {
        try {
            const rawValue = element.getAttribute(attributeName);
            if (!rawValue) {
                console.log(`⚠️ ${attributeName} is empty or missing`);
                return null;
            }

            // Decode HTML entities (&quot; etc)
            const decodedValue = decodeHTMLEntities(rawValue);

            // Parse JSON
            const parsed = JSON.parse(decodedValue);
            console.log(`✅ Parsed ${attributeName}:`, parsed);
            return parsed;
        } catch (error) {
            console.error(`❌ Failed to parse ${attributeName}:`, error);
            console.error('Raw value:', element.getAttribute(attributeName));
            return null;
        }
    }

    /**
     * Validate if coordinate is within Egypt bounding box
     */
    function isValidEgyptCoordinate(lat, lng) {
        return lat >= EGYPT_BOUNDS.minLat && lat <= EGYPT_BOUNDS.maxLat &&
               lng >= EGYPT_BOUNDS.minLng && lng <= EGYPT_BOUNDS.maxLng;
    }

    /**
     * Normalize and validate coordinates
     * Auto-detects and fixes swapped lat/lng for Egypt
     */
    function normalizeCoordinate(lat, lng) {
        // Convert to numbers
        lat = parseFloat(lat);
        lng = parseFloat(lng);

        // Check if numeric
        if (isNaN(lat) || isNaN(lng)) {
            console.warn('⚠️ Invalid coordinate (NaN):', {lat, lng});
            return null;
        }

        // Check if valid Egypt coordinates
        if (isValidEgyptCoordinate(lat, lng)) {
            return {lat, lng};
        }

        // Try swapping
        if (isValidEgyptCoordinate(lng, lat)) {
            console.warn('🔄 Swapped coordinates detected:', {
                original: {lat, lng},
                fixed: {lat: lng, lng: lat}
            });
            return {lat: lng, lng: lat};
        }

        // Invalid coordinates
        console.warn('❌ Coordinates outside Egypt bounds:', {lat, lng});
        return null;
    }

    /**
     * Calculate center from array of coordinates
     */
    function calculateCenter(coordinates) {
        if (!coordinates || coordinates.length === 0) {
            return null;
        }

        let latSum = 0;
        let lngSum = 0;
        let count = 0;

        coordinates.forEach(coord => {
            if (coord && coord.lat && coord.lng) {
                latSum += coord.lat;
                lngSum += coord.lng;
                count++;
            }
        });

        if (count === 0) return null;

        return {
            lat: latSum / count,
            lng: lngSum / count
        };
    }

    /**
     * Validate and normalize polygon data
     */
    function normalizePolygon(polygon) {
        if (!Array.isArray(polygon) || polygon.length === 0) {
            return null;
        }

        const normalized = [];

        for (let point of polygon) {
            if (!point || typeof point !== 'object') continue;

            const lat = point.lat || point.latitude;
            const lng = point.lng || point.lng || point.longitude;

            const coord = normalizeCoordinate(lat, lng);
            if (coord) {
                normalized.push(coord);
            }
        }

        if (normalized.length < 3) {
            console.warn('⚠️ Polygon has less than 3 valid points');
            return null;
        }

        // Ensure polygon is closed (first point = last point)
        const first = normalized[0];
        const last = normalized[normalized.length - 1];
        if (first.lat !== last.lat || first.lng !== last.lng) {
            normalized.push({...first});
        }

        return normalized;
    }

    function initMap(mapSelector, lat, lng, title, markersData, input, polygonData = []) {
        console.log('🗺️ ========== MAP INITIALIZATION START ==========');
        console.log('Map ID:', mapSelector);
        console.log('Initial Center:', {lat, lng});
        console.log('Raw Markers Count:', markersData ? markersData.length : 0);
        console.log('Raw Polygons Count:', polygonData ? polygonData.length : 0);

        let bounds = L.latLngBounds();
        let polygons = [];
        let markerCluster = null;
        let searchMarkers = [];

        // Normalize and validate markers
        let validMarkers = [];
        if (markersData && Array.isArray(markersData)) {
            markersData.forEach((marker, index) => {
                if (!marker || !marker.position) {
                    console.warn(`⚠️ Marker ${index} missing position`);
                    return;
                }

                const coord = normalizeCoordinate(
                    marker.position.lat,
                    marker.position.lng
                );

                if (coord) {
                    validMarkers.push({
                        ...marker,
                        position: coord
                    });
                } else {
                    console.warn(`❌ Marker ${index} invalid:`, marker.position);
                }
            });
        }

        console.log('✅ Valid Markers:', validMarkers.length);
        if (validMarkers.length > 0) {
            console.log('First Marker:', validMarkers[0].position);
        }

        // Normalize and validate polygons
        let validPolygons = [];
        if (polygonData && Array.isArray(polygonData)) {
            polygonData.forEach((polygon, index) => {
                const normalized = normalizePolygon(polygon);
                if (normalized) {
                    validPolygons.push(normalized);
                } else {
                    console.warn(`❌ Polygon ${index} invalid`);
                }
            });
        }

        console.log('✅ Valid Polygons:', validPolygons.length);

        // Determine map center with fallback logic
        let centerLat = lat;
        let centerLng = lng;

        // Validate initial center
        const normalizedCenter = normalizeCoordinate(lat, lng);
        if (!normalizedCenter) {
            console.warn('⚠️ Invalid center coordinates, calculating from data...');

            // Try to calculate from markers
            if (validMarkers.length > 0) {
                const markerCoords = validMarkers.map(m => m.position);
                const calculatedCenter = calculateCenter(markerCoords);
                if (calculatedCenter) {
                    centerLat = calculatedCenter.lat;
                    centerLng = calculatedCenter.lng;
                    console.log('📍 Center calculated from markers:', {centerLat, centerLng});
                }
            }

            // Fallback to Cairo if still invalid
            if (!normalizeCoordinate(centerLat, centerLng)) {
                console.warn('⚠️ Using Cairo fallback center');
                centerLat = CAIRO_CENTER.lat;
                centerLng = CAIRO_CENTER.lng;
            }
        }

        console.log('📍 Final Map Center:', {centerLat, centerLng});

        // Determine zoom level
        let zoomValue = 13;
        if (validMarkers.length === 0 && validPolygons.length === 0) {
            zoomValue = 6; // Show Egypt overview
        }

        // Initialize Leaflet map
        const map = L.map(mapSelector, {
            center: [centerLat, centerLng],
            zoom: zoomValue,
            zoomControl: true
        });

        console.log('✅ Leaflet map instance created');

        // Add OpenStreetMap tile layer
        L.tileLayer(defaultTileUrl, {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        console.log('✅ Tile layer added');

        // Add zone polygons
        if (validPolygons.length > 0) {
            console.log('📐 Rendering polygons...');
            validPolygons.forEach((polygonCoords, index) => {
                try {
                    // Convert to Leaflet format [lat, lng]
                    const coords = polygonCoords.map(point => [point.lat, point.lng]);
                    const polygon = L.polygon(coords, {
                        color: '#000000',
                        weight: 2,
                        opacity: 0.2,
                        fillColor: '#000000',
                        fillOpacity: 0.05
                    }).addTo(map);
                    polygons.push(polygon);
                    bounds.extend(polygon.getBounds());
                    console.log(`✅ Polygon ${index} rendered with ${coords.length} points`);
                } catch (error) {
                    console.error(`❌ Failed to render polygon ${index}:`, error);
                }
            });

            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [20, 20] });
                console.log('✅ Map fitted to polygon bounds');
            }
        }

        // Initialize marker cluster group
        markerCluster = L.markerClusterGroup({
            chunkedLoading: true,
            maxClusterRadius: validMarkers.length > 1000 ? 80 : 50,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true
        });
        map.addLayer(markerCluster);

        console.log(`📌 Marker cluster initialized (radius: ${validMarkers.length > 1000 ? 80 : 50})`);

        // Add markers
        if (validMarkers.length > 0) {
            console.log('📌 Adding markers to cluster...');
            let addedCount = 0;

            validMarkers.forEach(function (data) {
                try {
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
                            .setContent(`<div class="map-clusters-custom-window"><h6>${data.title || 'Marker'}</h6></div>`)
                            .openOn(map);
                    });

                    markerCluster.addLayer(marker);
                    addedCount++;
                } catch (error) {
                    console.error('❌ Failed to add marker:', error, data);
                }
            });

            console.log(`✅ Added ${addedCount} markers to map`);
        }

        // Setup search box with Nominatim
        if (input) {
            setupSearchBox(input, map, searchMarkers);
        }

        console.log('🗺️ ========== MAP INITIALIZATION COMPLETE ==========');

        // Return map instance for external access
        return map;
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
        console.log('🔍 ========== PARSING MAP DATA ==========');

        const $map = $(this).find(".map");
        const mapElement = $map[0];

        if (!mapElement) {
            console.error('❌ Map element not found');
            return;
        }

        const input = $(this).find(".map-search-input")[0];

        // Parse coordinates with fallback
        let lat = parseFloat($map.data("lat")) || 0;
        let lng = parseFloat($map.data("lng")) || 0;

        console.log('Raw Center Data:', {lat, lng});

        const title = $map.data("title") || 'Map';

        // Parse markers with HTML entity decoding
        let markers = parseDataAttribute(mapElement, 'data-markers');
        if (!markers || !Array.isArray(markers)) {
            console.warn('⚠️ No valid markers found, using empty array');
            markers = [];
        }

        // Parse polygon data with HTML entity decoding
        let polygonData = parseDataAttribute(mapElement, 'data-polygon');
        if (!polygonData || !Array.isArray(polygonData)) {
            console.warn('⚠️ No valid polygon data found, using empty array');
            polygonData = [];
        }

        console.log('📊 Data Summary:', {
            center: {lat, lng},
            markersCount: markers.length,
            polygonsCount: polygonData.length
        });

        // Initialize the map
        try {
            initMap($map.attr("id"), lat, lng, title, markers, input, polygonData);
        } catch (error) {
            console.error('❌ Failed to initialize map:', error);
        }
    });
});

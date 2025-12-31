"use strict";

/**
 * Leaflet Map Initialization Overview (OpenStreetMap)
 * Replaces Google Maps with Leaflet + OSM tiles
 *
 * Includes robust JSON parsing and Modal support
 */

window.leafMaps = window.leafMaps || {}; // Registry for map instances

/**
 * Decode HTML entities from string
 */
function decodeHTMLEntities(text) {
    if (!text) return text;
    if (typeof text !== 'string') return text;
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
        if (!rawValue) return [];

        // Decode HTML entities (&quot; etc)
        const decodedValue = decodeHTMLEntities(rawValue);

        // Parse JSON
        const parsed = JSON.parse(decodedValue);
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        console.error(`❌ Failed to parse ${attributeName}:`, error);
        // Try jQuery fallback
        const jqData = $(element).data(attributeName.replace('data-', ''));
        return Array.isArray(jqData) ? jqData : [];
    }
}

function initMap(mapSelector, lat, lng, title, markersData, input, polygonData = []) {
    // Default map settings
    const defaultTileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

    // Check if map is already initialized
    if (window.leafMaps[mapSelector]) {
        try {
            window.leafMaps[mapSelector].remove();
            delete window.leafMaps[mapSelector];
        } catch (e) {
            console.warn('Failed to cleanup old map', e);
        }
    }

    let bounds = L.latLngBounds();
    let polygons = [];
    let markerCluster = null;
    let searchMarkers = [];

    // Normalize center - default to Cairo, Egypt if invalid
    if (!lat || !lng || isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) {
        lat = 30.0444; // Cairo
        lng = 31.2357;
    }

    // Initialize Leaflet map
    const map = L.map(mapSelector, {
        center: [lat, lng],
        zoom: lat === 30.0444 && lng === 31.2357 ? 10 : 13, // Lower zoom if using default
        zoomControl: true,
        preferCanvas: false, // Better performance for many markers
        worldCopyJump: false
    });
    
    console.log('🗺️ Map initialized at:', lat, lng);

    // Store instance
    window.leafMaps[mapSelector] = map;

    // Add OpenStreetMap tile layer
    L.tileLayer(defaultTileUrl, {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    // Add zone polygons
    if (polygonData && Array.isArray(polygonData)) {
        polygonData.forEach((polygonCoords, index) => {
            try {
                const coords = polygonCoords.map(point => {
                    if (Array.isArray(point)) return point;
                    return [point.lat || point.latitude, point.lng || point.longitude];
                });

                const polygon = L.polygon(coords, {
                    color: '#000000',
                    weight: 2,
                    opacity: 0.2,
                    fillColor: '#000000',
                    fillOpacity: 0.05
                }).addTo(map);
                polygons.push(polygon);
                bounds.extend(polygon.getBounds());
            } catch (e) {
                console.warn('Invalid polygon data', e);
            }
        });

        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [20, 20] });
        }
    }

    // Prepare heatmap data points
    const heatmapPoints = [];
    
    // Initialize marker cluster group
    markerCluster = L.markerClusterGroup({
        chunkedLoading: true,
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true
    });
    map.addLayer(markerCluster);

    // Add markers and collect heatmap points
    if (markersData && Array.isArray(markersData)) {
        markersData.forEach(function (data) {
            if (!data.position) return;

            const mLat = parseFloat(data.position.lat);
            const mLng = parseFloat(data.position.lng);

            if (isNaN(mLat) || isNaN(mLng) || mLat === 0 || mLng === 0) return;

            // Add to heatmap points array
            heatmapPoints.push([mLat, mLng, 1.0]); // [lat, lng, intensity]

            const markerOptions = {};

            if (data.icon) {
                markerOptions.icon = L.icon({
                    iconUrl: data.icon,
                    iconSize: [32, 32],
                    iconAnchor: [16, 32],
                    popupAnchor: [0, -32]
                });
            }

            const marker = L.marker([mLat, mLng], markerOptions);

            marker.on('click', function () {
                L.popup()
                    .setLatLng(marker.getLatLng())
                    .setContent(`<div class="map-clusters-custom-window"><h6>${data.title || 'Trip'}</h6></div>`)
                    .openOn(map);
            });

            markerCluster.addLayer(marker);
        });
    }

    // Add heatmap layer if Leaflet.heat is available and we have points
    if (heatmapPoints.length > 0) {
        // Check if Leaflet.heat is loaded (it might be L.heatLayer or window.HeatmapOverlay)
        if (typeof L.heatLayer !== 'undefined') {
            try {
                const heatLayer = L.heatLayer(heatmapPoints, {
                    radius: 25,
                    blur: 15,
                    maxZoom: 17,
                    max: 1.0,
                    gradient: {
                        0.0: 'blue',
                        0.5: 'cyan',
                        0.7: 'lime',
                        0.85: 'yellow',
                        1.0: 'red'
                    }
                }).addTo(map);
                
                console.log('✅ Heatmap layer added with', heatmapPoints.length, 'points');
            } catch (error) {
                console.error('Error adding heatmap layer:', error);
            }
        } else {
            console.warn('⚠️ Leaflet.heat plugin not loaded. Heatmap visualization disabled.');
            console.info('To enable heatmap, ensure leaflet.heat script is loaded before this script.');
        }
    } else {
        console.info('ℹ️ No trip markers found for heatmap visualization.');
    }

    // Setup search box with Nominatim
    if (input) {
        setupSearchBox(input, map, searchMarkers);
    }

    // Force resize and remove loading spinner
    setTimeout(() => {
        map.invalidateSize();
        // Remove any loading spinners
        const mapElement = document.querySelector(mapSelector);
        if (mapElement) {
            const spinner = mapElement.querySelector('.spinner-border');
            if (spinner) {
                spinner.remove();
            }
        }
    }, 200);

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

// Initialize all maps on the page
function initializeOverviewMaps() {
    $(".map-container").each(function () {
        const $map = $(this).find(".map");
        const mapElement = $map[0];

        if (!mapElement) {
            console.warn('Map element not found');
            return;
        }

        const input = $(this).find(".map-search-input")[0];

        let lat = parseFloat($map.data("lat")) || 30.0444;
        let lng = parseFloat($map.data("lng")) || 31.2357;

        const markers = parseDataAttribute(mapElement, 'data-markers') || [];
        const polygonData = parseDataAttribute(mapElement, 'data-polygon') || [];
        const title = $map.data("title") || "Heat Map";

        console.log('🗺️ Initializing overview map with:', {
            id: $map.attr("id"),
            lat, lng,
            markersCount: markers.length,
            polygonsCount: polygonData.length,
            heatPluginLoaded: typeof L !== 'undefined' && typeof L.heatLayer !== 'undefined'
        });

        // Ensure Leaflet is loaded before initializing
        if (typeof L === 'undefined') {
            console.error('❌ Leaflet library not loaded!');
            $(mapElement).html('<div class="alert alert-danger p-4 text-center">Map library failed to load. Please refresh the page.</div>');
            return;
        }

        try {
            initMap($map.attr("id"), lat, lng, title, markers, input, polygonData);
        } catch (error) {
            console.error('❌ Error initializing map:', error);
            // Show error message to user
            $(mapElement).html('<div class="alert alert-danger p-4 text-center">Error loading map: ' + (error.message || 'Unknown error') + '</div>');
        }
    });
}

// Initialize on document ready
$(document).ready(function () {
    initializeOverviewMaps();
});

// Expose function globally for AJAX reloads
window.initializeOverviewMaps = initializeOverviewMaps;
"use strict";

// Simple OpenStreetMap Heat Map - NO Google Maps
// Uses Leaflet only
console.log('✅ map-init-overview.js loaded - NO Google Maps');

window.leafMaps = window.leafMaps || {};

function parseDataAttribute(element, attributeName) {
    try {
        const rawValue = element.getAttribute(attributeName);
        if (!rawValue) return [];
        const parsed = JSON.parse(rawValue);
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        console.error('Failed to parse', attributeName, error);
        return [];
    }
}

function initMap(mapSelector, lat, lng, markersData, polygonData) {
    // Default to Cairo if invalid
    if (!lat || !lng || isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) {
        lat = 30.0444;
        lng = 31.2357;
    }

    // Remove old map if exists
    if (window.leafMaps[mapSelector]) {
        try {
            window.leafMaps[mapSelector].remove();
        } catch (e) {}
        delete window.leafMaps[mapSelector];
    }

    // Create map
    const map = L.map(mapSelector, {
        center: [lat, lng],
        zoom: 10,
        zoomControl: true
    });

    window.leafMaps[mapSelector] = map;

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // Add polygons
    if (polygonData && Array.isArray(polygonData)) {
        polygonData.forEach(function(polygonCoords) {
            try {
                const coords = polygonCoords.map(function(point) {
                    if (Array.isArray(point)) return point;
                    return [point.lat || point.latitude, point.lng || point.longitude];
                });
                L.polygon(coords, {
                    color: '#000000',
                    weight: 2,
                    opacity: 0.2,
                    fillOpacity: 0.05
                }).addTo(map);
            } catch (e) {
                console.warn('Invalid polygon', e);
            }
        });
    }

    // Add markers with clustering
    const markerCluster = L.markerClusterGroup({
        maxClusterRadius: 50
    });
    map.addLayer(markerCluster);

    const heatmapPoints = [];

    if (markersData && Array.isArray(markersData)) {
        markersData.forEach(function(data) {
            if (!data.position) return;

            const mLat = parseFloat(data.position.lat);
            const mLng = parseFloat(data.position.lng);

            if (isNaN(mLat) || isNaN(mLng) || mLat === 0 || mLng === 0) return;

            heatmapPoints.push([mLat, mLng, 1.0]);

            const marker = L.marker([mLat, mLng]);
            if (data.title) {
                marker.bindPopup(data.title);
            }
            markerCluster.addLayer(marker);
        });
    }

    // Add heatmap layer
    if (heatmapPoints.length > 0 && typeof L.heatLayer !== 'undefined') {
        try {
            L.heatLayer(heatmapPoints, {
                radius: 25,
                blur: 15,
                maxZoom: 17,
                gradient: {
                    0.0: 'blue',
                    0.5: 'cyan',
                    0.7: 'lime',
                    0.85: 'yellow',
                    1.0: 'red'
                }
            }).addTo(map);
        } catch (e) {
            console.warn('Heatmap layer error', e);
        }
    }

    // Remove loading spinner
    setTimeout(function() {
        map.invalidateSize();
        const mapElement = document.querySelector(mapSelector);
        if (mapElement) {
            const spinner = mapElement.querySelector('.spinner-border');
            if (spinner) spinner.remove();
        }
    }, 200);

    return map;
}

function initializeOverviewMaps() {
    if (typeof L === 'undefined') {
        console.error('Leaflet not loaded!');
        return;
    }

    $(".map-container").each(function() {
        const $map = $(this).find(".map");
        const mapElement = $map[0];
        if (!mapElement) return;

        let lat = parseFloat($map.data("lat")) || 30.0444;
        let lng = parseFloat($map.data("lng")) || 31.2357;

        const markers = parseDataAttribute(mapElement, 'data-markers') || [];
        const polygonData = parseDataAttribute(mapElement, 'data-polygon') || [];

        try {
            initMap($map.attr("id"), lat, lng, markers, polygonData);
        } catch (error) {
            console.error('Map init error:', error);
        }
    });
}

// Wait for jQuery and Leaflet
(function() {
    function waitAndInit() {
        if (typeof $ !== 'undefined' && typeof L !== 'undefined' && typeof L.map === 'function') {
            $(document).ready(function() {
                initializeOverviewMaps();
            });
        } else {
            setTimeout(waitAndInit, 100);
        }
    }
    waitAndInit();
})();

window.initializeOverviewMaps = initializeOverviewMaps;

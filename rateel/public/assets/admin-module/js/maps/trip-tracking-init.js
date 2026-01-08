// Global variables
let map;
let markerClusterGroup;
let tripPolylines = [];
let driverMarkers = {};
let pollingInterval = null;
let isLiveTrackingEnabled = true;

// Status colors for trip paths
const STATUS_COLORS = {
    'pending': '#007bff',
    'accepted': '#28a745',
    'ongoing': '#ffc107',
    'completed': '#17a2b8',
    'cancelled': '#dc3545'
};

// Initialize map on page load
function initializeTripTracking() {
    console.log('=== Trip Tracking Initialization Start ===');
    console.log('jQuery loaded:', typeof $ !== 'undefined');
    console.log('Leaflet loaded:', typeof L !== 'undefined');
    console.log('Moment loaded:', typeof moment !== 'undefined');
    console.log('tripTrackingDataUrl defined:', typeof tripTrackingDataUrl !== 'undefined');
    console.log('tripTrackingDataUrl value:', typeof tripTrackingDataUrl !== 'undefined' ? tripTrackingDataUrl : 'UNDEFINED');
    console.log('Map container exists:', document.getElementById('tripTrackingMap') !== null);

    // Check if all dependencies are loaded
    if (typeof L === 'undefined') {
        console.error('ERROR: Leaflet (L) is not loaded!');
        return;
    }
    if (typeof $ === 'undefined') {
        console.error('ERROR: jQuery ($) is not loaded!');
        return;
    }
    if (typeof moment === 'undefined') {
        console.error('ERROR: Moment.js is not loaded!');
        return;
    }
    if (typeof tripTrackingDataUrl === 'undefined') {
        console.error('ERROR: tripTrackingDataUrl is not defined!');
        return;
    }

    try {
        initializeMap();
        initializeDateRangePicker();
        initializeEventListeners();
        fetchTripTrackingData(); // Initial load
        startPolling(); // Start 15-second polling
        console.log('=== Trip Tracking: Initialization complete ===');
    } catch (error) {
        console.error('=== Trip Tracking initialization error ===', error);
    }
}

// Wait for DOM and all dependencies
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initializeTripTracking, 100);
    });
} else {
    setTimeout(initializeTripTracking, 100);
}

/**
 * Initialize Leaflet map
 */
function initializeMap() {
    console.log('Initializing Leaflet map...');
    console.log('Map container exists:', document.getElementById('tripTrackingMap') !== null);
    console.log('Leaflet loaded:', typeof L !== 'undefined');

    // Remove loading message
    const loadingMessage = document.getElementById('mapLoadingMessage');
    if (loadingMessage) {
        loadingMessage.remove();
    }

    map = L.map('tripTrackingMap').setView([defaultLat, defaultLng], defaultZoom);
    console.log('Map created successfully');

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // Initialize marker cluster group
    markerClusterGroup = L.markerClusterGroup({
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true
    });
    map.addLayer(markerClusterGroup);
}

/**
 * Initialize DateRangePicker
 */
function initializeDateRangePicker() {
    $('#dateRangePicker').daterangepicker({
        startDate: moment().startOf('day'),
        endDate: moment().endOf('day'),
        locale: {
            format: 'YYYY-MM-DD'
        }
    });
}

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // Toggle switch
    $('#liveTrackingToggle').on('change', function() {
        isLiveTrackingEnabled = $(this).is(':checked');

        if (isLiveTrackingEnabled) {
            startPolling();
            fetchTripTrackingData(); // Immediate fetch
            if (typeof toastr !== 'undefined') toastr.success('Live tracking enabled');
        } else {
            stopPolling();
            clearAllData();
            if (typeof toastr !== 'undefined') toastr.info('Live tracking disabled');
        }
    });

    // Apply filters button
    $('#applyFilters').on('click', function() {
        fetchTripTrackingData();
    });

    // Auto-search on driver input (debounced)
    let driverSearchTimeout;
    $('#driverSearch').on('input', function() {
        clearTimeout(driverSearchTimeout);
        driverSearchTimeout = setTimeout(() => {
            fetchTripTrackingData();
        }, 500);
    });

    // Trigger fetch on filter changes
    $('#zoneFilter, #statusFilter').on('change', function() {
        fetchTripTrackingData();
    });

    // Trigger fetch on date range change
    $('#dateRangePicker').on('apply.daterangepicker', function() {
        fetchTripTrackingData();
    });
}

/**
 * Fetch trip tracking data via AJAX
 */
function fetchTripTrackingData() {
    if (!isLiveTrackingEnabled) {
        return; // Don't fetch if tracking is disabled
    }

    const filters = {
        zone_id: $('#zoneFilter').val(),
        driver_id: $('#driverSearch').val(),
        status: $('#statusFilter').val(),
        date_from: $('#dateRangePicker').data('daterangepicker').startDate.format('YYYY-MM-DD'),
        date_to: $('#dateRangePicker').data('daterangepicker').endDate.format('YYYY-MM-DD')
    };

    $.ajax({
        url: tripTrackingDataUrl,
        method: 'GET',
        data: filters,
        success: function(response) {
            updateMap(response);
        },
        error: function(xhr, status, error) {
            console.error('Failed to fetch trip tracking data:', error);
            if (typeof toastr !== 'undefined') {
                toastr.error('Failed to load data');
            }
        }
    });
}

/**
 * Update map with trip data
 */
function updateMap(data) {
    // Clear existing trip polylines
    tripPolylines.forEach(polyline => map.removeLayer(polyline));
    tripPolylines = [];

    // Clear existing driver markers
    markerClusterGroup.clearLayers();
    driverMarkers = {};

    // Draw trip paths (polylines)
    if (data.trips && data.trips.length > 0) {
        data.trips.forEach(trip => {
            if (trip.path && trip.path.length > 0) {
                drawTripPath(trip);
            }

            // Add driver marker if available
            if (trip.current_location && trip.driver) {
                addDriverMarker(trip.driver, trip.current_location, trip.status);
            }
        });

        // Fit bounds to show all trips
        if (tripPolylines.length > 0) {
            const group = new L.featureGroup(tripPolylines);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    // Draw zone polygons if available
    if (data.polygons && data.polygons.length > 0) {
        drawZonePolygons(data.polygons);
    }
}

/**
 * Draw trip path as polyline
 */
function drawTripPath(trip) {
    const latLngs = trip.path.map(point => [point.lat, point.lng]);
    const color = STATUS_COLORS[trip.status] || '#6c757d';

    const polyline = L.polyline(latLngs, {
        color: color,
        weight: 3,
        opacity: 0.7,
        smoothFactor: 1
    }).addTo(map);

    // Popup info
    const popupContent = `
        <div style="min-width: 200px;">
            <strong>Trip #${trip.ref_id}</strong><br>
            <span style="color: ${color}; font-weight: bold;">Status: ${trip.status.toUpperCase()}</span><br>
            <hr style="margin: 5px 0;">
            <strong>Driver:</strong> ${trip.driver ? trip.driver.name : 'N/A'}<br>
            ${trip.driver ? '<strong>Phone:</strong> ' + trip.driver.phone + '<br>' : ''}
            ${trip.driver ? '<strong>Vehicle:</strong> ' + trip.driver.vehicle + '<br>' : ''}
            <hr style="margin: 5px 0;">
            <strong>Customer:</strong> ${trip.customer ? trip.customer.name : 'N/A'}<br>
            ${trip.customer ? '<strong>Phone:</strong> ' + trip.customer.phone + '<br>' : ''}
            <hr style="margin: 5px 0;">
            <strong>Created:</strong> ${trip.created_at}
        </div>
    `;
    polyline.bindPopup(popupContent);

    // Add pickup marker
    if (trip.path.length > 0) {
        const pickupIcon = L.divIcon({
            className: 'custom-pickup-marker',
            html: '<div style="background-color: #28a745; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white;"></div>',
            iconSize: [12, 12],
            iconAnchor: [6, 6]
        });
        L.marker([trip.path[0].lat, trip.path[0].lng], { icon: pickupIcon })
            .bindPopup(`<strong>Pickup Point</strong><br>Trip #${trip.ref_id}`)
            .addTo(map);
    }

    // Add destination marker
    if (trip.path.length > 1) {
        const destIcon = L.divIcon({
            className: 'custom-destination-marker',
            html: '<div style="background-color: #dc3545; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white;"></div>',
            iconSize: [12, 12],
            iconAnchor: [6, 6]
        });
        L.marker([trip.path[trip.path.length - 1].lat, trip.path[trip.path.length - 1].lng], { icon: destIcon })
            .bindPopup(`<strong>Destination Point</strong><br>Trip #${trip.ref_id}`)
            .addTo(map);
    }

    tripPolylines.push(polyline);
}

/**
 * Add driver marker with animation
 */
function addDriverMarker(driver, location, tripStatus) {
    const latLng = L.latLng(location.lat, location.lng);

    if (driverMarkers[driver.id]) {
        // Animate existing marker
        animateMarker(driverMarkers[driver.id], latLng);
    } else {
        // Create new marker
        const marker = L.marker(latLng, {
            icon: createDriverIcon(tripStatus)
        });

        const popupContent = `
            <div style="min-width: 180px;">
                <strong>${driver.name}</strong><br>
                <strong>Phone:</strong> ${driver.phone}<br>
                <strong>Vehicle:</strong> ${driver.vehicle}<br>
                <hr style="margin: 5px 0;">
                <small><em>Location updated: ${location.updated_at}</em></small>
            </div>
        `;
        marker.bindPopup(popupContent);

        markerClusterGroup.addLayer(marker);
        driverMarkers[driver.id] = marker;
    }
}

/**
 * Create custom driver icon
 */
function createDriverIcon(tripStatus) {
    const color = STATUS_COLORS[tripStatus] || '#6c757d';

    return L.divIcon({
        className: 'custom-driver-marker',
        html: `<div style="background-color: ${color}; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });
}

/**
 * Animate marker movement smoothly
 */
function animateMarker(marker, newLatLng) {
    const startLatLng = marker.getLatLng();
    const duration = 14980; // 14.98 seconds (slightly less than polling interval)
    const startTime = performance.now();

    function moveMarker(timestamp) {
        const elapsed = timestamp - startTime;
        const progress = Math.min(elapsed / duration, 1);

        const lat = startLatLng.lat + (newLatLng.lat - startLatLng.lat) * progress;
        const lng = startLatLng.lng + (newLatLng.lng - startLatLng.lng) * progress;

        marker.setLatLng([lat, lng]);

        if (progress < 1) {
            requestAnimationFrame(moveMarker);
        }
    }

    requestAnimationFrame(moveMarker);
}

/**
 * Draw zone polygons
 */
function drawZonePolygons(polygons) {
    polygons.forEach(polygon => {
        const latLngs = polygon.map(point => [point.lat, point.lng]);
        L.polygon(latLngs, {
            color: '#3388ff',
            weight: 2,
            fillOpacity: 0.1
        }).addTo(map);
    });
}

/**
 * Start polling interval
 */
function startPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    pollingInterval = setInterval(fetchTripTrackingData, 15000); // 15 seconds
}

/**
 * Stop polling interval
 */
function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
}

/**
 * Clear all map data
 */
function clearAllData() {
    // Clear trip polylines
    tripPolylines.forEach(polyline => map.removeLayer(polyline));
    tripPolylines = [];

    // Clear driver markers
    markerClusterGroup.clearLayers();
    driverMarkers = {};

    // Reset map view
    map.setView([defaultLat, defaultLng], defaultZoom);
}

/**
 * Cleanup on page unload
 */
$(window).on('beforeunload', function() {
    stopPolling();
});

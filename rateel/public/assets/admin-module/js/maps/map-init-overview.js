"use strict";

// Customer Density Heat Map using OpenStreetMap/Leaflet (NO Google Maps)
console.log('Customer Density Heat Map Script Loaded (OpenStreetMap/Leaflet)');

let heatMapInstance = null;

function initializeHeatMap() {
    console.log('Initializing Heat Map...');
    
    // Check if Leaflet is loaded (NO Google Maps - using OpenStreetMap/Leaflet)
    if (typeof L === 'undefined') {
        console.error('Leaflet library not loaded. Waiting...');
        // Retry after a short delay
        setTimeout(function() {
            if (typeof L !== 'undefined') {
                initializeHeatMap();
            } else {
                console.error('Leaflet library failed to load');
            }
        }, 500);
        return;
    }
    
    // Ensure Google Maps is NOT being used
    if (typeof google !== 'undefined') {
        console.warn('Google Maps detected but not needed - using OpenStreetMap/Leaflet instead');
    }
    
    // Find the map element
    const mapElement = document.getElementById('map');
    if (!mapElement) {
        console.error('Map element not found');
        return;
    }
    
    console.log('Map element found:', mapElement);
    
    // Remove existing map if any
    if (heatMapInstance) {
        try {
            heatMapInstance.remove();
        } catch (e) {
            console.log('Error removing old map:', e);
        }
        heatMapInstance = null;
    }
    
    // Get data from attributes
    const lat = parseFloat(mapElement.getAttribute('data-lat')) || 30.0444;
    const lng = parseFloat(mapElement.getAttribute('data-lng')) || 31.2357;
    
    console.log('Map center:', lat, lng);
    
    // Parse markers
    let markers = [];
    try {
        const markersData = mapElement.getAttribute('data-markers');
        if (markersData) {
            markers = JSON.parse(markersData);
            console.log('Markers loaded:', markers.length);
        }
    } catch (e) {
        console.error('Error parsing markers:', e);
    }
    
    // Parse polygons
    let polygons = [];
    try {
        const polygonData = mapElement.getAttribute('data-polygon');
        if (polygonData) {
            polygons = JSON.parse(polygonData);
            console.log('Polygons loaded:', polygons.length);
        }
    } catch (e) {
        console.error('Error parsing polygons:', e);
    }
    
    // Remove spinner
    const spinner = mapElement.querySelector('.spinner-border');
    if (spinner) {
        spinner.parentElement.remove();
    }
    
    // Clear the map element
    mapElement.innerHTML = '';
    
    // Create the map
    try {
        heatMapInstance = L.map('map', {
            center: [lat, lng],
            zoom: 12,
            zoomControl: true
        });
        
        console.log('Map created successfully');
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(heatMapInstance);
        
        console.log('Tile layer added');
        
        // Add polygons (zones)
        if (polygons && polygons.length > 0) {
            polygons.forEach(function(coords) {
                if (Array.isArray(coords) && coords.length > 0) {
                    try {
                        const latLngs = coords.map(function(point) {
                            if (Array.isArray(point)) {
                                return [point[0], point[1]];
                            }
                            return [point.lat || point.latitude, point.lng || point.longitude];
                        });
                        
                        L.polygon(latLngs, {
                            color: '#000000',
                            weight: 2,
                            opacity: 0.3,
                            fillOpacity: 0.1
                        }).addTo(heatMapInstance);
                    } catch (e) {
                        console.warn('Error adding polygon:', e);
                    }
                }
            });
            console.log('Polygons added');
        }
        
        // Prepare heat map data for customer density visualization
        const heatData = [];
        
        // Count customers per location for better density visualization
        const locationCounts = {};
        
        // Add markers and prepare heat data
        if (markers && markers.length > 0) {
            const markerGroup = L.markerClusterGroup({
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false
            });
            
            markers.forEach(function(markerData) {
                if (markerData.position) {
                    const markerLat = parseFloat(markerData.position.lat);
                    const markerLng = parseFloat(markerData.position.lng);
                    
                    if (!isNaN(markerLat) && !isNaN(markerLng) && markerLat !== 0 && markerLng !== 0) {
                        // Round coordinates to group nearby customers (for density calculation)
                        const roundedLat = Math.round(markerLat * 1000) / 1000;
                        const roundedLng = Math.round(markerLng * 1000) / 1000;
                        const locationKey = roundedLat + ',' + roundedLng;
                        
                        // Count customers at this location
                        if (!locationCounts[locationKey]) {
                            locationCounts[locationKey] = {
                                lat: markerLat,
                                lng: markerLng,
                                count: 0
                            };
                        }
                        locationCounts[locationKey].count++;
                        
                        // Add marker
                        const marker = L.marker([markerLat, markerLng]);
                        if (markerData.title) {
                            marker.bindPopup(markerData.title);
                        }
                        markerGroup.addLayer(marker);
                    }
                }
            });
            
            heatMapInstance.addLayer(markerGroup);
            console.log('Customer markers added:', markers.length);
            
            // Convert location counts to heat data with intensity based on customer count
            Object.values(locationCounts).forEach(function(location) {
                // Intensity increases with customer count (capped at 1.0)
                const intensity = Math.min(location.count / 10, 1.0);
                heatData.push([location.lat, location.lng, intensity]);
            });
        }
        
        // Add heat layer for customer density
        if (heatData.length > 0 && typeof L.heatLayer !== 'undefined') {
            try {
                const heatLayer = L.heatLayer(heatData, {
                    radius: 30, // Slightly larger radius for better visibility
                    blur: 20,   // Slightly more blur for smoother visualization
                    maxZoom: 17,
                    max: 1.0,
                    gradient: {
                        0.0: 'blue',      // Low customer density
                        0.3: 'cyan',      // Medium-low
                        0.5: 'lime',      // Medium
                        0.7: 'yellow',    // Medium-high
                        0.85: 'orange',   // High
                        1.0: 'red'        // Very high customer density
                    }
                });
                heatLayer.addTo(heatMapInstance);
                console.log('Customer density heat layer added with', heatData.length, 'points');
            } catch (e) {
                console.error('Error adding heat layer:', e);
            }
        }
        
        // Invalidate size to ensure proper rendering
        setTimeout(function() {
            heatMapInstance.invalidateSize();
            console.log('Map size invalidated');
        }, 100);
        
        setTimeout(function() {
            heatMapInstance.invalidateSize();
        }, 500);
        
    } catch (e) {
        console.error('Error creating map:', e);
    }
}

// Make function globally available
window.initializeOverviewMaps = initializeHeatMap;
window.initializeHeatMap = initializeHeatMap; // Alias for compatibility

// Wait for all dependencies to be loaded before initializing
function waitForDependencies(callback, maxAttempts = 20) {
    let attempts = 0;
    const checkDependencies = function() {
        attempts++;
        if (typeof L !== 'undefined' && typeof L.heatLayer !== 'undefined' && document.getElementById('map')) {
            callback();
        } else if (attempts < maxAttempts) {
            setTimeout(checkDependencies, 100);
        } else {
            console.error('Failed to load dependencies after', maxAttempts, 'attempts');
        }
    };
    checkDependencies();
}

// Auto-initialize when page loads and dependencies are ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        waitForDependencies(function() {
            setTimeout(initializeHeatMap, 300);
        });
    });
} else {
    waitForDependencies(function() {
        setTimeout(initializeHeatMap, 300);
    });
}

console.log('Heat Map Script Ready');

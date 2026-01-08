"use strict";

console.log('Heat Map Script Loaded');

let heatMapInstance = null;

function initializeHeatMap() {
    console.log('Initializing Heat Map...');
    
    // Check if Leaflet is loaded
    if (typeof L === 'undefined') {
        console.error('Leaflet library not loaded');
        return;
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
        
        // Prepare heat map data
        const heatData = [];
        
        // Add markers
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
                        // Add to heat map data
                        heatData.push([markerLat, markerLng, 1.0]);
                        
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
            console.log('Markers added:', markers.length);
        }
        
        // Add heat layer
        if (heatData.length > 0 && typeof L.heatLayer !== 'undefined') {
            try {
                const heatLayer = L.heatLayer(heatData, {
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
                });
                heatLayer.addTo(heatMapInstance);
                console.log('Heat layer added with', heatData.length, 'points');
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

// Auto-initialize when page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initializeHeatMap, 500);
    });
} else {
    setTimeout(initializeHeatMap, 500);
}

console.log('Heat Map Script Ready');

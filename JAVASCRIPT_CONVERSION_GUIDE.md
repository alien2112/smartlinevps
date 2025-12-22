# JavaScript Conversion Guide: Google Maps → Leaflet + Geoapify

## Status Overview

### ✅ Backend: 100% Complete
All PHP backend code has been successfully migrated to use Geoapify API.

### ⚠️ Frontend JavaScript: Needs Conversion
The views now load Leaflet.js libraries, but the inline JavaScript still uses Google Maps API syntax.

---

## Files That Need JavaScript Conversion

### 1. Zone Management Views (High Priority)
These have complex inline JavaScript for polygon drawing:

**Files:**
- `Modules\ZoneManagement\Resources\views\admin\zone\index.blade.php` (lines with `google.maps`)
- `Modules\ZoneManagement\Resources\views\admin\zone\edit.blade.php` (lines with `google.maps`)
- `rateel\Modules\ZoneManagement\Resources\views\admin\zone\index.blade.php`
- `rateel\Modules\ZoneManagement\Resources\views\admin\zone\edit.blade.php`

**External JS:**
- `rateel\public\assets\admin-module\js\zone-management\zone\index.js`

### 2. Fleet Map (High Priority)
Real-time vehicle tracking:

**Files:**
- `Modules\AdminModule\Resources\views\fleet-map.blade.php`
- `rateel\Modules\AdminModule\Resources\views\fleet-map.blade.php`

**External JS:**
- `rateel\public\assets\admin-module\js\maps\fleet-map-init.js`

### 3. Dashboard Maps (Medium Priority)
General map overview:

**External JS:**
- `rateel\public\assets\admin-module\js\maps\map-init.js`
- `rateel\public\assets\admin-module\js\maps\map-init-overview.js`

### 4. Trip Details Maps (Medium Priority)
Route display on trip details:

**Files:**
- `Modules\TripManagement\Resources\views\admin\trip\partials\_trip-details-status.blade.php`
- `Modules\TripManagement\Resources\views\admin\refund\partials\_trip-details-status.blade.php`
- `rateel\Modules\TripManagement\Resources\views\admin\trip\partials\_trip-details-status.blade.php`
- `rateel\Modules\TripManagement\Resources\views\admin\refund\partials\_trip-details-status.blade.php`

### 5. Heat Maps (Lower Priority)
Heat map visualizations are already set up with Leaflet.heat library.

---

## Complete Conversion Reference

### Map Initialization

**Google Maps:**
```javascript
let map;
let myOptions = {
    zoom: 13,
    center: {lat: 23.757989, lng: 90.360587},
    mapTypeId: google.maps.MapTypeId.ROADMAP
};
map = new google.maps.Map(document.getElementById("map"), myOptions);
```

**Leaflet:**
```javascript
let map;
map = L.map('map').setView([23.757989, 90.360587], 13);

// Add Geoapify tile layer
L.tileLayer('https://maps.geoapify.com/v1/tile/osm-bright/{z}/{x}/{y}.png?apiKey={{ $map_key }}', {
    attribution: 'Powered by Geoapify | © OpenStreetMap',
    maxZoom: 20
}).addTo(map);
```

---

### Markers

**Google Maps:**
```javascript
let marker = new google.maps.Marker({
    position: {lat: 23.757989, lng: 90.360587},
    map: map,
    title: "My Location",
    icon: "path/to/icon.png"
});

// Set marker position
marker.setPosition(new google.maps.LatLng(lat, lng));

// Remove marker
marker.setMap(null);
```

**Leaflet:**
```javascript
let marker = L.marker([23.757989, 90.360587], {
    title: "My Location",
    icon: L.icon({
        iconUrl: "path/to/icon.png",
        iconSize: [32, 32],
        iconAnchor: [16, 32]
    })
}).addTo(map);

// Set marker position
marker.setLatLng([lat, lng]);

// Remove marker
map.removeLayer(marker);
// or
marker.remove();
```

---

### Info Windows / Popups

**Google Maps:**
```javascript
let infoWindow = new google.maps.InfoWindow({
    content: "<h3>Title</h3><p>Description</p>"
});

marker.addListener('click', function() {
    infoWindow.open(map, marker);
});
```

**Leaflet:**
```javascript
marker.bindPopup("<h3>Title</h3><p>Description</p>");

// Auto-open
marker.bindPopup("<h3>Title</h3><p>Description</p>").openPopup();

// Or manually
marker.on('click', function() {
    marker.openPopup();
});
```

---

### Polygons

**Google Maps:**
```javascript
let polygon = new google.maps.Polygon({
    paths: [
        {lat: 23.757989, lng: 90.360587},
        {lat: 23.758989, lng: 90.361587},
        {lat: 23.759989, lng: 90.362587}
    ],
    strokeColor: "#FF0000",
    strokeOpacity: 0.8,
    strokeWeight: 2,
    fillColor: "#FF0000",
    fillOpacity: 0.35,
    editable: true,
    draggable: true
});
polygon.setMap(map);

// Get polygon path
let path = polygon.getPath();
let coordinates = [];
for (let i = 0; i < path.getLength(); i++) {
    let point = path.getAt(i);
    coordinates.push([point.lat(), point.lng()]);
}

// Remove polygon
polygon.setMap(null);
```

**Leaflet:**
```javascript
let polygon = L.polygon([
    [23.757989, 90.360587],
    [23.758989, 90.361587],
    [23.759989, 90.362587]
], {
    color: '#FF0000',
    fillColor: '#FF0000',
    fillOpacity: 0.35,
    weight: 2
}).addTo(map);

// Get polygon coordinates
let coordinates = polygon.getLatLngs()[0].map(latlng => [latlng.lat, latlng.lng]);

// Remove polygon
map.removeLayer(polygon);
```

---

### Polylines (Routes)

**Google Maps:**
```javascript
let polyline = new google.maps.Polyline({
    path: [
        {lat: 23.757989, lng: 90.360587},
        {lat: 23.758989, lng: 90.361587}
    ],
    strokeColor: "#0000FF",
    strokeOpacity: 1.0,
    strokeWeight: 4
});
polyline.setMap(map);
```

**Leaflet:**
```javascript
let polyline = L.polyline([
    [23.757989, 90.360587],
    [23.758989, 90.361587]
], {
    color: '#0000FF',
    weight: 4,
    opacity: 1.0
}).addTo(map);
```

---

### Circles

**Google Maps:**
```javascript
let circle = new google.maps.Circle({
    center: {lat: 23.757989, lng: 90.360587},
    radius: 1000, // meters
    strokeColor: "#FF0000",
    fillColor: "#FF0000",
    fillOpacity: 0.2
});
circle.setMap(map);
```

**Leaflet:**
```javascript
let circle = L.circle([23.757989, 90.360587], {
    radius: 1000, // meters
    color: '#FF0000',
    fillColor: '#FF0000',
    fillOpacity: 0.2
}).addTo(map);
```

---

### Event Listeners

**Google Maps:**
```javascript
google.maps.event.addListener(map, 'click', function(event) {
    let lat = event.latLng.lat();
    let lng = event.latLng.lng();
    console.log(lat, lng);
});

marker.addListener('click', function() {
    // Handle marker click
});

polygon.addListener('click', function() {
    // Handle polygon click
});
```

**Leaflet:**
```javascript
map.on('click', function(e) {
    let lat = e.latlng.lat;
    let lng = e.latlng.lng;
    console.log(lat, lng);
});

marker.on('click', function() {
    // Handle marker click
});

polygon.on('click', function() {
    // Handle polygon click
});
```

---

### Map Controls & Methods

**Google Maps:**
```javascript
// Set center
map.setCenter({lat: 23.757989, lng: 90.360587});

// Set zoom
map.setZoom(15);

// Fit bounds
let bounds = new google.maps.LatLngBounds();
bounds.extend({lat: 23.757989, lng: 90.360587});
bounds.extend({lat: 23.758989, lng: 90.361587});
map.fitBounds(bounds);

// Pan to
map.panTo({lat: 23.757989, lng: 90.360587});
```

**Leaflet:**
```javascript
// Set center
map.setView([23.757989, 90.360587], map.getZoom());

// Set zoom
map.setZoom(15);

// Fit bounds
let bounds = L.latLngBounds([
    [23.757989, 90.360587],
    [23.758989, 90.361587]
]);
map.fitBounds(bounds);

// Pan to
map.panTo([23.757989, 90.360587]);
```

---

### Drawing Tools (Polygon Drawing)

**Google Maps (Drawing Manager):**
```javascript
let drawingManager = new google.maps.drawing.DrawingManager({
    drawingMode: google.maps.drawing.OverlayType.POLYGON,
    drawingControl: true,
    drawingControlOptions: {
        position: google.maps.ControlPosition.TOP_CENTER,
        drawingModes: [google.maps.drawing.OverlayType.POLYGON]
    }
});
drawingManager.setMap(map);

google.maps.event.addListener(drawingManager, 'overlaycomplete', function(event) {
    if (event.type === 'polygon') {
        let polygon = event.overlay;
        let path = polygon.getPath();
    }
});
```

**Leaflet (Leaflet.draw - Already included in your views!):**
```javascript
// Create a feature group for drawn items
let drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

// Initialize draw control
let drawControl = new L.Control.Draw({
    draw: {
        polygon: {
            allowIntersection: false,
            showArea: true,
            drawError: {
                color: '#e1e100',
                message: '<strong>Error:</strong> Shape edges cannot cross!'
            }
        },
        polyline: false,
        circle: false,
        rectangle: false,
        marker: false,
        circlemarker: false
    },
    edit: {
        featureGroup: drawnItems,
        remove: true
    }
});
map.addControl(drawControl);

// Handle draw created event
map.on(L.Draw.Event.CREATED, function(event) {
    let layer = event.layer;
    let coordinates = layer.getLatLngs()[0].map(latlng => ({
        lat: latlng.lat,
        lng: latlng.lng
    }));
    
    drawnItems.addLayer(layer);
    
    // Store coordinates in your form field
    $('#coordinates').val(JSON.stringify(coordinates));
});

// Handle draw edited event
map.on(L.Draw.Event.EDITED, function(event) {
    let layers = event.layers;
    layers.eachLayer(function(layer) {
        let coordinates = layer.getLatLngs()[0].map(latlng => ({
            lat: latlng.lat,
            lng: latlng.lng
        }));
        $('#coordinates').val(JSON.stringify(coordinates));
    });
});

// Handle draw deleted event
map.on(L.Draw.Event.DELETED, function(event) {
    $('#coordinates').val('');
});
```

---

### Geolocation

**Google Maps:**
```javascript
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
        let pos = {
            lat: position.coords.latitude,
            lng: position.coords.longitude
        };
        map.setCenter(pos);
    });
}
```

**Leaflet:**
```javascript
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
        let pos = [position.coords.latitude, position.coords.longitude];
        map.setView(pos, 15);
        L.marker(pos).addTo(map);
    });
}

// Or use Leaflet's built-in locate
map.locate({setView: true, maxZoom: 15});

map.on('locationfound', function(e) {
    L.marker(e.latlng).addTo(map);
});
```

---

### Marker Clustering

**Google Maps (MarkerClusterer):**
```javascript
let markerCluster = new MarkerClusterer(map, markers, {
    imagePath: 'path/to/cluster/images'
});
```

**Leaflet (Leaflet.markercluster - Already included!):**
```javascript
let markers = L.markerClusterGroup();

// Add markers to cluster group
for (let i = 0; i < locations.length; i++) {
    let marker = L.marker([locations[i].lat, locations[i].lng]);
    marker.bindPopup(locations[i].name);
    markers.addLayer(marker);
}

map.addLayer(markers);
```

---

### Heat Maps

**Google Maps (Heatmap Layer):**
```javascript
let heatmapData = [
    {location: new google.maps.LatLng(23.757989, 90.360587), weight: 5},
    {location: new google.maps.LatLng(23.758989, 90.361587), weight: 3}
];

let heatmap = new google.maps.visualization.HeatmapLayer({
    data: heatmapData
});
heatmap.setMap(map);
```

**Leaflet (Leaflet.heat - Already included!):**
```javascript
// Array format: [lat, lng, intensity]
let heatmapData = [
    [23.757989, 90.360587, 5],
    [23.758989, 90.361587, 3]
];

let heat = L.heatLayer(heatmapData, {
    radius: 25,
    blur: 15,
    maxZoom: 17
}).addTo(map);
```

---

### Address Search / Autocomplete

You've already added the Geoapify Address Search plugin! Here's how to use it:

```javascript
// Add address search control (already in your dashboard map)
const addressSearchControl = L.control.addressSearch('YOUR_API_KEY', {
    position: 'topleft',
    placeholder: 'Enter address...',
    resultCallback: (address) => {
        if (address && address.lat && address.lon) {
            map.setView([address.lat, address.lon], 15);
            L.marker([address.lat, address.lon])
                .addTo(map)
                .bindPopup(address.formatted)
                .openPopup();
        }
    },
    suggestionsCallback: (suggestions) => {
        console.log('Suggestions:', suggestions);
    }
});
map.addControl(addressSearchControl);
```

---

## Quick Conversion Checklist for Each File

### For Zone Index/Edit Pages:

1. ✅ Replace map initialization
2. ✅ Replace DrawingManager with L.Control.Draw
3. ✅ Update polygon creation/editing
4. ✅ Update coordinate extraction
5. ✅ Replace place search with Geoapify search control

### For Fleet Map:

1. ✅ Replace map initialization
2. ✅ Replace marker creation
3. ✅ Replace marker updates (real-time tracking)
4. ✅ Replace info windows with popups
5. ✅ Replace marker clustering if used

### For Trip Details:

1. ✅ Replace map initialization
2. ✅ Replace polyline drawing (route display)
3. ✅ Replace markers (pickup/dropoff points)
4. ✅ Replace bounds fitting

---

## Example: Complete Zone Drawing Page Conversion

Here's a complete example for the zone index page:

**Before (Google Maps):**
```javascript
function initialize() {
    let map = new google.maps.Map(document.getElementById("map-canvas"), {
        center: {lat: 23.757989, lng: 90.360587},
        zoom: 13,
        mapTypeId: google.maps.MapTypeId.ROADMAP
    });
    
    let drawingManager = new google.maps.drawing.DrawingManager({
        drawingMode: google.maps.drawing.OverlayType.POLYGON,
        drawingControl: true
    });
    drawingManager.setMap(map);
    
    google.maps.event.addListener(drawingManager, 'overlaycomplete', function(event) {
        let polygon = event.overlay;
        let path = polygon.getPath();
        let coordinates = [];
        for (let i = 0; i < path.getLength(); i++) {
            coordinates.push({
                lat: path.getAt(i).lat(),
                lng: path.getAt(i).lng()
            });
        }
        $('#coordinates').val(JSON.stringify(coordinates));
    });
}
```

**After (Leaflet):**
```javascript
function initialize() {
    let map = L.map('map-canvas').setView([23.757989, 90.360587], 13);
    
    L.tileLayer('https://maps.geoapify.com/v1/tile/osm-bright/{z}/{x}/{y}.png?apiKey={{ $map_key }}', {
        attribution: '© Geoapify | © OpenStreetMap',
        maxZoom: 20
    }).addTo(map);
    
    let drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);
    
    let drawControl = new L.Control.Draw({
        draw: {
            polygon: {
                allowIntersection: false,
                showArea: true
            },
            polyline: false,
            circle: false,
            rectangle: false,
            marker: false
        },
        edit: {
            featureGroup: drawnItems
        }
    });
    map.addControl(drawControl);
    
    map.on(L.Draw.Event.CREATED, function(event) {
        let layer = event.layer;
        let coordinates = layer.getLatLngs()[0].map(latlng => ({
            lat: latlng.lat,
            lng: latlng.lng
        }));
        
        drawnItems.addLayer(layer);
        $('#coordinates').val(JSON.stringify(coordinates));
    });
    
    map.on(L.Draw.Event.EDITED, function(event) {
        let layers = event.layers;
        layers.eachLayer(function(layer) {
            let coordinates = layer.getLatLngs()[0].map(latlng => ({
                lat: latlng.lat,
                lng: latlng.lng
            }));
            $('#coordinates').val(JSON.stringify(coordinates));
        });
    });
}
```

---

## Testing After Conversion

For each converted file, test:

1. ✅ Map loads and displays
2. ✅ Tiles load from Geoapify
3. ✅ Markers display correctly
4. ✅ Click events work
5. ✅ Popups/info windows display
6. ✅ Drawing tools work (if applicable)
7. ✅ Data saves correctly
8. ✅ No console errors

---

## Need Help?

- **Leaflet Docs**: https://leafletjs.com/reference.html
- **Leaflet.draw**: https://leaflet.github.io/Leaflet.draw/docs/leaflet-draw-latest.html
- **Leaflet.heat**: https://github.com/Leaflet/Leaflet.heat
- **Leaflet.markercluster**: https://github.com/Leaflet/Leaflet.markercluster
- **Geoapify Leaflet Plugin**: https://apidocs.geoapify.com/docs/maps/map-tiles/

---

**Note**: All the required Leaflet libraries and plugins are already included in your updated view files. You just need to convert the JavaScript code from Google Maps syntax to Leaflet syntax!


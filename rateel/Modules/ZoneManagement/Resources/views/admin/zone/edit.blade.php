@extends('adminmodule::layouts.master')

@section('title', translate('Edit_Zone_Setup'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3 justify-content-between mb-4">
                <h2 class="fs-22 text-capitalize">{{ translate('zone_setup') }}</h2>
            </div>
            <form id="zone_form" action="{{ route('admin.zone.update', ['id'=>$zone->id]) }}"
                  enctype="multipart/form-data" method="POST">
                @csrf
                @method('put')
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row justify-content-between">
                                    <div class="col-lg-5 col-xl-4 mb-5 mb-lg-0">
                                        <h5 class="text-primary text-uppercase mb-4">{{ translate('instructions') }}</h5>
                                        <div class="d-flex flex-column">
                                            <p>{{ translate('create_zone_by_click_on_map_and_connect_the_dots_together') }}</p>

                                            <div class="media mb-2 gap-3 align-items-center">
                                                <img
                                                    src="{{asset('public/assets/admin-module/img/map-drag.png') }}"
                                                    alt="">
                                                <div class="media-body ">
                                                    <p>{{ translate('use_this_to_drag_map_to_find_proper_area') }}</p>
                                                </div>
                                            </div>

                                            <div class="media gap-3 align-items-center">
                                                <img
                                                    src="{{asset('public/assets/admin-module/img/map-draw.png') }}"
                                                    alt="">
                                                <div class="media-body ">
                                                    <p>{{ translate('click_this_icon_to_start_pin_points_in_the_map_and_connect_them_
                                                            to_draw_a_
                                                            zone_._Minimum_3_points_required') }}</p>
                                                </div>
                                            </div>
                                            <div class="map-img mt-4">
                                                <img
                                                    src="{{ asset('public/assets/admin-module/img/instructions.gif') }}"
                                                    alt="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-7">
                                        <div class="mb-4">
                                            <label for="zone_name"
                                                   class="form-label text-capitalize">{{ translate('zone_name') }} <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="name" id="zone_name"
                                                   value="{{ $zone->name }}" placeholder="{{ translate('ex') }}: {{ translate('Dhanmondi') }}" required>
                                        </div>

                                        <div class="form-group mb-3 d-none">
                                            <label class="input-label"
                                                   for="coordinates">{{ translate('coordinates') }}
                                                <span
                                                    class="input-label-secondary">{{ translate('draw_your_zone_on_the_map') }}</span>
                                            </label>
                                            <textarea type="text" rows="8" name="coordinates" id="coordinates" class="form-control" readonly>@foreach($zone->coordinates[0]->toArray()['coordinates'] as $key=>$coords)<?php if (count($zone->coordinates[0]->toArray()['coordinates']) != $key + 1) {if ($key != 0) echo(','); ?>({{$coords[1]}}, {{$coords[0]}})<?php } ?>@endforeach</textarea>
                                        </div>

                                        <!-- Start Map -->
                                        <div class="map-warper overflow-hidden rounded-5">
                                            <input id="pac-input" class="controls rounded map-search-box"
                                                   title="{{ translate('search_your_location_here') }}" type="text"
                                                   placeholder="{{ translate('search_here') }}"/>
                                            <div id="map-canvas" class="map-height"></div>
                                        </div>
                                        <!-- End Map -->
                                    </div>

                                    <div class="d-flex justify-content-end gap-3 mt-3">
                                        <button class="btn btn-secondary" type="reset" id="reset_btn">
                                            {{ translate('reset') }}
                                        </button>
                                        <button class="btn btn-primary" type="submit">
                                            {{ translate('update') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <!-- Leaflet CSS and JS - OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet Draw Plugin for drawing polygons -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    <!-- Leaflet Control Geocoder for search -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.css" />
    <script src="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.js"></script>
    
    <style>
        #map-canvas { z-index: 1; }
        .leaflet-control-geocoder { width: 300px; }
        .leaflet-control-geocoder-form input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    </style>
    
    <script>
        "use strict";
        
        let map; // Global Leaflet map
        let drawnItems; // Layer group for drawn polygons
        let lastPolygon = null;
        let existingZoneLayer; // Layer for the current zone being edited
        let otherZonesLayer; // Layer for other zones

        function auto_grow() {
            let element = document.getElementById("coordinates");
            if (element) {
                element.style.height = "5px";
                element.style.height = (element.scrollHeight) + "px";
            }
        }

        function initialize() {
            // Get zone center from PHP
            let centerLat = {{trim(explode(' ',$zone->center)[1], 'POINT()') }};
            let centerLng = {{trim(explode(' ',$zone->center)[0], 'POINT()') }};

            // Initialize Leaflet map with OpenStreetMap tiles
            map = L.map('map-canvas').setView([centerLat, centerLng], 13);

            // Add OpenStreetMap tile layer (FREE - no API key needed)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // Initialize layer groups
            drawnItems = new L.FeatureGroup();
            map.addLayer(drawnItems);
            
            existingZoneLayer = new L.FeatureGroup();
            map.addLayer(existingZoneLayer);
            
            otherZonesLayer = new L.FeatureGroup();
            map.addLayer(otherZonesLayer);

            // Draw the existing zone polygon
            const existingCoords = [
                @foreach($area['coordinates'] as $coords)
                [{{$coords[1]}}, {{$coords[0]}}],
                @endforeach
            ];

            let existingPolygon = L.polygon(existingCoords, {
                color: '#000000',
                weight: 2,
                opacity: 0.2,
                fillColor: '#000000',
                fillOpacity: 0.05
            });
            existingZoneLayer.addLayer(existingPolygon);

            // Fit map to the existing zone
            map.fitBounds(existingPolygon.getBounds(), { padding: [20, 20] });

            // Initialize Leaflet Draw control
            let drawControl = new L.Control.Draw({
                position: 'topright',
                draw: {
                    polygon: {
                        allowIntersection: false,
                        drawError: {
                            color: '#e1e100',
                            message: '<strong>Error:</strong> Shape edges cannot cross!'
                        },
                        shapeOptions: {
                            color: '#0066ff',
                            fillColor: '#0066ff',
                            fillOpacity: 0.2,
                            weight: 2
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

            // Add geocoder search control (uses Nominatim - FREE)
            let geocoder = L.Control.geocoder({
                defaultMarkGeocode: false,
                placeholder: '{{ translate("search_here") }}',
                geocoder: L.Control.Geocoder.nominatim({
                    geocodingQueryParams: {
                        countrycodes: 'eg', // Prioritize Egypt
                        limit: 5
                    }
                })
            }).on('markgeocode', function(e) {
                let bbox = e.geocode.bbox;
                let poly = L.polygon([
                    bbox.getSouthEast(),
                    bbox.getNorthEast(),
                    bbox.getNorthWest(),
                    bbox.getSouthWest()
                ]);
                map.fitBounds(poly.getBounds());
                
                // Add a temporary marker
                L.marker(e.geocode.center).addTo(map)
                    .bindPopup(e.geocode.name)
                    .openPopup();
            }).addTo(map);

            // Handle polygon creation
            map.on(L.Draw.Event.CREATED, function (event) {
                let layer = event.layer;

                // Remove previous drawn polygon if exists
                if (lastPolygon) {
                    drawnItems.removeLayer(lastPolygon);
                }

                drawnItems.addLayer(layer);
                lastPolygon = layer;

                // Convert to coordinate string format expected by backend
                let coords = layer.getLatLngs()[0].map(function(latlng) {
                    return '(' + latlng.lat + ', ' + latlng.lng + ')';
                }).join(',');
                
                $('#coordinates').val(coords);
                auto_grow();
            });

            // Handle polygon edit
            map.on(L.Draw.Event.EDITED, function (event) {
                let layers = event.layers;
                layers.eachLayer(function (layer) {
                    let coords = layer.getLatLngs()[0].map(function(latlng) {
                        return '(' + latlng.lat + ', ' + latlng.lng + ')';
                    }).join(',');
                    $('#coordinates').val(coords);
                });
            });

            // Handle polygon delete
            map.on(L.Draw.Event.DELETED, function (event) {
                $('#coordinates').val('');
                lastPolygon = null;
            });

            // Load other zones
            set_all_zones();
        }

        function set_all_zones() {
            $.get({
                url: '{{route('admin.zone.get-zones',[$zone->id])}}',
                dataType: 'json',
                success: function (data) {
                    // Clear other zones layer
                    otherZonesLayer.clearLayers();
                    
                    for (let i = 0; i < data.length; i++) {
                        // Convert Google Maps format to Leaflet format
                        let coords = data[i].map(function(point) {
                            return [point.lat, point.lng];
                        });
                        
                        let polygon = L.polygon(coords, {
                            color: '#FF0000',
                            weight: 2,
                            opacity: 0.8,
                            fillColor: '#FF0000',
                            fillOpacity: 0.1
                        });
                        
                        otherZonesLayer.addLayer(polygon);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading zones:', error);
                }
            });
        }

        // Initialize map when DOM is ready
        $(document).ready(function () {
            initialize();
            auto_grow();
            
            // Prevent form submit on Enter key
            $("#zone_form").on('keydown', function (e) {
                if (e.keyCode === 13) {
                    e.preventDefault();
                }
            });
        });

        // Reset button handler
        $('#reset_btn').click(function () {
            $('#zone_name').val('{{ $zone->name }}');
            if (lastPolygon) {
                drawnItems.removeLayer(lastPolygon);
                lastPolygon = null;
            }
            // Reset coordinates to original
            $('#coordinates').val('@foreach($zone->coordinates[0]->toArray()['coordinates'] as $key=>$coords)<?php if (count($zone->coordinates[0]->toArray()['coordinates']) != $key + 1) {if ($key != 0) echo(','); ?>({{$coords[1]}}, {{$coords[0]}})<?php } ?>@endforeach');
        });
    </script>
@endpush

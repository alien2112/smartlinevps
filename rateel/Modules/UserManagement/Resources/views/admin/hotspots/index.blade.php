@section('title', translate('User_Hotspots'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <style>
        #map { height: 600px; width: 100%; border-radius: 12px; }
    </style>
@endpush

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 text-capitalize mb-3">{{ translate('User_Saved_Locations_Map') }}</h2>

            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-3">{{ translate('Visualize_where_your_customers_live_and_work._Use_this_to_plan_driver_allocation.') }}</p>
                    <div id="map"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    @php
        $heatPoints = $hotspots->map(function ($point) {
            return [(float)$point->latitude, (float)$point->longitude, 0.5];
        })->values();
    @endphp
    <script>
        var heatPoints = @json($heatPoints);
        var defaultCenter = [30.0444, 31.2357]; // Default to Cairo, Egypt
        var center = heatPoints.length > 0
            ? [heatPoints[0][0], heatPoints[0][1]]
            : defaultCenter;
        var zoomLevel = heatPoints.length > 0 ? 11 : 10;
        var map = L.map('map').setView(center, zoomLevel);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        if (heatPoints.length > 0) {
            L.heatLayer(heatPoints, {radius: 25}).addTo(map);
        }
    </script>
@endpush

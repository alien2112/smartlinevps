@section('title', translate('trip_tracking'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
    <!-- Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Leaflet MarkerCluster -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    <!-- DateRangePicker -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <script src="https://cdn.jsdelivr.net/npm/moment/min/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
@endpush

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4>{{ translate('trip_tracking') }}</h4>
                    <p class="mb-0">{{ translate('track_trip_paths_and_live_driver_locations') }}</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="liveTrackingToggle" checked>
                    <label class="form-check-label" for="liveTrackingToggle">
                        <strong>{{ translate('live_tracking') }}</strong>
                    </label>
                </div>
            </div>

            <div class="card-body">
                <!-- Filters Row -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <label class="form-label">{{ translate('filter_by_zone') }}</label>
                        <select class="form-select" id="zoneFilter">
                            <option value="">{{ translate('all_zones') }}</option>
                            @foreach($zones as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <label class="form-label">{{ translate('search_driver_vehicle') }}</label>
                        <input type="text" class="form-control" id="driverSearch"
                               placeholder="{{ translate('driver_name_or_phone') }}">
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <label class="form-label">{{ translate('trip_status') }}</label>
                        <select class="form-select" id="statusFilter">
                            <option value="">{{ translate('all_statuses') }}</option>
                            <option value="pending">{{ translate('pending') }}</option>
                            <option value="accepted">{{ translate('accepted') }}</option>
                            <option value="ongoing">{{ translate('ongoing') }}</option>
                            <option value="completed">{{ translate('completed') }}</option>
                            <option value="cancelled">{{ translate('cancelled') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <label class="form-label">{{ translate('date_range') }}</label>
                        <input type="text" class="form-control" id="dateRangePicker" />
                    </div>
                    <div class="col-lg-1 col-md-2 col-sm-6 d-flex align-items-end">
                        <button class="btn btn-primary w-100" id="applyFilters">
                            {{ translate('apply') }}
                        </button>
                    </div>
                </div>

                <!-- Map Container -->
                <div id="tripTrackingMap" style="height: 600px; width: 100%; border-radius: 8px; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                    <div id="mapLoadingMessage" style="text-align: center; color: #6c757d;">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading map...</p>
                        <small>If map doesn't load, check browser console (F12) for errors</small>
                    </div>
                </div>

                <!-- Legend -->
                <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                    <strong>{{ translate('legend') }}:</strong>
                    <span class="badge px-3 py-2" style="background-color: #007bff;">{{ translate('pending') }}</span>
                    <span class="badge px-3 py-2" style="background-color: #28a745;">{{ translate('accepted') }}</span>
                    <span class="badge px-3 py-2" style="background-color: #ffc107; color: #000;">{{ translate('ongoing') }}</span>
                    <span class="badge px-3 py-2" style="background-color: #17a2b8;">{{ translate('completed') }}</span>
                    <span class="badge px-3 py-2" style="background-color: #dc3545;">{{ translate('cancelled') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('css_or_js2')
    <script>
        // Pass Laravel variables to JavaScript (MUST be defined BEFORE loading the script)
        const tripTrackingDataUrl = "{{ route('admin.trip-tracking-data') }}";
        const defaultLat = 30.0444;
        const defaultLng = 31.2357;
        const defaultZoom = 6;
        console.log('Trip Tracking Variables Loaded:', {
            tripTrackingDataUrl,
            defaultLat,
            defaultLng,
            defaultZoom
        });
    </script>
    <script src="{{ asset('public/assets/admin-module/js/maps/trip-tracking-init.js') }}?v={{ time() }}"></script>
@endpush

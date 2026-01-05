@section('title', translate('fleet_map'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
    <!-- Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet MarkerCluster -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <!-- Geoapify Address Search Plugin -->
    <script src="https://unpkg.com/@geoapify/leaflet-address-search-plugin@^1/dist/L.Control.GeoapifyAddressSearch.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@geoapify/leaflet-address-search-plugin@^1/dist/L.Control.GeoapifyAddressSearch.min.css" />
@endpush

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex gap-2 flex-column flex-sm-row justify-content-between">
                    <div class="w-100 max-w-299px">
                        <h4>{{translate('User Live View')}}</h4>
                        <p>{{translate("Monitor your users from here")}}</p>
                    </div>
                    <div class="get-zone-message">
                        @include('adminmodule::partials.fleet-map._safety-alert-get-zone-message')
                    </div>
                </div>
                <div class="card-body tab-filter-container">
                    <div class="border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                        {{-- Tab Menu --}}
                        <ul class="nav d-inline-flex nav--tabs-2 rounded bg-white align-items-center mt-2"
                            id="zone-tab-menu">
                            <li class="nav-item">
                                <a href="{{route('admin.fleet-map',['type' => ALL_DRIVER, 'zone_id' => request('zone_id')])}}"
                                   class="nav-link text-capitalize {{request('type') == ALL_DRIVER ? 'active' : ''}}"
                                   data-tab-target="all-driver">{{translate("All Drivers")}}</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{route('admin.fleet-map',['type' => DRIVER_ON_TRIP, 'zone_id' => request('zone_id')])}}"
                                   class="nav-link text-capitalize {{request('type') == DRIVER_ON_TRIP ? 'active' : ''}}"
                                   data-tab-target="trip-driver">{{translate("On-trip")}}</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{route('admin.fleet-map',['type' => DRIVER_IDLE, 'zone_id' => request('zone_id')])}}"
                                   class="nav-link text-capitalize {{request('type') == DRIVER_IDLE ? 'active' : ''}}"
                                   data-tab-target="idle-driver">{{translate("Idle")}}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-capitalize">|</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{route('admin.fleet-map',['type' => ALL_CUSTOMER, 'zone_id' => request('zone_id')])}}"
                                   class="nav-link text-capitalize {{request('type') == ALL_CUSTOMER ? 'active' : ''}}"
                                   data-tab-target="customer">{{translate("Customers")}}</a>
                            </li>
                        </ul>
                        <form action="{{request()->fullUrl()}}" id="zoneSubmitForm" class="pb-1">
                            <div class="">
                                <select class="js-select-custom min-w-200 h-35" name="zone_id" id="selectZone">
                                    @if(count($zones)>0)
                                        @foreach($zones as $key =>$zone)
                                            <option value="{{$zone->id}}"
                                                    {{request('zone_id') == $zone->id ? "selected" :""}} data-show-shield="{{ in_array($zone->id, $safetyAlertZones) ? 'true' : '' }}">{{$zone->name}}</option>
                                        @endforeach
                                    @else
                                        <option selected disabled>{{translate("zone_not_found")}}</option>
                                    @endif
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="zone-lists d-flex flex-wrap gap-3">
                        <div class="zone-lists__left">
                            <div id="zone-tab-content">
                                <div>
                                    @if(request('type') == ALL_DRIVER)
                                        <div data-tab-type="all-driver">
                                            <h4 class="mb-2">{{translate("Driver List")}}</h4>
                                            <form action="javascript:;" class="search-form search-form_style-two"
                                                  method="GET">
                                                <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                    <input type="text" class="theme-input-style search-form__input"
                                                           value="{{ request('search') }}" name="search" id="search"
                                                           placeholder="{{ translate('search_driver') }}">
                                                </div>
                                                <button type="submit" class="btn btn-primary search-submit"
                                                        data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                                            </form>
                                            <ul class="zone-list">
                                                @include('adminmodule::partials.fleet-map._fleet-map-driver-list')
                                            </ul>
                                        </div>
                                    @endif
                                    @if(request('type') == DRIVER_ON_TRIP)
                                        <div data-tab-type="trip-driver">
                                            <h4 class="mb-2">{{translate("On Trip Driver")}}</h4>
                                            <form action="javascript:;" class="search-form search-form_style-two"
                                                  method="GET">
                                                <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                    <input type="text" class="theme-input-style search-form__input"
                                                           value="{{ request('search') }}" name="search" id="search"
                                                           placeholder="{{ translate('search_driver') }}">
                                                </div>
                                                <button type="submit" class="btn btn-primary search-submit"
                                                        data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                                            </form>
                                            <ul class="zone-list">
                                                @include('adminmodule::partials.fleet-map._fleet-map-driver-list')
                                            </ul>
                                        </div>

                                    @endif
                                    @if(request('type') == DRIVER_IDLE)
                                        <div data-tab-type="idle-driver">
                                            <h4 class="mb-2">{{translate("Idle Driver")}}</h4>
                                            <form action="javascript:;"
                                                  class="search-form search-form_style-two" method="GET">
                                                <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                    <input type="text" class="theme-input-style search-form__input"
                                                           value="{{ request('search') }}" name="search" id="search"
                                                           placeholder="{{ translate('search_driver') }}">
                                                </div>
                                                <button type="submit" class="btn btn-primary search-submit"
                                                        data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                                            </form>
                                            <ul class="zone-list">
                                                @include('adminmodule::partials.fleet-map._fleet-map-driver-list')
                                            </ul>
                                        </div>

                                    @endif
                                    @if(request('type') == ALL_CUSTOMER)
                                        <div data-tab-type="customer">
                                            <h4 class="mb-2">{{translate("Customer List")}}</h4>
                                            <form action="javascript:;" class="search-form search-form_style-two"
                                                  method="GET">
                                                <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                    <input type="text" class="theme-input-style search-form__input"
                                                           value="{{ request('search') }}" name="search" id="search"
                                                           placeholder="{{ translate('search_customer') }}">
                                                </div>
                                                <button type="submit" class="btn btn-primary search-submit"
                                                        data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                                            </form>
                                            <ul class="zone-list">
                                                @include('adminmodule::partials.fleet-map._fleet-map-customer-list')
                                            </ul>
                                        </div>
                                    @endif

                                </div>
                            </div>

                            {{-- Driver Details --}}
                            <div id="userDetails">
                            </div>
                        </div>
                        <div class="zone-lists__map" id="partialFleetMap">
                            @include('adminmodule::partials.fleet-map._fleet-map-view')
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Overlay --}}
        <div class="js-select-overlay">
            <div class="inner-div">
                <select class="js-select">
                    <option value="">{{count($zones)?$zones[0]?->name:translate("zone_not_found")}}</option>
                </select>
                <div class="mt-2">
                    {{translate('From here select your zone and see the filtered data')}}
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="userId">
@endsection

@push('script')
    <script>
        "use strict";
        $(document).ready(function () {
            function formatState(state) {
                if (!state.id) {
                    return state.text;
                }
                var shouldShowImage = $(state.element).data('show-shield');
                if (shouldShowImage) {
                    var $state = $('<div class="d-flex align-items-center gap-2 justify-content-between">' +
                        state.text +
                        '<img src="{{asset('/public/assets/admin-module/img/shield.svg')}}" class="svg" alt="" />' +
                        '</div>');
                    return $state;
                }
                return state.text;
            }

            $(".js-select-custom").select2({
                templateResult: formatState
            });

            let zoneMessageHide = $('.zone-message-hide');
            let zoneMessage = $('.zone-message');
            let showZoneMessage = sessionStorage.getItem('showZoneMessage');
            if (showZoneMessage) {
                zoneMessage.addClass('invisible');
            } else {
                zoneMessage.removeClass('invisible');
            }
            zoneMessageHide.on('click', function () {
                zoneMessage.addClass('invisible');
                sessionStorage.setItem('showZoneMessage', 'false');
            });
        });

        // Leaflet Map Implementation (OpenStreetMap)
        $(document).ready(function () {
            let map = null;
            let bounds = null;
            let polygons = [];
            let markerCluster = null;
            let markers = [];
            let markersById = new Map();
            let currentPopup = null;
            let currentlyOpenMarkerId = null;
            let isSingleView = false;
            let singleInterval, doubleInterval;
            let searchMarkers = [];

            // Default map settings from DB or fallback
            const defaultLat = {{ app_setting('map.default_center_lat', 30.0444) }};
            const defaultLng = {{ app_setting('map.default_center_lng', 31.2357) }};
            const defaultZoom = {{ app_setting('map.default_zoom', 12) }};
            const tileUrl = @json(app_setting('map.tile_provider_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'));

            // Safety alert handling
            let currentUrl = window.location.href;
            let substrings = ['all-customer', 'driver-on-trip', 'driver-idle'];
            let found = substrings.some(substring => currentUrl.includes(substring));
            let safetyAlertUserDetailsStatus = localStorage.getItem('safetyAlertUserDetailsStatus');
            let safetyAlertUserId = localStorage.getItem('safetyAlertUserId');
            if (localStorage.getItem('safetyAlertUserIdFromTrip')) {
                safetyAlertUserId = localStorage.getItem('safetyAlertUserIdFromTrip');
            }

            if (found && safetyAlertUserId && safetyAlertUserDetailsStatus) {
                loadUserDetails(safetyAlertUserId);
                fetchSingleModelUpdate();
                localStorage.removeItem('safetyAlertUserDetailsStatus');
                localStorage.removeItem('safetyAlertUserIdFromTrip');
            }

            function initMap(mapSelector, lat, lng, title, markersData, input, polygonData = []) {
                let zoomValue = 13;
                if (lat == 0 && lng == 0) {
                    zoomValue = 2;
                    lat = defaultLat;
                    lng = defaultLng;
                }

                // Initialize Leaflet map
                map = L.map(mapSelector, {
                    center: [lat, lng],
                    zoom: zoomValue,
                    zoomControl: true
                });

                // Add OpenStreetMap tile layer
                L.tileLayer(tileUrl, {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19
                }).addTo(map);

                // Initialize marker cluster group
                markerCluster = L.markerClusterGroup({
                    chunkedLoading: true,
                    maxClusterRadius: 50,
                    spiderfyOnMaxZoom: true,
                    showCoverageOnHover: false,
                    zoomToBoundsOnClick: true
                });
                map.addLayer(markerCluster);

                // Initialize bounds
                bounds = L.latLngBounds();

                // Add zone polygons
                if (zoomValue == 13 && polygonData.length > 0) {
                    for (let i = 0; i < polygonData.length; i++) {
                        // Convert Google Maps format {lat, lng} to Leaflet format [lat, lng]
                        const coords = polygonData[i].map(point => [point.lat, point.lng]);
                        const polygon = L.polygon(coords, {
                            color: '#000000',
                            weight: 2,
                            opacity: 0.2,
                            fillColor: '#000000',
                            fillOpacity: 0.05
                        }).addTo(map);
                        polygons.push(polygon);
                        bounds.extend(polygon.getBounds());
                    }
                    if (bounds.isValid()) {
                        map.fitBounds(bounds, { padding: [20, 20] });
                    }
                }

                // Add search control using Nominatim (free OSM geocoding)
                if (input) {
                    setupSearchBox(input);
                }

                // Load initial markers
                if (markersData && markersData.length > 0) {
                    updateMarkers(markersData);
                }
            }

            function setupSearchBox(input) {
                let searchTimeout = null;
                const $input = $(input);
                const $resultsContainer = $('<div class="nominatim-results"></div>').insertAfter($input);

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
                        searchNominatim(query, $resultsContainer);
                    }, 500);
                });

                $input.on('blur', function() {
                    setTimeout(() => $resultsContainer.hide(), 200);
                });
            }

            function searchNominatim(query, $container) {
                $.get('https://nominatim.openstreetmap.org/search', {
                    q: query,
                    format: 'json',
                    limit: 5
                }, function(results) {
                    $container.empty();
                    if (results.length === 0) {
                        $container.append('<div class="p-2 text-muted">{{ translate("no_results_found") }}</div>');
                    } else {
                        results.forEach(function(result) {
                            const $item = $('<div class="p-2 nominatim-result-item" style="cursor:pointer;border-bottom:1px solid #eee;"></div>')
                                .text(result.display_name)
                                .on('click', function() {
                                    const lat = parseFloat(result.lat);
                                    const lng = parseFloat(result.lon);

                                    // Clear existing search markers
                                    searchMarkers.forEach(m => map.removeLayer(m));
                                    searchMarkers = [];

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

            function createCustomIcon(iconUrl) {
                return L.icon({
                    iconUrl: iconUrl,
                    iconSize: [32, 32],
                    iconAnchor: [16, 32],
                    popupAnchor: [0, -32]
                });
            }

            function animateMarker(marker, startLatLng, endLatLng, duration = 14980) {
                const startTime = performance.now();

                function moveMarker(timestamp) {
                    const elapsed = timestamp - startTime;
                    const progress = Math.min(elapsed / duration, 1);

                    const lat = startLatLng.lat + (endLatLng.lat - startLatLng.lat) * progress;
                    const lng = startLatLng.lng + (endLatLng.lng - startLatLng.lng) * progress;
                    marker.setLatLng([lat, lng]);

                    if (progress < 1) requestAnimationFrame(moveMarker);
                }

                requestAnimationFrame(moveMarker);
            }

            function openPopupForMarker(marker, data) {
                const popupContent = `
                    <div class="map-clusters-custom-window">
                        <a class="d-flex justify-content-between gap-1 align-items-center" href="${data.driver ?? data.customer}" target="_blank">
                            <h6>${data.title}</h6>
                            ${data.safetyAlertIcon ? `<img src="${data.safetyAlertIcon}" alt="safety alert icon" height="22px" width="22px">` : ''}
                        </a>
                        <a href="${data.trip}" target="_blank"><p>${data.subtitle || ""}</p></a>
                    </div>
                `;

                if (currentlyOpenMarkerId === data.id && currentPopup) {
                    return;
                }

                if (currentPopup) {
                    map.closePopup(currentPopup);
                }

                currentPopup = L.popup()
                    .setLatLng(marker.getLatLng())
                    .setContent(popupContent)
                    .openOn(map);

                currentlyOpenMarkerId = data.id;
                singleViewZoom(data.position);
            }

            function updateMarkers(markerData, openMarkers = false) {
                const updatedMarkersMap = new Map();
                const newMarkers = [];

                markerData.forEach(data => {
                    const existingMarker = markersById.get(data.id);

                    if (existingMarker) {
                        const oldLatLng = existingMarker.getLatLng();
                        const newLatLng = L.latLng(data.position.lat, data.position.lng);

                        if (oldLatLng.lat !== newLatLng.lat || oldLatLng.lng !== newLatLng.lng) {
                            animateMarker(existingMarker, {
                                lat: oldLatLng.lat,
                                lng: oldLatLng.lng
                            }, data.position);
                        }

                        // Update icon if changed
                        if (data.icon) {
                            existingMarker.setIcon(createCustomIcon(data.icon));
                        }

                        // Update popup if this marker is open
                        if (currentlyOpenMarkerId === data.id) {
                            openPopupForMarker(existingMarker, data);
                        }

                        existingMarker._markerData = data;
                        updatedMarkersMap.set(data.id, existingMarker);
                    } else {
                        // Create new marker
                        const markerOptions = {};
                        if (data.icon) {
                            markerOptions.icon = createCustomIcon(data.icon);
                        }

                        const marker = L.marker([data.position.lat, data.position.lng], markerOptions);
                        marker._markerId = data.id;
                        marker._markerData = data;

                        marker.on('click', function() {
                            openPopupForMarker(marker, marker._markerData);
                        });

                        newMarkers.push(marker);
                        updatedMarkersMap.set(data.id, marker);
                    }
                });

                // Remove old markers not in the update
                markersById.forEach((marker, id) => {
                    if (!updatedMarkersMap.has(id)) {
                        markerCluster.removeLayer(marker);
                    }
                });

                // Add new markers to cluster
                if (newMarkers.length > 0) {
                    markerCluster.addLayers(newMarkers);
                }

                markersById = updatedMarkersMap;
                markers = Array.from(updatedMarkersMap.values());
            }

            function fetchModelUpdate() {
                const requestData = getRequestData();
                $.get({
                    url: "{{ route('admin.fleet-map-view-using-ajax') }}",
                    dataType: "json",
                    data: requestData,
                    success: function (response) {
                        if (response) {
                            const listUrl = getListUrl();
                            $.get({
                                url: listUrl,
                                dataType: "json",
                                data: requestData,
                                success: function (userListResponse) {
                                    $(".zone-list").empty().html(userListResponse);
                                    updateMarkers(JSON.parse(response.markers), false);
                                    userZoneList();
                                },
                                error: showError('{{ translate('failed_to_load_list') }}')
                            });
                        }
                    },
                    error: showError('{{ translate('failed_to_load_data') }}')
                });
            }

            function fetchSingleModelUpdate() {
                if (typeof safetyAlertUserId !== 'undefined' && safetyAlertUserId && $("#userId").val() == '') {
                    $("#userId").val(safetyAlertUserId);
                }
                const id = $("#userId").val();
                const url = getSingleViewUrl(id);
                $.get({
                    url,
                    dataType: "json",
                    data: {zone_id: "{{ request('zone_id') }}"},
                    success: function (response) {
                        const markerData = JSON.parse(response.markers);
                        updateMarkers(markerData, true);
                        if (!isSingleView && markerData.length) {
                            const firstMarker = markerData[0];
                            singleViewZoom(firstMarker.position);
                            isSingleView = true;
                            const marker = markersById.get(firstMarker.id);
                            if (marker) openPopupForMarker(marker, firstMarker);
                        }
                        loadUserDetails(id);
                    },
                    error: showError('{{ translate('failed_to_load_data') }}')
                });
            }

            function manageIntervals() {
                if ($("#userId").val()) {
                    clearInterval(doubleInterval);
                    if (!singleInterval) singleInterval = setInterval(fetchSingleModelUpdate, 15000);
                } else {
                    clearInterval(singleInterval);
                    if (!doubleInterval) doubleInterval = setInterval(fetchModelUpdate, 15000);
                }
            }

            function getRequestData() {
                return {
                    zone_id: "{{ request('zone_id') }}",
                    type: "{{ $type }}",
                    search: "{{ request('search') }}"
                };
            }

            function getListUrl() {
                return @json($type) === 'all-customer'
                    ? "{{ route('admin.fleet-map-customer-list', $type) }}"
                    : "{{ route('admin.fleet-map-driver-list', $type) }}";
            }

            function getSingleViewUrl(id) {
                const baseUrl = @json($type) === 'all-customer'
                    ? "{{ route('admin.fleet-map-view-single-customer', ':id') }}"
                    : "{{ route('admin.fleet-map-view-single-driver', ':id') }}";
                return baseUrl.replace(':id', id);
            }

            function getUserDetails(id) {
                const baseUrl = @json($type) === 'all-customer'
                    ? "{{ route('admin.fleet-map-customer-details', ':id') }}"
                    : "{{ route('admin.fleet-map-driver-details', ':id') }}";
                return baseUrl.replace(':id', id);
            }

            function singleViewZoom(center) {
                if (map && map.getZoom() <= 19) {
                    map.setView([center.lat, center.lng], 19);
                }
            }

            function showError(message) {
                return function () {
                    toastr.error(message);
                };
            }

            function resetView() {
                $('#zone-tab-content').show();
                $('#userDetails').hide();
            }

            function userZoneList() {
                $('.zone-list').find('.user-details').on('click', 'label', function (e) {
                    const id = $(this).data('id');
                    $("#userId").val(id);
                    fetchSingleModelUpdate();
                    if (singleInterval) clearInterval(singleInterval);
                    singleInterval = setInterval(fetchSingleModelUpdate, 15000);
                    isSingleView = false;
                    clearInterval(doubleInterval);
                    e.preventDefault();
                    loadUserDetails(id);
                });
            }

            function loadUserDetails(id) {
                const url = getUserDetails(id);
                $.get({
                    url,
                    dataType: 'json',
                    success: function (response) {
                        $('#zone-tab-content').hide();
                        $('#userDetails').show().empty().html(response);
                        $('.customer-back-btn').on('click', resetViewAndFetch);
                        $(".markAsSolvedBtn").on('click', function (e) {
                            markAsSolved(id, this, e);
                        });
                    },
                    error: showError('{{ translate('failed_to_load_data') }}')
                });
            }

            function markAsSolved(id, thisInput, e) {
                e.preventDefault();
                let markAsSolvedUrl = $(thisInput).data('url');
                const csrfToken = $('meta[name="csrf-token"]').attr('content');
                $.ajax({
                    url: markAsSolvedUrl,
                    method: 'PUT',
                    data: { _token: csrfToken },
                    success: function (response) {
                        toastr.success(response.success);
                        loadUserDetails(id);
                        fetchSingleModelUpdate();
                        getSafetyAlerts();
                        fetchSafetyAlertIcon();
                        getZoneMessage();
                    },
                    error: function (xhr, status, error) {
                        const response = xhr.responseJSON;
                        if (response && response.status == 403) {
                            toastr.error(response.message);
                            loadUserDetails(id);
                            fetchSingleModelUpdate();
                            getSafetyAlerts();
                            fetchSafetyAlertIcon();
                            getZoneMessage();
                        } else {
                            showError('{{ translate('failed_to_load_data') }}');
                        }
                    }
                });
            }

            function resetViewAndFetch(e) {
                e.preventDefault();
                $("#userId").val("");
                clearInterval(singleInterval);
                fetchModelUpdate();
                doubleInterval = setInterval(fetchModelUpdate, 15000);
                if (bounds && bounds.isValid()) {
                    map.fitBounds(bounds, { padding: [20, 20] });
                }
                if (currentPopup) {
                    map.closePopup(currentPopup);
                }
                resetView();
            }

            manageIntervals();
            userZoneList();
            resetView();

            // Initialize maps
            $(".map-container").each(function () {
                const $map = $(this).find(".map");
                const input = $(this).find(".map-search-input")[0];
                const lat = $map.data("lat") || defaultLat;
                const lng = $map.data("lng") || defaultLng;
                const title = $map.data("title");
                const markersData = $map.data("markers") || [];
                const polygonData = $map.data("polygon") || [];
                initMap($map.attr("id"), lat, lng, title, markersData, input, polygonData);
            });

            $('#selectZone').on('change', function () {
                $('#zoneSubmitForm').submit();
            });

            if (localStorage.getItem('firstTimeUser') === null) {
                $('.js-select-overlay').show();
                localStorage.setItem('firstTimeUser', 'true');
            }
            $('.js-select-overlay').on('click', function () {
                $(this).hide();
            });
        });
    </script>
@endpush

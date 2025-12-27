@push('css_or_js')
    <style>
        #map-layer {
            max-width: 706px;
            min-height: 430px;
        }
    </style>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush
<div class="col-lg-4">
    @if($trip?->parcelRefund)
        <div class="d-flex gap-10px mb-10px">

            @if($trip->parcelRefund->status == PENDING || $trip->parcelRefund->status == APPROVED )
                <button class="btn btn--cancel flex-grow-1 w-100px justify-content-center fw-semibold"
                        type="button"
                        id="deniedButtonParcelRefund"
                        data-url="{{route('admin.trip.refund.denied', [$trip->parcelRefund->id])}}"
                        data-icon="{{ asset('public/assets/admin-module/img/denied-icon.png') }}"
                        data-title="{{ translate('Are you sure to Deny the Refund Request')."?" }}"
                        data-sub-title="{{translate("Once you deny the request, the customer will not be refunded the amount he asked for.")}}"
                        data-confirm-btn="{{translate("Deny")}}"
                        data-input-title="{{translate("Deny Note")}}"
                        class="btn btn-outline-danger btn-action d-flex justify-content-center align-items-center"
                >{{ translate('Deny') }}</button>
            @endif
            @if($trip->parcelRefund->status == PENDING || $trip->parcelRefund->status == DENIED )
                <button class="btn btn-primary flex-grow-1 w-100px justify-content-center fw-semibold"
                        type="button"
                        id="approvalButtonParcelRefund"
                        data-url="{{route('admin.trip.refund.approved', [$trip->parcelRefund->id])}}"
                        data-icon="{{ asset('public/assets/admin-module/img/approval-icon.png') }}"
                        data-title="{{ translate('Are you sure to Approve the Refund Request')."?" }}"
                        data-sub-title="{{translate("The customer has requested a refund of")}}  <strong>{{set_currency_symbol($trip->parcelRefund->parcel_approximate_price)}}</strong> {{translate("for this parcel.")}}"
                        data-confirm-btn="{{translate("Approve")}}"
                        data-input-title="{{translate("Approval Note")}}"
                        class="btn btn-outline-success btn-action d-flex justify-content-center align-items-center"
                >{{ translate('Approve') }}</button>
            @endif

            @if($trip->parcelRefund->status == APPROVED )
                <button class="btn btn-primary flex-grow-1 w-100px justify-content-center fw-semibold"
                        id="parcelRefundButton"
                        data-amount="{{$trip->parcelRefund->parcel_approximate_price}}"
                        data-url="{{route('admin.trip.refund.store', [$trip->parcelRefund->id])}}"
                        type="button">{{ translate('Make Refund') }}</button>
            @endif
        </div>
    @endif
    <div class="card">
        <div class="card-body">
            <h5 class="text-center mb-3 text-capitalize">{{translate('trip_status')}}</h5>

            <div class="mb-3">
                <label for="trip_status" class="mb-2">{{translate('trip_status')}}</label>
                <select name="trip_status" id="trip_status" class="js-select" disabled>
                    <option selected>{{translate($trip->current_status)}}</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="payment_status" class="mb-2">{{translate('payment_status')}}</label>
                <select name="payment_status" id="payment_status" class="js-select" disabled>
                    <option selected>{{translate($trip->payment_status)}}</option>
                </select>
            </div>
            <div class="mb-4">
                <div id="map-layer"></div>
            </div>

            <div>
                <ul class="list-icon">
                    <li>
                        <div class="media gap-2">
                            <img width="18" src="{{asset('public/assets/admin-module/img/svg/gps.svg')}}" class="svg"
                                 alt="">
                            <div class="media-body">{{$trip->coordinate->pickup_address}}</div>
                        </div>
                    </li>
                    <li>
                        <div class="media gap-2">
                            <img width="18" src="{{asset('public/assets/admin-module/img/svg/map-nav.svg')}}"
                                 class="svg" alt="">
                            <div class="media-body">
                                <div>{{$trip->coordinate->destination_address}}</div>
                                @if($trip->entrance)
                                    <a href="#" class="text-primary d-flex">{{$trip->entrance}}</a>

                                @endif
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="media gap-2">
                            <img width="18" src="{{asset('public/assets/admin-module/img/svg/distance.svg')}}"
                                 class="svg" alt="">
                            @if($trip->current_status == 'completed')
                                <div class="media-body text-capitalize">{{translate('total_distance')}}
                                    - {{$trip->actual_distance}} {{translate('km')}}</div>
                            @else
                                <div class="media-body text-capitalize">{{translate('total_distance')}}
                                    - {{$trip->estimated_distance}} {{translate('km')}}</div>
                            @endif
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('script')
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        "use strict";

        document.addEventListener('DOMContentLoaded', function() {
            const mapLayer = document.getElementById("map-layer");
            if (!mapLayer) return;

            // Trip coordinates
            const start = {
                lat: {{$trip->coordinate->pickup_coordinates->latitude}},
                lng: {{$trip->coordinate->pickup_coordinates->longitude}}
            };
            const end = {
                lat: {{$trip->coordinate->destination_coordinates->latitude}},
                lng: {{$trip->coordinate->destination_coordinates->longitude}}
            };

            // Map settings from DB or fallback
            const tileUrl = @json(app_setting('map.tile_provider_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'));
            const osrmUrl = @json(app_setting('map.osrm_server_url', 'https://router.project-osrm.org'));

            // Initialize Leaflet map
            const map = L.map('map-layer').setView([start.lat, start.lng], 13);

            // Add OpenStreetMap tile layer
            L.tileLayer(tileUrl, {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // Add markers for pickup and destination
            const pickupIcon = L.divIcon({
                className: 'custom-div-icon',
                html: '<div style="background-color:#4CAF50;width:24px;height:24px;border-radius:50%;border:3px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.3);"></div>',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            const destinationIcon = L.divIcon({
                className: 'custom-div-icon',
                html: '<div style="background-color:#F44336;width:24px;height:24px;border-radius:50%;border:3px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.3);"></div>',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            const pickupMarker = L.marker([start.lat, start.lng], { icon: pickupIcon })
                .addTo(map)
                .bindPopup('{{ translate("Pickup Location") }}');

            const destinationMarker = L.marker([end.lat, end.lng], { icon: destinationIcon })
                .addTo(map)
                .bindPopup('{{ translate("Destination") }}');

            // Fit bounds to show both markers
            const bounds = L.latLngBounds([
                [start.lat, start.lng],
                [end.lat, end.lng]
            ]);
            map.fitBounds(bounds, { padding: [30, 30] });

            // Fetch route from OSRM
            fetchRoute(start, end, osrmUrl);

            function fetchRoute(start, end, osrmUrl) {
                const routeUrl = `${osrmUrl}/route/v1/driving/${start.lng},${start.lat};${end.lng},${end.lat}?overview=full&geometries=geojson`;

                fetch(routeUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                            const route = data.routes[0];
                            const coordinates = route.geometry.coordinates.map(coord => [coord[1], coord[0]]);

                            // Draw route polyline
                            const routeLine = L.polyline(coordinates, {
                                color: '#4285F4',
                                weight: 5,
                                opacity: 0.8
                            }).addTo(map);

                            // Fit bounds to route
                            map.fitBounds(routeLine.getBounds(), { padding: [30, 30] });
                        } else {
                            // Fallback: draw straight line if OSRM fails
                            drawStraightLine(start, end);
                        }
                    })
                    .catch(error => {
                        console.warn('OSRM routing failed, drawing straight line:', error);
                        drawStraightLine(start, end);
                    });
            }

            function drawStraightLine(start, end) {
                L.polyline([
                    [start.lat, start.lng],
                    [end.lat, end.lng]
                ], {
                    color: '#4285F4',
                    weight: 3,
                    opacity: 0.7,
                    dashArray: '10, 10'
                }).addTo(map);
            }
        });
    </script>
@endpush

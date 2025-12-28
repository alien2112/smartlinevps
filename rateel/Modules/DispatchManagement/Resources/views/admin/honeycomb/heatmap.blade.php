@extends('adminmodule::layouts.master')

@section('title', translate('Honeycomb_Heatmap'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                <h2 class="fs-22 text-capitalize">
                    <i class="bi bi-map-fill text-primary me-2"></i>
                    {{ translate('خريطة الحرارة - العرض والطلب') }}
                </h2>
                <a href="{{ route('admin.dispatch.honeycomb.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-gear me-1"></i>
                    {{ translate('الإعدادات') }}
                </a>
            </div>

            <!-- Zone Selector & Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <label class="form-label fw-semibold">{{ translate('اختر المنطقة') }}</label>
                            <select class="form-select" id="zone-selector" onchange="loadHeatmap(this.value)">
                                <option value="">{{ translate('-- اختر منطقة --') }}</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}" {{ $selectedZoneId == $zone->id ? 'selected' : '' }}>
                                        {{ $zone->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="col-md-2">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body text-center">
                            <h3 class="mb-1" id="stat-cells">{{ $stats['total_cells'] ?? 0 }}</h3>
                            <small>{{ translate('خلايا نشطة') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body text-center">
                            <h3 class="mb-1" id="stat-supply">{{ $stats['total_supply'] ?? 0 }}</h3>
                            <small>{{ translate('سائقين متاحين') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body text-center">
                            <h3 class="mb-1" id="stat-demand">{{ $stats['total_demand'] ?? 0 }}</h3>
                            <small>{{ translate('طلبات') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body text-center">
                            <h3 class="mb-1" id="stat-hotspots">{{ $stats['hotspot_count'] ?? 0 }}</h3>
                            <small>{{ translate('نقاط ساخنة') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row g-3">
                <!-- Map -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-hexagon me-1"></i>
                                {{ translate('خريطة الخلايا') }}
                            </h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshHeatmap()">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                {{ translate('تحديث') }}
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div id="heatmap-container" style="height: 500px; position: relative;">
                                @if(!$selectedZoneId)
                                    <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                        <div class="text-center">
                                            <i class="bi bi-map fs-1 mb-3 d-block"></i>
                                            {{ translate('اختر منطقة لعرض الخريطة') }}
                                        </div>
                                    </div>
                                @else
                                    <div id="map" style="height: 100%; width: 100%;"></div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cell Details Panel -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-list-ul me-1"></i>
                                {{ translate('تفاصيل الخلايا') }}
                            </h5>
                        </div>
                        <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;">
                            <div id="cells-list">
                                @if(empty($heatmapData))
                                    <div class="text-center text-muted p-4">
                                        <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                                        {{ translate('لا توجد بيانات') }}
                                    </div>
                                @else
                                    @foreach($heatmapData as $cell)
                                        <div class="cell-item p-3 border-bottom" 
                                             data-lat="{{ $cell['center']['lat'] }}" 
                                             data-lng="{{ $cell['center']['lng'] }}">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <span class="badge {{ $cell['imbalance'] > 1.5 ? 'bg-danger' : ($cell['imbalance'] > 1 ? 'bg-warning text-dark' : 'bg-success') }}">
                                                        {{ round($cell['imbalance'], 2) }}x
                                                    </span>
                                                    @if($cell['imbalance'] > 1.5 && $cell['demand'] >= 2)
                                                        <span class="badge bg-danger ms-1">
                                                            <i class="bi bi-fire"></i> {{ translate('ساخن') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <small class="text-muted">{{ substr($cell['h3_index'], 0, 15) }}...</small>
                                            </div>
                                            <div class="row g-2 text-center">
                                                <div class="col-4">
                                                    <div class="bg-success-subtle rounded p-2">
                                                        <h6 class="mb-0">{{ $cell['supply'] }}</h6>
                                                        <small class="text-muted">{{ translate('عرض') }}</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="bg-warning-subtle rounded p-2">
                                                        <h6 class="mb-0">{{ $cell['demand'] }}</h6>
                                                        <small class="text-muted">{{ translate('طلب') }}</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="bg-primary-subtle rounded p-2">
                                                        <h6 class="mb-0">{{ $cell['surge_multiplier'] ?? 1 }}x</h6>
                                                        <small class="text-muted">Surge</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-4 align-items-center">
                        <span class="fw-semibold">{{ translate('مفتاح الألوان:') }}</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 16px; height: 16px; background: #28a745;"></span>
                            <small>{{ translate('متوازن') }} (< 1x)</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 16px; height: 16px; background: #ffc107;"></span>
                            <small>{{ translate('طلب متوسط') }} (1-1.5x)</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 16px; height: 16px; background: #fd7e14;"></span>
                            <small>{{ translate('طلب مرتفع') }} (1.5-2x)</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 16px; height: 16px; background: #dc3545;"></span>
                            <small>{{ translate('نقطة ساخنة') }} (> 2x)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    @if($selectedZoneId)
        <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY', '') }}&callback=initMap" async defer></script>
    @endif

    <script>
        "use strict";

        let map;
        let markers = [];
        let heatmapData = @json($heatmapData ?? []);

        function initMap() {
            // Default center (can be overridden by cell data)
            let center = { lat: 24.7136, lng: 46.6753 }; // Riyadh default
            
            if (heatmapData.length > 0) {
                center = {
                    lat: parseFloat(heatmapData[0].center.lat),
                    lng: parseFloat(heatmapData[0].center.lng)
                };
            }

            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 12,
                center: center,
                styles: [
                    { featureType: "poi", stylers: [{ visibility: "off" }] }
                ]
            });

            // Add markers for each cell
            addCellMarkers(heatmapData);
        }

        function addCellMarkers(cells) {
            // Clear existing markers
            markers.forEach(m => m.setMap(null));
            markers = [];

            cells.forEach(cell => {
                const color = getColorForImbalance(cell.imbalance);
                const isHotspot = cell.imbalance > 1.5 && cell.demand >= 2;

                const marker = new google.maps.Marker({
                    position: { 
                        lat: parseFloat(cell.center.lat), 
                        lng: parseFloat(cell.center.lng) 
                    },
                    map: map,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        fillColor: color,
                        fillOpacity: 0.7,
                        strokeColor: isHotspot ? '#fff' : color,
                        strokeWeight: isHotspot ? 3 : 1,
                        scale: Math.max(10, Math.min(30, cell.demand * 5 + cell.supply * 2))
                    },
                    title: `Supply: ${cell.supply}, Demand: ${cell.demand}, Imbalance: ${cell.imbalance.toFixed(2)}x`
                });

                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="min-width: 150px; direction: rtl;">
                            <strong>${isHotspot ? '🔥 نقطة ساخنة' : 'خلية'}</strong><br>
                            <hr style="margin: 5px 0;">
                            <div>عرض: <strong>${cell.supply}</strong></div>
                            <div>طلب: <strong>${cell.demand}</strong></div>
                            <div>نسبة: <strong>${cell.imbalance.toFixed(2)}x</strong></div>
                            ${cell.surge_multiplier > 1 ? `<div>Surge: <strong>${cell.surge_multiplier}x</strong></div>` : ''}
                        </div>
                    `
                });

                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });

                markers.push(marker);
            });

            // Fit bounds to show all markers
            if (markers.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                markers.forEach(m => bounds.extend(m.getPosition()));
                map.fitBounds(bounds);
            }
        }

        function getColorForImbalance(imbalance) {
            if (imbalance > 2) return '#dc3545';      // Red - Hot
            if (imbalance > 1.5) return '#fd7e14';    // Orange - High demand
            if (imbalance > 1) return '#ffc107';      // Yellow - Medium
            return '#28a745';                          // Green - Balanced
        }

        function loadHeatmap(zoneId) {
            if (!zoneId) return;
            window.location.href = "{{ route('admin.dispatch.honeycomb.heatmap') }}?zone_id=" + zoneId;
        }

        function refreshHeatmap() {
            const zoneId = document.getElementById('zone-selector').value;
            if (!zoneId) {
                toastr.warning('{{ translate("اختر منطقة أولاً") }}');
                return;
            }

            $.ajax({
                url: "{{ route('admin.dispatch.honeycomb.heatmap.data') }}",
                data: { zone_id: zoneId },
                success: function(response) {
                    if (response.success) {
                        heatmapData = response.data.cells;
                        addCellMarkers(heatmapData);
                        
                        // Update stats
                        $('#stat-cells').text(response.data.stats.total_cells);
                        $('#stat-supply').text(response.data.stats.total_supply);
                        $('#stat-demand').text(response.data.stats.total_demand);
                        $('#stat-hotspots').text(response.data.stats.hotspot_count);
                        
                        toastr.success('{{ translate("تم التحديث") }}');
                    }
                },
                error: function() {
                    toastr.error('{{ translate("فشل التحديث") }}');
                }
            });
        }

        // Auto-refresh every 60 seconds
        @if($selectedZoneId)
            setInterval(refreshHeatmap, 60000);
        @endif

        // Click on cell item to pan to location
        $(document).on('click', '.cell-item', function() {
            const lat = parseFloat($(this).data('lat'));
            const lng = parseFloat($(this).data('lng'));
            if (map && lat && lng) {
                map.panTo({ lat, lng });
                map.setZoom(14);
            }
        });
    </script>
@endpush

<style>
    .cell-item {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .cell-item:hover {
        background-color: rgba(0,0,0,0.03);
    }
</style>

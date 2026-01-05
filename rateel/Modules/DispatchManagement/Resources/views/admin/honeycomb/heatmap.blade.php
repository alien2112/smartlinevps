@extends('adminmodule::layouts.master')

@section('title', 'خريطة الحرارة')

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                <h2 class="fs-22 text-capitalize">
                    <i class="bi bi-map-fill text-primary me-2"></i>
                    خريطة الحرارة - العرض والطلب
                </h2>
                <a href="{{ route('admin.dispatch.honeycomb.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-gear me-1"></i>
                    الإعدادات
                </a>
            </div>

            <!-- Zone Selector & Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <label class="form-label fw-semibold">اختر المنطقة</label>
                            <select class="form-select" id="zone-selector" onchange="loadHeatmap(this.value)">
                                <option value="">-- اختر منطقة --</option>
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
                            <small>خلايا نشطة</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body text-center">
                            <h3 class="mb-1" id="stat-supply">{{ $stats['total_supply'] ?? 0 }}</h3>
                            <small>سائقين متاحين</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body text-center">
                            <h3 class="mb-1" id="stat-demand">{{ $stats['total_demand'] ?? 0 }}</h3>
                            <small>طلبات</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body text-center">
                            <h3 class="mb-1" id="stat-hotspots">{{ $stats['hotspot_count'] ?? 0 }}</h3>
                            <small>نقاط ساخنة</small>
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
                                خريطة الخلايا
                            </h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshHeatmap()">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                تحديث
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div id="heatmap-container" style="height: 500px; position: relative;">
                                @if(!$selectedZoneId)
                                    <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                        <div class="text-center">
                                            <i class="bi bi-map fs-1 mb-3 d-block"></i>
                                            اختر منطقة لعرض الخريطة
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
                                تفاصيل الخلايا
                            </h5>
                        </div>
                        <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;">
                            <div id="cells-list">
                                @if(empty($heatmapData))
                                    <div class="text-center text-muted p-4">
                                        <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                                        لا توجد بيانات
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
                                                            <i class="bi bi-fire"></i> ساخن
                                                        </span>
                                                    @endif
                                                </div>
                                                <small class="text-muted">{{ substr($cell['h3_index'], 0, 15) }}...</small>
                                            </div>
                                            <div class="row g-2 text-center">
                                                <div class="col-4">
                                                    <div class="bg-success-subtle rounded p-2">
                                                        <h6 class="mb-0">{{ $cell['supply'] }}</h6>
                                                        <small class="text-muted">عرض</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="bg-warning-subtle rounded p-2">
                                                        <h6 class="mb-0">{{ $cell['demand'] }}</h6>
                                                        <small class="text-muted">طلب</small>
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
                        <span class="fw-semibold">مفتاح الألوان:</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 16px; height: 16px; background: #e0e0e0; border: 1px solid #ccc;"></span>
                            <small>فارغ (لا نشاط)</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 16px; height: 16px; background: #28a745;"></span>
                            <small>متوازن (< 1x)</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 16px; height: 16px; background: #ffc107;"></span>
                            <small>طلب متوسط (1-1.5x)</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 16px; height: 16px; background: #fd7e14;"></span>
                            <small>طلب مرتفع (1.5-2x)</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 16px; height: 16px; background: #dc3545;"></span>
                            <small>نقطة ساخنة (> 2x)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('css_or_js')
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@push('script')
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        "use strict";

        let map;
        let hexagons = [];
        let heatmapData = @json($heatmapData ?? []);

        $(document).ready(function() {
            @if($selectedZoneId)
                initMap();
            @endif
        });

        function initMap() {
            // Default center (Egypt center)
            let center = [26.8206, 30.8025];
            let zoom = 10;

            if (heatmapData.length > 0) {
                center = [
                    parseFloat(heatmapData[0].center.lat),
                    parseFloat(heatmapData[0].center.lng)
                ];
            }

            // Initialize Leaflet map
            map = L.map('map', {
                center: center,
                zoom: zoom,
                zoomControl: true
            });

            // Add OpenStreetMap tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // Draw hexagons for each cell
            drawHexagons(heatmapData);
        }

        function drawHexagons(cells) {
            // Clear existing hexagons
            hexagons.forEach(h => map.removeLayer(h));
            hexagons = [];

            if (cells.length === 0) return;

            let bounds = L.latLngBounds();

            cells.forEach(cell => {
                const isEmpty = cell.is_empty || (cell.supply === 0 && cell.demand === 0);
                const color = isEmpty ? '#e0e0e0' : getColorForImbalance(cell.imbalance);
                const isHotspot = cell.imbalance > 1.5 && cell.demand >= 2;

                const centerLat = parseFloat(cell.center.lat);
                const centerLng = parseFloat(cell.center.lng);

                // Generate hexagon vertices
                const hexVertices = getHexagonVertices(centerLat, centerLng, 0.01);

                const hexagon = L.polygon(hexVertices, {
                    color: isHotspot ? '#ffffff' : (isEmpty ? '#cccccc' : color),
                    weight: isHotspot ? 3 : 1,
                    opacity: isEmpty ? 0.3 : 0.8,
                    fillColor: color,
                    fillOpacity: isEmpty ? 0.1 : 0.5
                }).addTo(map);

                // Add popup for non-empty cells
                if (!isEmpty) {
                    const popupContent = `
                        <div style="min-width: 150px; direction: rtl;">
                            <strong>${isHotspot ? '🔥 نقطة ساخنة' : 'خلية'}</strong><br>
                            <hr style="margin: 5px 0;">
                            <div>عرض: <strong>${cell.supply}</strong></div>
                            <div>طلب: <strong>${cell.demand}</strong></div>
                            <div>نسبة: <strong>${cell.imbalance.toFixed(2)}x</strong></div>
                            ${cell.surge_multiplier > 1 ? `<div>Surge: <strong>${cell.surge_multiplier}x</strong></div>` : ''}
                        </div>
                    `;
                    hexagon.bindPopup(popupContent);
                }

                hexagons.push(hexagon);
                bounds.extend([centerLat, centerLng]);
            });

            // Fit map to show all hexagons
            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [20, 20] });
            }
        }

        /**
         * Generate hexagon vertices around a center point
         */
        function getHexagonVertices(lat, lng, radius) {
            const vertices = [];
            for (let i = 0; i < 6; i++) {
                const angle = (Math.PI / 3) * i; // 60 degrees per vertex
                const dx = radius * Math.cos(angle);
                const dy = radius * Math.sin(angle);
                vertices.push([
                    lat + dy,
                    lng + dx / Math.cos(lat * Math.PI / 180) // Adjust for latitude distortion
                ]);
            }
            return vertices;
        }

        function getColorForImbalance(imbalance) {
            if (imbalance === 0) return '#e0e0e0';  // Empty/Gray
            if (imbalance > 2) return '#dc3545';     // Red - Hot
            if (imbalance > 1.5) return '#fd7e14';   // Orange - High demand
            if (imbalance > 1) return '#ffc107';     // Yellow - Medium
            return '#28a745';                         // Green - Balanced
        }

        function loadHeatmap(zoneId) {
            if (!zoneId) return;
            window.location.href = "{{ route('admin.dispatch.honeycomb.heatmap') }}?zone_id=" + zoneId;
        }

        function refreshHeatmap() {
            const zoneId = document.getElementById('zone-selector').value;
            if (!zoneId) {
                toastr.warning('اختر منطقة أولاً');
                return;
            }

            if (!map) {
                toastr.error('الخريطة غير جاهزة');
                return;
            }

            $.ajax({
                url: "{{ route('admin.dispatch.honeycomb.heatmap.data') }}",
                data: { zone_id: zoneId },
                success: function(response) {
                    if (response.success) {
                        heatmapData = response.data.cells;
                        drawHexagons(heatmapData);

                        // Update stats
                        $('#stat-cells').text(response.data.stats.total_cells);
                        $('#stat-supply').text(response.data.stats.total_supply);
                        $('#stat-demand').text(response.data.stats.total_demand);
                        $('#stat-hotspots').text(response.data.stats.hotspot_count);

                        toastr.success('تم التحديث');
                    }
                },
                error: function() {
                    toastr.error('فشل التحديث');
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
                map.setView([lat, lng], 14);
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

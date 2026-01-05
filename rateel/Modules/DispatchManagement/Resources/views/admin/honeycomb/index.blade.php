@extends('adminmodule::layouts.master')

@section('title', 'إعدادات نظام الخلية')

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                <h2 class="fs-22 text-capitalize">
                    <i class="bi bi-hexagon-fill text-primary me-2"></i>
                    إعدادات نظام الخلية
                </h2>
                <a href="{{ route('admin.dispatch.honeycomb.heatmap') }}" class="btn btn-outline-primary">
                    <i class="bi bi-map me-1"></i>
                    عرض خريطة الحرارة
                </a>
            </div>

            <!-- Zone Selector -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">اختر المنطقة</label>
                            <select class="form-select" id="zone-selector" onchange="changeZone(this.value)">
                                <option value="">الإعدادات العامة (جميع المناطق)</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}" {{ $selectedZoneId == $zone->id ? 'selected' : '' }}>
                                        {{ $zone->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex flex-wrap gap-3 mt-3 mt-md-0">
                                @foreach($zoneStats as $zoneId => $stat)
                                    <div class="badge {{ $stat['enabled'] ? 'bg-success' : 'bg-secondary' }} py-2 px-3">
                                        {{ $stat['name'] }}
                                        @if($stat['enabled'])
                                            <i class="bi bi-check-circle ms-1"></i>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <form action="{{ route('admin.dispatch.honeycomb.store') }}" method="POST">
                @csrf
                <input type="hidden" name="zone_id" value="{{ $selectedZoneId }}">

                <div class="row g-3">
                    <!-- Master Toggles Card -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="d-flex align-items-center gap-2">
                                    <i class="bi bi-toggles text-primary"></i>
                                    تفعيل الميزات
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <!-- Master Toggle -->
                                    <div class="col-md-4">
                                        <div class="p-3 rounded bg-light h-100">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <h6 class="fw-semibold mb-1">تفعيل نظام الخلية</h6>
                                                    <small class="text-muted">التحكم الرئيسي بالنظام</small>
                                                </div>
                                                <label class="switcher">
                                                    <input class="switcher_input" type="checkbox" name="enabled" 
                                                           {{ $settings->enabled ? 'checked' : '' }}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Dispatch Toggle -->
                                    <div class="col-md-4">
                                        <div class="p-3 rounded bg-light h-100">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <h6 class="fw-semibold mb-1">تفعيل التوزيع الذكي</h6>
                                                    <small class="text-muted">البحث في خلايا مجاورة</small>
                                                </div>
                                                <label class="switcher">
                                                    <input class="switcher_input" type="checkbox" name="dispatch_enabled"
                                                           {{ $settings->dispatch_enabled ? 'checked' : '' }}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Heatmap Toggle -->
                                    <div class="col-md-4">
                                        <div class="p-3 rounded bg-light h-100">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <h6 class="fw-semibold mb-1">خريطة الحرارة</h6>
                                                    <small class="text-muted">عرض العرض والطلب</small>
                                                </div>
                                                <label class="switcher">
                                                    <input class="switcher_input" type="checkbox" name="heatmap_enabled"
                                                           {{ $settings->heatmap_enabled ? 'checked' : '' }}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hotspots Toggle -->
                                    <div class="col-md-4">
                                        <div class="p-3 rounded bg-light h-100">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <h6 class="fw-semibold mb-1">نقاط ساخنة للسائقين</h6>
                                                    <small class="text-muted">إظهار أماكن الطلب العالي</small>
                                                </div>
                                                <label class="switcher">
                                                    <input class="switcher_input" type="checkbox" name="hotspots_enabled"
                                                           {{ $settings->hotspots_enabled ? 'checked' : '' }}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Surge Toggle -->
                                    <div class="col-md-4">
                                        <div class="p-3 rounded bg-light h-100">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <h6 class="fw-semibold mb-1">تسعير ديناميكي</h6>
                                                    <small class="text-muted">Surge بناءً على الطلب</small>
                                                </div>
                                                <label class="switcher">
                                                    <input class="switcher_input" type="checkbox" name="surge_enabled"
                                                           {{ $settings->surge_enabled ? 'checked' : '' }}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Incentives Toggle -->
                                    <div class="col-md-4">
                                        <div class="p-3 rounded bg-light h-100">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <h6 class="fw-semibold mb-1">حوافز السائقين</h6>
                                                    <small class="text-muted">مكافآت للانتقال للنقاط الساخنة</small>
                                                </div>
                                                <label class="switcher">
                                                    <input class="switcher_input" type="checkbox" name="incentives_enabled"
                                                           {{ $settings->incentives_enabled ? 'checked' : '' }}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- H3 Configuration Card -->
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="d-flex align-items-center gap-2">
                                    <i class="bi bi-hexagon text-primary"></i>
                                    إعدادات الشبكة السداسية
                                    <i class="bi bi-info-circle-fill text-muted cursor-pointer" 
                                       data-bs-toggle="tooltip"
                                       title="H3 هو نظام الشبكة السداسية من Uber للتوزيع الذكي"></i>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- Resolution -->
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">
                                            دقة الخلية
                                            <i class="bi bi-info-circle-fill text-muted cursor-pointer ms-1" 
                                               data-bs-toggle="tooltip"
                                               title="كلما زادت الدقة، صغر حجم الخلية"></i>
                                        </label>
                                        <select class="form-select" name="h3_resolution" id="h3_resolution">
                                            @foreach($resolutions as $res => $info)
                                                <option value="{{ $res }}" {{ $settings->h3_resolution == $res ? 'selected' : '' }}>
                                                    {{ $res }} - {{ $info['name'] }} (~{{ $info['area_km2'] }} كم²)
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">
                                            <span id="resolution-info">
                                                الموصى به: 8 (مستوى الحي - ~0.74 كم²)
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Search Depth -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">عمق البحث (k)</label>
                                        <select class="form-select" name="search_depth_k">
                                            <option value="1" {{ $settings->search_depth_k == 1 ? 'selected' : '' }}>
                                                1 حلقة (7 خلايا)
                                            </option>
                                            <option value="2" {{ $settings->search_depth_k == 2 ? 'selected' : '' }}>
                                                2 حلقات (19 خلية)
                                            </option>
                                            <option value="3" {{ $settings->search_depth_k == 3 ? 'selected' : '' }}>
                                                3 حلقات (37 خلية)
                                            </option>
                                        </select>
                                        <div class="form-text">عدد حلقات الخلايا المجاورة للبحث</div>
                                    </div>

                                    <!-- Update Interval -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">فترة التحديث</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="update_interval_seconds"
                                                   value="{{ $settings->update_interval_seconds }}" min="30" max="300">
                                            <span class="input-group-text">ثانية</span>
                                        </div>
                                    </div>

                                    <!-- Min Drivers -->
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">الحد الأدنى للسائقين لإظهار الخلية</label>
                                        <input type="number" class="form-control" name="min_drivers_to_color_cell"
                                               value="{{ $settings->min_drivers_to_color_cell }}" min="1" max="10">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Surge Configuration Card -->
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="d-flex align-items-center gap-2">
                                    <i class="bi bi-graph-up-arrow text-danger"></i>
                                    إعدادات التسعير الديناميكي
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- Surge Threshold -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">عتبة التفعيل</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" class="form-control" name="surge_threshold"
                                                   value="{{ $settings->surge_threshold }}" min="1" max="5">
                                            <span class="input-group-text">x</span>
                                        </div>
                                        <div class="form-text">نسبة الطلب/العرض لبدء Surge</div>
                                    </div>

                                    <!-- Surge Cap -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">الحد الأقصى للمضاعف</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" class="form-control" name="surge_cap"
                                                   value="{{ $settings->surge_cap }}" min="1" max="3">
                                            <span class="input-group-text">x</span>
                                        </div>
                                    </div>

                                    <!-- Surge Step -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">خطوة الزيادة</label>
                                        <div class="input-group">
                                            <input type="number" step="0.05" class="form-control" name="surge_step"
                                                   value="{{ $settings->surge_step }}" min="0.05" max="0.5">
                                            <span class="input-group-text">x</span>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <hr>
                                        <h6 class="fw-semibold mb-3">
                                            <i class="bi bi-gift text-success me-1"></i>
                                            حوافز السائقين
                                        </h6>
                                    </div>

                                    <!-- Incentive Threshold -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">عتبة الحافز</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" class="form-control" name="incentive_threshold"
                                                   value="{{ $settings->incentive_threshold }}" min="1" max="5">
                                            <span class="input-group-text">x</span>
                                        </div>
                                    </div>

                                    <!-- Max Incentive -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">الحد الأقصى للحافز</label>
                                        <div class="input-group">
                                            <input type="number" step="1" class="form-control" name="max_incentive_amount"
                                                   value="{{ $settings->max_incentive_amount }}" min="0" max="200">
                                            <span class="input-group-text">ج.م</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Card -->
                    <div class="col-12">
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="fw-semibold">
                                            <i class="bi bi-lightbulb text-warning me-2"></i>
                                            كيف يعمل نظام الخلية؟
                                        </h6>
                                        <p class="mb-0 text-muted small">
                                            يقسم النظام المدينة إلى خلايا سداسية ويتتبع السائقين والطلبات في كل خلية. عند وصول طلب، يبحث فقط في خلية نقطة الالتقاط والخلايا المجاورة بدلاً من البحث في كامل المدينة، مما يسرع التوزيع بشكل كبير.
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <img src="{{ asset('public/assets/admin-module/img/hexagon-grid.svg') }}" 
                                             alt="Hexagon Grid" 
                                             style="max-width: 150px; opacity: 0.7;"
                                             onerror="this.style.display='none'">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="col-12">
                        <div class="d-flex justify-content-end gap-3">
                            <button type="reset" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-1"></i>
                                إلغاء
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>
                                حفظ الإعدادات
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script>
        "use strict";

        function changeZone(zoneId) {
            let url = "{{ route('admin.dispatch.honeycomb.index') }}";
            if (zoneId) {
                url += "?zone_id=" + zoneId;
            }
            window.location.href = url;
        }

        // Initialize tooltips
        $(function () {
            $('[data-bs-toggle="tooltip"]').tooltip();
        });
    </script>
@endpush

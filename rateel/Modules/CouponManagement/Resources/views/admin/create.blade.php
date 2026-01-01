@extends('adminmodule::layouts.master')

@section('title', translate('Create Coupon'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Create New Coupon') }}</h2>
            <a href="{{ route('admin.coupon-management.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back to List') }}
            </a>
        </div>

        <form action="{{ route('admin.coupon-management.store') }}" method="POST">
            @csrf
            
            <div class="row g-4">
                <!-- Basic Information -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">{{ translate('Basic Information') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Coupon Code') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="code" class="form-control text-uppercase" placeholder="e.g., SAVE20" value="{{ old('code') }}" required style="text-transform: uppercase;">
                                    <small class="text-muted">{{ translate('Customers will enter this code') }}</small>
                                    @error('code')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Coupon Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g., 20% Off First Ride" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">{{ translate('Description') }}</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="{{ translate('Optional description for customers') }}">{{ old('description') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Discount Settings -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">{{ translate('Discount Settings') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Discount Type') }} <span class="text-danger">*</span></label>
                                    <select name="type" id="coupon_type" class="form-select" required>
                                        @foreach($types as $value => $label)
                                            <option value="{{ $value }}" {{ old('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4" id="value_container">
                                    <label class="form-label">{{ translate('Discount Value') }} <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="value" class="form-control" step="0.01" min="0" value="{{ old('value') }}" required>
                                        <span class="input-group-text" id="value_suffix">%</span>
                                    </div>
                                </div>
                                <div class="col-md-4" id="max_discount_container">
                                    <label class="form-label">{{ translate('Max Discount') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ businessConfig('currency_symbol')?->value ?? 'EGP' }}</span>
                                        <input type="number" name="max_discount" class="form-control" step="0.01" min="0" value="{{ old('max_discount') }}">
                                    </div>
                                    <small class="text-muted">{{ translate('Optional cap on discount') }}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Minimum Fare Required') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ businessConfig('currency_symbol')?->value ?? 'EGP' }}</span>
                                        <input type="number" name="min_fare" class="form-control" step="0.01" min="0" value="{{ old('min_fare', 0) }}">
                                    </div>
                                    <small class="text-muted">{{ translate('Minimum trip fare to apply coupon') }}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Per User Limit') }} <span class="text-danger">*</span></label>
                                    <input type="number" name="per_user_limit" class="form-control" min="1" value="{{ old('per_user_limit', 1) }}" required>
                                    <small class="text-muted">{{ translate('How many times each user can use') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Validity Period -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">{{ translate('Validity Period') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Start Date') }} <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('End Date') }} <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', now()->addMonth()->format('Y-m-d\TH:i')) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Restrictions -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">{{ translate('Restrictions') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Allowed Zones/Cities') }}</label>
                                    <select name="allowed_city_ids[]" class="form-select" multiple>
                                        @foreach($zones as $zone)
                                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">{{ translate('Leave empty to allow all zones') }}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Allowed Service Types') }}</label>
                                    <div class="d-flex gap-3">
                                        @foreach($serviceTypes as $type)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="allowed_service_types[]" value="{{ $type }}" id="service_{{ $type }}">
                                                <label class="form-check-label" for="service_{{ $type }}">{{ translate(ucfirst($type)) }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <small class="text-muted">{{ translate('Leave empty to allow all') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <!-- Status & Limits -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">{{ translate('Status & Limits') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                                    <label class="form-check-label" for="is_active">{{ translate('Active') }}</label>
                                </div>
                                <small class="text-muted">{{ translate('Coupon can be used when active') }}</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ translate('Global Usage Limit') }}</label>
                                <input type="number" name="global_limit" class="form-control" min="1" value="{{ old('global_limit') }}" placeholder="{{ translate('Unlimited') }}">
                                <small class="text-muted">{{ translate('Total times this coupon can be used') }}</small>
                            </div>
                        </div>
                    </div>

                    <!-- Eligibility -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">{{ translate('Eligibility') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">{{ translate('Who can use this coupon?') }} <span class="text-danger">*</span></label>
                                <select name="eligibility_type" id="eligibility_type" class="form-select" required>
                                    @foreach($eligibilityTypes as $value => $label)
                                        <option value="{{ $value }}" {{ old('eligibility_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3" id="segment_container" style="display: none;">
                                <label class="form-label">{{ translate('User Segment') }}</label>
                                <select name="segment_key" class="form-select">
                                    <option value="">{{ translate('Select segment') }}</option>
                                    @foreach($segments as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="targeted_info" style="display: none;">
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    {{ translate('You can add target users after creating the coupon') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle me-2"></i>{{ translate('Create Coupon') }}
                        </button>
                        <a href="{{ route('admin.coupon-management.index') }}" class="btn btn-outline-secondary">
                            {{ translate('Cancel') }}
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const typeSelect = document.getElementById('coupon_type');
        const valueContainer = document.getElementById('value_container');
        const valueSuffix = document.getElementById('value_suffix');
        const maxDiscountContainer = document.getElementById('max_discount_container');
        const eligibilitySelect = document.getElementById('eligibility_type');
        const segmentContainer = document.getElementById('segment_container');
        const targetedInfo = document.getElementById('targeted_info');

        function updateTypeUI() {
            const type = typeSelect.value;
            if (type === 'PERCENT') {
                valueSuffix.textContent = '%';
                maxDiscountContainer.style.display = 'block';
                valueContainer.style.display = 'block';
            } else if (type === 'FIXED') {
                valueSuffix.textContent = '{{ businessConfig("currency_symbol")?->value ?? "EGP" }}';
                maxDiscountContainer.style.display = 'none';
                valueContainer.style.display = 'block';
            } else {
                // FREE_RIDE_CAP
                valueContainer.style.display = 'none';
                maxDiscountContainer.style.display = 'block';
            }
        }

        function updateEligibilityUI() {
            const eligibility = eligibilitySelect.value;
            segmentContainer.style.display = eligibility === 'SEGMENT' ? 'block' : 'none';
            targetedInfo.style.display = eligibility === 'TARGETED' ? 'block' : 'none';
        }

        typeSelect.addEventListener('change', updateTypeUI);
        eligibilitySelect.addEventListener('change', updateEligibilityUI);

        updateTypeUI();
        updateEligibilityUI();
    });
</script>
@endpush

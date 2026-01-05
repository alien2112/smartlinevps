@extends('adminmodule::layouts.master')

@section('title', translate('Create Offer'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Create New Offer') }}</h2>
            <a href="{{ route('admin.offer-management.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back to List') }}
            </a>
        </div>

        <form action="{{ route('admin.offer-management.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div class="row g-4">
                <div class="col-md-8">
                    <!-- Basic Information -->
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">{{ translate('Basic Information') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">{{ translate('Offer Title') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">{{ translate('Short Description') }}</label>
                                    <textarea name="short_description" class="form-control" rows="2">{{ old('short_description') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">{{ translate('Terms & Conditions') }}</label>
                                    <textarea name="terms_conditions" class="form-control" rows="3">{{ old('terms_conditions') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Offer Image') }}</label>
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Banner Image') }}</label>
                                    <input type="file" name="banner_image" class="form-control" accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Discount Settings -->
                    <div class="card mt-4">
                        <div class="card-header"><h5 class="mb-0">{{ translate('Discount Settings') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Discount Type') }} <span class="text-danger">*</span></label>
                                    <select name="discount_type" id="discount_type" class="form-select" required>
                                        <option value="percentage">{{ translate('Percentage (%)') }}</option>
                                        <option value="fixed">{{ translate('Fixed Amount') }}</option>
                                        <option value="free_ride">{{ translate('Free Ride') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4" id="discount_value_group">
                                    <label class="form-label">{{ translate('Discount Value') }} <span class="text-danger">*</span></label>
                                    <input type="number" name="discount_amount" class="form-control" step="0.01" min="0" value="{{ old('discount_amount') }}" required>
                                </div>
                                <div class="col-md-4" id="max_discount_group">
                                    <label class="form-label">{{ translate('Max Discount') }}</label>
                                    <input type="number" name="max_discount" class="form-control" step="0.01" min="0" value="{{ old('max_discount') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Minimum Trip Amount') }}</label>
                                    <input type="number" name="min_trip_amount" class="form-control" step="0.01" min="0" value="{{ old('min_trip_amount', 0) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Limit Per User') }} <span class="text-danger">*</span></label>
                                    <input type="number" name="limit_per_user" class="form-control" min="1" value="{{ old('limit_per_user', 1) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Validity Period -->
                    <div class="card mt-4">
                        <div class="card-header"><h5 class="mb-0">{{ translate('Validity Period') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Start Date') }} <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="start_date" class="form-control" value="{{ old('start_date', now()->format('Y-m-d\TH:i')) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('End Date') }} <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date', now()->addMonth()->format('Y-m-d\TH:i')) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Targeting -->
                    <div class="card mt-4">
                        <div class="card-header"><h5 class="mb-0">{{ translate('Targeting') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <!-- Zone Targeting -->
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Zone Targeting') }}</label>
                                    <select name="zone_type" id="zone_type" class="form-select">
                                        <option value="all">{{ translate('All Zones') }}</option>
                                        <option value="selected">{{ translate('Selected Zones') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="zone_ids_group" style="display: none;">
                                    <label class="form-label">{{ translate('Select Zones') }}</label>
                                    <select name="zone_ids[]" class="form-select" multiple>
                                        @foreach($zones as $zone)
                                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Customer Level Targeting -->
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Customer Level') }}</label>
                                    <select name="customer_level_type" id="customer_level_type" class="form-select">
                                        <option value="all">{{ translate('All Levels') }}</option>
                                        <option value="selected">{{ translate('Selected Levels') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="customer_level_ids_group" style="display: none;">
                                    <label class="form-label">{{ translate('Select Levels') }}</label>
                                    <select name="customer_level_ids[]" class="form-select" multiple>
                                        @foreach($customerLevels as $level)
                                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Service Type -->
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Service Type') }}</label>
                                    <select name="service_type" id="service_type" class="form-select">
                                        <option value="all">{{ translate('All Services') }}</option>
                                        <option value="ride">{{ translate('Ride Only') }}</option>
                                        <option value="parcel">{{ translate('Parcel Only') }}</option>
                                        <option value="selected">{{ translate('Selected Categories') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="vehicle_category_ids_group" style="display: none;">
                                    <label class="form-label">{{ translate('Vehicle Categories') }}</label>
                                    <select name="vehicle_category_ids[]" class="form-select" multiple>
                                        @foreach($vehicleCategories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Customer Type -->
                                <div class="col-12">
                                    <label class="form-label">{{ translate('Customer Targeting') }}</label>
                                    <select name="customer_type" class="form-select">
                                        <option value="all">{{ translate('All Customers') }}</option>
                                        <option value="selected">{{ translate('Selected Customers Only') }}</option>
                                    </select>
                                    <small class="text-muted">{{ translate('For selected customers, add them after creating the offer') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">{{ translate('Status & Settings') }}</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                                    <label class="form-check-label" for="is_active">{{ translate('Active') }}</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_in_app" id="show_in_app" value="1" checked>
                                    <label class="form-check-label" for="show_in_app">{{ translate('Show in App') }}</label>
                                </div>
                                <small class="text-muted">{{ translate('Display in customer offers list') }}</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ translate('Global Usage Limit') }}</label>
                                <input type="number" name="global_limit" class="form-control" min="1" placeholder="{{ translate('Unlimited') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ translate('Priority') }}</label>
                                <input type="number" name="priority" class="form-control" min="0" max="255" value="0">
                                <small class="text-muted">{{ translate('Higher priority offers are applied first') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle me-2"></i>{{ translate('Create Offer') }}
                        </button>
                        <a href="{{ route('admin.offer-management.index') }}" class="btn btn-outline-secondary">{{ translate('Cancel') }}</a>
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
    // Discount type handling
    const discountType = document.getElementById('discount_type');
    const discountValueGroup = document.getElementById('discount_value_group');
    const maxDiscountGroup = document.getElementById('max_discount_group');
    
    discountType.addEventListener('change', function() {
        if (this.value === 'free_ride') {
            discountValueGroup.style.display = 'none';
        } else {
            discountValueGroup.style.display = 'block';
        }
        maxDiscountGroup.style.display = (this.value === 'percentage' || this.value === 'free_ride') ? 'block' : 'none';
    });

    // Zone targeting
    document.getElementById('zone_type').addEventListener('change', function() {
        document.getElementById('zone_ids_group').style.display = this.value === 'selected' ? 'block' : 'none';
    });

    // Customer level targeting
    document.getElementById('customer_level_type').addEventListener('change', function() {
        document.getElementById('customer_level_ids_group').style.display = this.value === 'selected' ? 'block' : 'none';
    });

    // Service type targeting
    document.getElementById('service_type').addEventListener('change', function() {
        document.getElementById('vehicle_category_ids_group').style.display = this.value === 'selected' ? 'block' : 'none';
    });
});
</script>
@endpush

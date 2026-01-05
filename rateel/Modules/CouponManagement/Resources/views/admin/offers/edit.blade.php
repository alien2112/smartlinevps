@extends('adminmodule::layouts.master')

@section('title', translate('Edit Offer'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Edit Offer') }}: {{ $offer->title }}</h2>
            <a href="{{ route('admin.offer-management.show', $offer->id) }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back') }}
            </a>
        </div>

        <form action="{{ route('admin.offer-management.update', $offer->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            
            <div class="row g-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">{{ translate('Basic Information') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">{{ translate('Offer Title') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" value="{{ old('title', $offer->title) }}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">{{ translate('Short Description') }}</label>
                                    <textarea name="short_description" class="form-control" rows="2">{{ old('short_description', $offer->short_description) }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">{{ translate('Terms & Conditions') }}</label>
                                    <textarea name="terms_conditions" class="form-control" rows="3">{{ old('terms_conditions', $offer->terms_conditions) }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Offer Image') }}</label>
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                    @if($offer->image)
                                        <small class="text-muted">Current: {{ basename($offer->image) }}</small>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Banner Image') }}</label>
                                    <input type="file" name="banner_image" class="form-control" accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header"><h5 class="mb-0">{{ translate('Discount Settings') }}</h5></div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>{{ translate('Discount type and value cannot be changed') }}
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Discount Type') }}</label>
                                    <input type="text" class="form-control" value="{{ ucfirst($offer->discount_type) }}" disabled>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Discount Value') }}</label>
                                    <input type="text" class="form-control" value="{{ $offer->discount_type === 'percentage' ? $offer->discount_amount . '%' : getCurrencyFormat($offer->discount_amount) }}" disabled>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Max Discount') }}</label>
                                    <input type="number" name="max_discount" class="form-control" step="0.01" value="{{ old('max_discount', $offer->max_discount) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Min Trip Amount') }}</label>
                                    <input type="number" name="min_trip_amount" class="form-control" step="0.01" value="{{ old('min_trip_amount', $offer->min_trip_amount) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Global Limit') }}</label>
                                    <input type="number" name="global_limit" class="form-control" min="{{ $offer->total_used }}" value="{{ old('global_limit', $offer->global_limit) }}">
                                    <small class="text-muted">{{ translate('Currently used') }}: {{ $offer->total_used }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header"><h5 class="mb-0">{{ translate('Validity') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Start Date') }}</label>
                                    <input type="datetime-local" class="form-control" value="{{ $offer->start_date->format('Y-m-d\TH:i') }}" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('End Date') }} <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date', $offer->end_date->format('Y-m-d\TH:i')) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header"><h5 class="mb-0">{{ translate('Targeting') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Zone Targeting') }}</label>
                                    <select name="zone_type" id="zone_type" class="form-select">
                                        <option value="all" {{ $offer->zone_type === 'all' ? 'selected' : '' }}>{{ translate('All Zones') }}</option>
                                        <option value="selected" {{ $offer->zone_type === 'selected' ? 'selected' : '' }}>{{ translate('Selected') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="zone_ids_group" style="{{ $offer->zone_type === 'all' ? 'display:none' : '' }}">
                                    <label class="form-label">{{ translate('Select Zones') }}</label>
                                    <select name="zone_ids[]" class="form-select" multiple>
                                        @foreach($zones as $zone)
                                            <option value="{{ $zone->id }}" {{ in_array($zone->id, $offer->zone_ids ?? []) ? 'selected' : '' }}>{{ $zone->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Customer Level') }}</label>
                                    <select name="customer_level_type" id="customer_level_type" class="form-select">
                                        <option value="all" {{ $offer->customer_level_type === 'all' ? 'selected' : '' }}>{{ translate('All Levels') }}</option>
                                        <option value="selected" {{ $offer->customer_level_type === 'selected' ? 'selected' : '' }}>{{ translate('Selected') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="customer_level_ids_group" style="{{ $offer->customer_level_type === 'all' ? 'display:none' : '' }}">
                                    <label class="form-label">{{ translate('Select Levels') }}</label>
                                    <select name="customer_level_ids[]" class="form-select" multiple>
                                        @foreach($customerLevels as $level)
                                            <option value="{{ $level->id }}" {{ in_array($level->id, $offer->customer_level_ids ?? []) ? 'selected' : '' }}>{{ $level->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Service Type') }}</label>
                                    <select name="service_type" id="service_type" class="form-select">
                                        <option value="all" {{ $offer->service_type === 'all' ? 'selected' : '' }}>{{ translate('All') }}</option>
                                        <option value="ride" {{ $offer->service_type === 'ride' ? 'selected' : '' }}>{{ translate('Ride') }}</option>
                                        <option value="parcel" {{ $offer->service_type === 'parcel' ? 'selected' : '' }}>{{ translate('Parcel') }}</option>
                                        <option value="selected" {{ $offer->service_type === 'selected' ? 'selected' : '' }}>{{ translate('Selected') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="vehicle_category_ids_group" style="{{ $offer->service_type !== 'selected' ? 'display:none' : '' }}">
                                    <label class="form-label">{{ translate('Vehicle Categories') }}</label>
                                    <select name="vehicle_category_ids[]" class="form-select" multiple>
                                        @foreach($vehicleCategories as $cat)
                                            <option value="{{ $cat->id }}" {{ in_array($cat->id, $offer->vehicle_category_ids ?? []) ? 'selected' : '' }}>{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">{{ translate('Customer Targeting') }}</label>
                                    <select name="customer_type" class="form-select">
                                        <option value="all" {{ $offer->customer_type === 'all' ? 'selected' : '' }}>{{ translate('All Customers') }}</option>
                                        <option value="selected" {{ $offer->customer_type === 'selected' ? 'selected' : '' }}>{{ translate('Selected') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">{{ translate('Status') }}</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ $offer->is_active ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">{{ translate('Active') }}</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_in_app" id="show_in_app" value="1" {{ $offer->show_in_app ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_in_app">{{ translate('Show in App') }}</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ translate('Priority') }}</label>
                                <input type="number" name="priority" class="form-control" min="0" max="255" value="{{ old('priority', $offer->priority) }}">
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle me-2"></i>{{ translate('Update Offer') }}
                        </button>
                        <a href="{{ route('admin.offer-management.show', $offer->id) }}" class="btn btn-outline-secondary">{{ translate('Cancel') }}</a>
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
    document.getElementById('zone_type').addEventListener('change', function() {
        document.getElementById('zone_ids_group').style.display = this.value === 'selected' ? 'block' : 'none';
    });
    document.getElementById('customer_level_type').addEventListener('change', function() {
        document.getElementById('customer_level_ids_group').style.display = this.value === 'selected' ? 'block' : 'none';
    });
    document.getElementById('service_type').addEventListener('change', function() {
        document.getElementById('vehicle_category_ids_group').style.display = this.value === 'selected' ? 'block' : 'none';
    });
});
</script>
@endpush

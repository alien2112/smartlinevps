@extends('adminmodule::layouts.master')

@section('title', translate('Edit Coupon'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Edit Coupon') }}: <code>{{ $coupon->code }}</code></h2>
            <a href="{{ route('admin.coupon-management.show', $coupon->id) }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back to Details') }}
            </a>
        </div>

        <form action="{{ route('admin.coupon-management.update', $coupon->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="row g-4">
                <div class="col-md-8">
                    <!-- Basic Information -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">{{ translate('Basic Information') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Coupon Code') }}</label>
                                    <input type="text" class="form-control" value="{{ $coupon->code }}" disabled>
                                    <small class="text-muted">{{ translate('Code cannot be changed') }}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Coupon Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name', $coupon->name) }}" required>
                                    @error('name')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">{{ translate('Description') }}</label>
                                    <textarea name="description" class="form-control" rows="2">{{ old('description', $coupon->description) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Discount Settings (Limited) -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">{{ translate('Discount Settings') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                {{ translate('Type and value cannot be changed after creation to maintain data integrity') }}
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Discount Type') }}</label>
                                    <input type="text" class="form-control" value="{{ $types[$coupon->type] ?? $coupon->type }}" disabled>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Discount Value') }}</label>
                                    <input type="text" class="form-control" value="{{ $coupon->type === 'PERCENT' ? $coupon->value . '%' : getCurrencyFormat($coupon->value) }}" disabled>
                                </div>
                                @if($coupon->type === 'PERCENT' || $coupon->type === 'FREE_RIDE_CAP')
                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Max Discount') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ businessConfig('currency_symbol')?->value ?? 'EGP' }}</span>
                                        <input type="number" name="max_discount" class="form-control" step="0.01" min="0" value="{{ old('max_discount', $coupon->max_discount) }}">
                                    </div>
                                </div>
                                @endif
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Minimum Fare Required') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ businessConfig('currency_symbol')?->value ?? 'EGP' }}</span>
                                        <input type="number" name="min_fare" class="form-control" step="0.01" min="0" value="{{ old('min_fare', $coupon->min_fare) }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Global Usage Limit') }}</label>
                                    <input type="number" name="global_limit" class="form-control" min="{{ $coupon->global_used_count }}" value="{{ old('global_limit', $coupon->global_limit) }}" placeholder="{{ translate('Unlimited') }}">
                                    <small class="text-muted">{{ translate('Currently used') }}: {{ $coupon->global_used_count }}</small>
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
                                    <label class="form-label">{{ translate('Start Date') }}</label>
                                    <input type="datetime-local" class="form-control" value="{{ $coupon->starts_at?->format('Y-m-d\TH:i') }}" disabled>
                                    <small class="text-muted">{{ translate('Start date cannot be changed') }}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('End Date') }} <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $coupon->ends_at?->format('Y-m-d\TH:i')) }}" required>
                                    <!-- Hidden field for validation -->
                                    <input type="hidden" name="starts_at" value="{{ $coupon->starts_at?->format('Y-m-d\TH:i:s') }}">
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
                                            <option value="{{ $zone->id }}" {{ in_array($zone->id, $coupon->allowed_city_ids ?? []) ? 'selected' : '' }}>
                                                {{ $zone->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">{{ translate('Leave empty to allow all zones') }}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('Allowed Service Types') }}</label>
                                    <div class="d-flex gap-3">
                                        @foreach($serviceTypes as $type)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="allowed_service_types[]" value="{{ $type }}" id="service_{{ $type }}" {{ in_array($type, $coupon->allowed_service_types ?? []) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="service_{{ $type }}">{{ translate(ucfirst($type)) }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">{{ translate('Status') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ $coupon->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">{{ translate('Active') }}</label>
                            </div>
                            <small class="text-muted">{{ translate('Coupon can be used when active') }}</small>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">{{ translate('Eligibility') }}</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">
                                <strong>{{ translate('Type') }}:</strong>
                                @if($coupon->eligibility_type === 'ALL')
                                    <span class="badge bg-primary">{{ translate('All Users') }}</span>
                                @elseif($coupon->eligibility_type === 'TARGETED')
                                    <span class="badge bg-warning text-dark">{{ translate('Targeted Users') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ translate('Segment') }}</span>
                                @endif
                            </p>
                            <small class="text-muted">{{ translate('Eligibility type cannot be changed') }}</small>

                            @if($coupon->eligibility_type === 'TARGETED')
                            <hr>
                            <a href="{{ route('admin.coupon-management.target-users', $coupon->id) }}" class="btn btn-outline-primary w-100">
                                <i class="bi bi-people me-2"></i>{{ translate('Manage Target Users') }}
                            </a>
                            @endif
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle me-2"></i>{{ translate('Update Coupon') }}
                        </button>
                        <a href="{{ route('admin.coupon-management.show', $coupon->id) }}" class="btn btn-outline-secondary">
                            {{ translate('Cancel') }}
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

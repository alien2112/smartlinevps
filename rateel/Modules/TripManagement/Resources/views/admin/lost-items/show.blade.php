@extends('adminmodule::layouts.master')

@section('title', translate('Lost Item Details'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-capitalize">{{ translate('Lost Item Details') }}</h4>
                <a href="{{ route('admin.lost-items.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> {{ translate('Back to List') }}
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Main Details Card -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Report Information') }}</h5>
                        @php
                            $statusColors = [
                                'pending' => 'warning',
                                'driver_contacted' => 'info',
                                'found' => 'success',
                                'returned' => 'primary',
                                'closed' => 'dark',
                                'no_driver_response' => 'danger'
                            ];
                        @endphp
                        <span class="badge bg-{{ $statusColors[$lostItem->status] ?? 'secondary' }} fs-6 text-capitalize">
                            {{ translate(str_replace('_', ' ', $lostItem->status)) }}
                        </span>
                    </div>
                    @if($lostItem->status == 'no_driver_response')
                        <div class="alert alert-danger m-3 mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            {{ translate('Report closed automatically due to no driver response') }}
                        </div>
                    @endif
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="text-muted mb-1 small text-uppercase">{{ translate('Report ID') }}</label>
                                <div class="d-flex align-items-center gap-2">
                                    <code class="fw-bold fs-6 text-primary bg-light px-2 py-1 rounded">#{{ $lostItem->id }}</code>
                                    <button class="btn btn-sm btn-link p-0" onclick="copyToClipboard('{{ $lostItem->id }}')" title="{{ translate('Copy ID') }}">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted mb-1">{{ translate('Category') }}</label>
                                <p><span class="badge bg-primary text-capitalize">{{ translate($lostItem->category) }}</span></p>
                            </div>
                            <div class="col-12">
                                <label class="text-muted mb-1">{{ translate('Description') }}</label>
                                <div class="bg-light p-3 rounded border">
                                    <p class="fw-medium mb-0 text-dark">{{ $lostItem->description }}</p>
                                </div>
                            </div>
                            @if($lostItem->image_url)
                            <div class="col-12">
                                <label class="text-muted mb-2">{{ translate('Item Image') }}</label>
                                <div>
                                    <a href="{{ asset('storage/app/public/' . $lostItem->image_url) }}" target="_blank">
                                        <img src="{{ asset('storage/app/public/' . $lostItem->image_url) }}" 
                                             alt="{{ translate('Lost Item') }}" 
                                             class="img-thumbnail rounded" 
                                             style="max-width: 300px; max-height: 300px; object-fit: cover;">
                                    </a>
                                </div>
                            </div>
                            @endif
                            <div class="col-md-6">
                                <label class="text-muted mb-1">{{ translate('Contact Preference') }}</label>
                                <p class="text-capitalize fw-medium text-dark">{{ translate($lostItem->contact_preference ?? 'In App') }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted mb-1">{{ translate('Item Lost At') }}</label>
                                <p class="fw-medium text-dark">{{ $lostItem->item_lost_at ? $lostItem->item_lost_at->format('d M Y, h:i A') : translate('Not specified') }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted mb-1">{{ translate('Reported On') }}</label>
                                <p class="fw-medium text-dark">{{ $lostItem->created_at->format('d M Y, h:i A') }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted mb-1">{{ translate('Last Updated') }}</label>
                                <p class="fw-medium text-dark">{{ $lostItem->updated_at->format('d M Y, h:i A') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Driver Response Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Driver Response') }}</h5>
                    </div>
                    <div class="card-body">
                        @if($lostItem->driver_response)
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="badge bg-{{ $lostItem->driver_response == 'found' ? 'success' : 'danger' }} fs-6 text-capitalize">
                                    {{ translate($lostItem->driver_response) }}
                                </span>
                            </div>
                            @if($lostItem->driver_notes)
                                <div class="bg-light p-3 rounded border">
                                    <label class="text-muted small mb-1">{{ translate('Driver Notes') }}</label>
                                    <p class="mb-0 text-dark">{{ $lostItem->driver_notes }}</p>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-hourglass-split fs-1 d-block mb-2"></i>
                                <span>{{ translate('Awaiting driver response') }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Status Timeline -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Status History') }}</h5>
                    </div>
                    <div class="card-body">
                        @if($lostItem->statusLogs && $lostItem->statusLogs->count() > 0)
                            <ul class="timeline">
                                @foreach($lostItem->statusLogs->sortByDesc('created_at') as $log)
                                    <li class="timeline-item mb-4 position-relative ps-4">
                                        <div class="timeline-marker position-absolute start-0 top-0 bg-primary rounded-circle" style="width: 12px; height: 12px; margin-top: 6px;"></div>
                                        <div class="d-flex flex-column gap-1">
                                            <p class="mb-1 fw-bold">
                                                <span class="badge bg-secondary text-capitalize">{{ translate($log->from_status) }}</span>
                                                <i class="bi bi-arrow-right mx-2 text-muted"></i>
                                                <span class="badge bg-primary text-capitalize">{{ translate($log->to_status) }}</span>
                                            </p>
                                            @if($log->notes)
                                                <p class="text-muted small mb-1 bg-light p-2 rounded d-inline-block">{{ $log->notes }}</p>
                                            @endif
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>{{ $log->created_at->format('d M Y, h:i A') }}
                                                @if($log->changedBy)
                                                    <span class="mx-1">•</span> {{ translate('by') }} {{ $log->changedBy->first_name }}
                                                @endif
                                            </small>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-clock-history fs-1 d-block mb-2"></i>
                                <p>{{ translate('No status changes recorded') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Customer Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Customer') }}</h5>
                    </div>
                    <div class="card-body">
                        @if($lostItem->customer)
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img src="{{ $lostItem->customer->profile_image ? asset('storage/app/public/customer/profile/' . $lostItem->customer->profile_image) : asset('public/assets/admin-module/img/user.png') }}" 
                                     alt="{{ translate('Customer') }}" class="rounded-circle border" width="60" height="60" style="object-fit: cover;">
                                <div>
                                    <h6 class="mb-0 fw-bold">{{ $lostItem->customer->first_name }} {{ $lostItem->customer->last_name }}</h6>
                                    <small class="text-muted">{{ $lostItem->customer->email }}</small>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="tel:{{ $lostItem->customer->phone }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-telephone me-2"></i>{{ $lostItem->customer->phone }}
                                </a>
                                <a href="{{ route('admin.customer.show', $lostItem->customer->id) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-person me-2"></i>{{ translate('View Profile') }}
                                </a>
                            </div>
                        @else
                            <p class="text-muted text-center py-3">{{ translate('Customer information not available') }}</p>
                        @endif
                    </div>
                </div>

                <!-- Driver Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Driver') }}</h5>
                    </div>
                    <div class="card-body">
                        @if($lostItem->driver)
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img src="{{ $lostItem->driver->profile_image ? asset('storage/app/public/driver/profile/' . $lostItem->driver->profile_image) : asset('public/assets/admin-module/img/user.png') }}" 
                                     alt="{{ translate('Driver') }}" class="rounded-circle border" width="60" height="60" style="object-fit: cover;">
                                <div>
                                    <h6 class="mb-0 fw-bold">{{ $lostItem->driver->first_name }} {{ $lostItem->driver->last_name }}</h6>
                                    <small class="text-muted">{{ $lostItem->driver->email }}</small>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="tel:{{ $lostItem->driver->phone }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-telephone me-2"></i>{{ $lostItem->driver->phone }}
                                </a>
                                <a href="{{ route('admin.driver.show', $lostItem->driver->id) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-person me-2"></i>{{ translate('View Profile') }}
                                </a>
                            </div>
                        @else
                            <p class="text-muted text-center py-3">{{ translate('Driver information not available') }}</p>
                        @endif
                    </div>
                </div>

                <!-- Trip Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Trip Information') }}</h5>
                    </div>
                    <div class="card-body">
                        @if($lostItem->trip)
                            <div class="mb-3">
                                <label class="text-muted small text-uppercase fw-bold">{{ translate('Trip ID') }}</label>
                                <p class="fs-5 mb-0">
                                    <a href="{{ route('admin.trip.show', ['type' => 'all', 'id' => $lostItem->trip_request_id]) }}" class="text-decoration-none fw-bold">
                                        #{{ $lostItem->trip->ref_id ?? $lostItem->trip_request_id }}
                                    </a>
                                </p>
                            </div>
                            @if($lostItem->trip->coordinate)
                                <div class="timeline-simple">
                                    <div class="mb-3 position-relative ps-4">
                                        <div class="position-absolute start-0 top-0 bg-success rounded-circle" style="width: 10px; height: 10px; margin-top: 5px;"></div>
                                        <label class="text-muted small">{{ translate('Pickup') }}</label>
                                        <p class="small mb-0 text-dark">{{ $lostItem->trip->coordinate->pickup_address ?? translate('N/A') }}</p>
                                    </div>
                                    <div class="mb-0 position-relative ps-4">
                                        <div class="position-absolute start-0 top-0 bg-danger rounded-circle" style="width: 10px; height: 10px; margin-top: 5px;"></div>
                                        <label class="text-muted small">{{ translate('Drop-off') }}</label>
                                        <p class="small mb-0 text-dark">{{ $lostItem->trip->coordinate->destination_address ?? translate('N/A') }}</p>
                                    </div>
                                </div>
                            @endif
                        @else
                            <p class="text-muted text-center py-3">{{ translate('Trip information not available') }}</p>
                        @endif
                    </div>
                </div>

                <!-- Update Status Form -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0 text-white">{{ translate('Update Status') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.lost-items.update-status', $lostItem->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <div class="mb-3">
                                <label class="form-label fw-bold">{{ translate('New Status') }}</label>
                                <select name="status" class="form-select" required>
                                    <option value="pending" {{ $lostItem->status == 'pending' ? 'selected' : '' }}>{{ translate('Pending') }}</option>
                                    <option value="driver_contacted" {{ $lostItem->status == 'driver_contacted' ? 'selected' : '' }}>{{ translate('Driver Contacted') }}</option>
                                    <option value="found" {{ $lostItem->status == 'found' ? 'selected' : '' }}>{{ translate('Found') }}</option>
                                    <option value="returned" {{ $lostItem->status == 'returned' ? 'selected' : '' }}>{{ translate('Returned') }}</option>
                                    <option value="closed" {{ $lostItem->status == 'closed' ? 'selected' : '' }}>{{ translate('Closed') }}</option>
                                    <option value="no_driver_response" {{ $lostItem->status == 'no_driver_response' ? 'selected' : '' }}>{{ translate('No Driver Response') }}</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">{{ translate('Admin Notes') }}</label>
                                <textarea name="admin_notes" class="form-control" rows="3" placeholder="{{ translate('Add notes about this update...') }}">{{ $lostItem->admin_notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check-circle me-2"></i>{{ translate('Update Status') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            toastr.success('{{ translate("ID copied to clipboard") }}');
        });
    }
</script>
@endpush

@push('css')
<style>
    .timeline {
        list-style: none;
        padding: 0;
        position: relative;
    }
    .timeline:before {
        content: '';
        position: absolute;
        left: 6px;
        top: 0;
        height: 100%;
        width: 1px;
        background: #e9ecef;
    }
    .timeline-item {
        position: relative;
    }
    .timeline-simple {
        position: relative;
    }
    .timeline-simple:before {
        content: '';
        position: absolute;
        left: 5px;
        top: 15px;
        bottom: 15px;
        width: 1px;
        background: #e9ecef;
    }
</style>
@endpush
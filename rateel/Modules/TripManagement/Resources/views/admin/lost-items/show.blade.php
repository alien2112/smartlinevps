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
                            {{ $lostItem->status == 'no_driver_response' ? translate('No Driver Response') : $lostItem->status }}
                        </span>
                    </div>
                    @if($lostItem->status == 'no_driver_response')
                        <div class="alert alert-danger m-3 mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            {{ translate('تم إغلاق هذا البلاغ تلقائياً لعدم رد الكابتن خلال المدة المحددة') }}
                        </div>
                    @endif
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="text-muted">{{ translate('Report ID') }}</label>
                                <p class="fw-bold">{{ $lostItem->id }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted">{{ translate('Category') }}</label>
                                <p><span class="badge bg-primary text-capitalize">{{ $lostItem->category }}</span></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="text-muted">{{ translate('Description') }}</label>
                                <p class="fw-bold">{{ $lostItem->description }}</p>
                            </div>
                        </div>
                        @if($lostItem->image_url)
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="text-muted">{{ translate('Item Image') }}</label>
                                    <div class="mt-2">
                                        <img src="{{ asset('storage/app/public/' . $lostItem->image_url) }}" 
                                             alt="Lost Item" class="img-thumbnail" style="max-width: 300px;">
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="text-muted">{{ translate('Contact Preference') }}</label>
                                <p class="text-capitalize">{{ $lostItem->contact_preference ?? 'In App' }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted">{{ translate('Item Lost At') }}</label>
                                <p>{{ $lostItem->item_lost_at ? $lostItem->item_lost_at->format('d M Y, h:i A') : 'Not specified' }}</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="text-muted">{{ translate('Reported On') }}</label>
                                <p>{{ $lostItem->created_at->format('d M Y, h:i A') }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted">{{ translate('Last Updated') }}</label>
                                <p>{{ $lostItem->updated_at->format('d M Y, h:i A') }}</p>
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
                                    {{ $lostItem->driver_response }}
                                </span>
                            </div>
                            @if($lostItem->driver_notes)
                                <div>
                                    <label class="text-muted">{{ translate('Driver Notes') }}</label>
                                    <p>{{ $lostItem->driver_notes }}</p>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-hourglass-split fs-1"></i>
                                <p class="mt-2">{{ translate('Awaiting driver response') }}</p>
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
                                    <li class="timeline-item mb-3">
                                        <div class="d-flex gap-3">
                                            <div class="timeline-marker bg-primary"></div>
                                            <div>
                                                <p class="mb-1">
                                                    <span class="badge bg-secondary text-capitalize">{{ $log->from_status }}</span>
                                                    <i class="bi bi-arrow-right mx-2"></i>
                                                    <span class="badge bg-primary text-capitalize">{{ $log->to_status }}</span>
                                                </p>
                                                @if($log->notes)
                                                    <p class="text-muted small mb-1">{{ $log->notes }}</p>
                                                @endif
                                                <small class="text-muted">
                                                    {{ $log->created_at->format('d M Y, h:i A') }}
                                                    @if($log->changedBy)
                                                        - {{ translate('by') }} {{ $log->changedBy->first_name }}
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-center py-4 text-muted">
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
                                     alt="Customer" class="rounded-circle" width="60" height="60">
                                <div>
                                    <h6 class="mb-0">{{ $lostItem->customer->first_name }} {{ $lostItem->customer->last_name }}</h6>
                                    <small class="text-muted">{{ $lostItem->customer->email }}</small>
                                </div>
                            </div>
                            <div class="mb-2">
                                <i class="bi bi-telephone me-2"></i>
                                <a href="tel:{{ $lostItem->customer->phone }}">{{ $lostItem->customer->phone }}</a>
                            </div>
                        @else
                            <p class="text-muted">{{ translate('Customer information not available') }}</p>
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
                                     alt="Driver" class="rounded-circle" width="60" height="60">
                                <div>
                                    <h6 class="mb-0">{{ $lostItem->driver->first_name }} {{ $lostItem->driver->last_name }}</h6>
                                    <small class="text-muted">{{ $lostItem->driver->email }}</small>
                                </div>
                            </div>
                            <div class="mb-2">
                                <i class="bi bi-telephone me-2"></i>
                                <a href="tel:{{ $lostItem->driver->phone }}">{{ $lostItem->driver->phone }}</a>
                            </div>
                        @else
                            <p class="text-muted">{{ translate('Driver information not available') }}</p>
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
                            <div class="mb-2">
                                <label class="text-muted">{{ translate('Trip ID') }}</label>
                                <p>
                                    <a href="{{ route('admin.trip.show', ['type' => 'all', 'id' => $lostItem->trip_request_id]) }}">
                                        {{ $lostItem->trip->ref_id ?? $lostItem->trip_request_id }}
                                    </a>
                                </p>
                            </div>
                            @if($lostItem->trip->coordinate)
                                <div class="mb-2">
                                    <label class="text-muted">{{ translate('Pickup') }}</label>
                                    <p class="small">{{ $lostItem->trip->coordinate->pickup_address ?? 'N/A' }}</p>
                                </div>
                                <div class="mb-2">
                                    <label class="text-muted">{{ translate('Drop-off') }}</label>
                                    <p class="small">{{ $lostItem->trip->coordinate->destination_address ?? 'N/A' }}</p>
                                </div>
                            @endif
                        @else
                            <p class="text-muted">{{ translate('Trip information not available') }}</p>
                        @endif
                    </div>
                </div>

                <!-- Update Status Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Update Status') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.lost-items.update-status', $lostItem->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <div class="mb-3">
                                <label class="form-label">{{ translate('New Status') }}</label>
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
                                <label class="form-label">{{ translate('Admin Notes') }}</label>
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

@push('css')
<style>
    .timeline {
        list-style: none;
        padding: 0;
    }
    .timeline-marker {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-top: 5px;
    }
</style>
@endpush

@extends('adminmodule::layouts.master')

@section('title', translate('Lost Item Details'))

@push('css_or_js')
    <style>
        .timeline-steps {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .timeline-steps .timeline-step {
            align-items: center;
            display: flex;
            flex-direction: column;
            position: relative;
            margin: 0 1rem;
        }
        .timeline-steps .timeline-step-content {
            width: 10rem;
            text-align: center;
        }
        .timeline-steps .timeline-step-icon {
            border-radius: 50%;
            height: 4rem;
            width: 4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border: 2px solid #e9ecef;
            z-index: 1;
            margin-bottom: 1rem;
            color: #adb5bd;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }
        .timeline-steps .timeline-step.active .timeline-step-icon {
            border-color: #007bff;
            background-color: #007bff;
            color: #fff;
            box-shadow: 0 0.5rem 1rem rgba(0, 123, 255, 0.15);
        }
        .timeline-steps .timeline-step.completed .timeline-step-icon {
            border-color: #28a745;
            background-color: #28a745;
            color: #fff;
        }
        
        .card-header-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: rgba(0, 123, 255, 0.1);
            color: #007bff;
            margin-right: 0.75rem;
        }
        
        .info-row {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px dashed #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .info-label {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-weight: 500;
            color: #212529;
            font-size: 1rem;
        }
        
        .vertical-timeline {
            position: relative;
            padding-left: 2rem;
            border-left: 2px solid #e9ecef;
        }
        .vertical-timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        .vertical-timeline-item:last-child {
            padding-bottom: 0;
        }
        .vertical-timeline-marker {
            position: absolute;
            left: -2.35rem;
            top: 0;
            width: 0.75rem;
            height: 0.75rem;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #007bff;
        }
        
        .avatar-lg {
            width: 4rem;
            height: 4rem;
            object-fit: cover;
        }
    </style>
@endpush

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h4 mb-1 text-capitalize">{{ translate('Lost Item Details') }}</h2>
                    <p class="text-muted mb-0">
                        {{ translate('Report') }} <span class="fw-bold">#{{ $lostItem->id }}</span>
                        <span class="mx-2">•</span>
                        {{ $lostItem->created_at->format('d M Y, h:i A') }}
                    </p>
                </div>
                <a href="{{ route('admin.lost-items.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> {{ translate('Back to List') }}
                </a>
            </div>

            <!-- Visual Status Tracker -->
            @php
                $statusSteps = [
                    'pending' => ['icon' => 'bi-hourglass-split', 'label' => 'pending'],
                    'driver_contacted' => ['icon' => 'bi-telephone', 'label' => 'driver_contacted'],
                    'found' => ['icon' => 'bi-search', 'label' => 'found'],
                    'returned' => ['icon' => 'bi-check-lg', 'label' => 'returned'],
                ];
                
                // Determine current step index
                $currentStatus = $lostItem->status;
                $keys = array_keys($statusSteps);
                $currentIndex = array_search($currentStatus, $keys);
                
                // Handle special end states
                $isClosed = $currentStatus == 'closed';
                $isNoResponse = $currentStatus == 'no_driver_response';
            @endphp

            @if(!$isClosed && !$isNoResponse && $currentIndex !== false)
            <div class="row justify-content-center mb-5">
                <div class="col-12">
                    <div class="timeline-steps">
                        @foreach($statusSteps as $key => $step)
                            @php
                                $stepIndex = array_search($key, $keys);
                                $isActive = $key == $currentStatus;
                                $isCompleted = $currentIndex > $stepIndex;
                            @endphp
                            <div class="timeline-step {{ $isActive ? 'active' : '' }} {{ $isCompleted ? 'completed' : '' }}">
                                <div class="timeline-step-icon">
                                    <i class="bi {{ $step['icon'] }}"></i>
                                </div>
                                <div class="timeline-step-content">
                                    <p class="text-muted mb-0 text-capitalize">{{ translate($step['label']) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <div class="row g-4">
                <!-- Left Column: Main Info -->
                <div class="col-lg-8">
                    <!-- Alerts for Special States -->
                    @if($isNoResponse)
                        <div class="alert alert-danger d-flex align-items-center mb-4 shadow-sm">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                            <div>
                                <h5 class="alert-heading mb-1">{{ translate('No Driver Response') }}</h5>
                                <p class="mb-0">{{ translate('This report was closed automatically because the driver did not respond in time.') }}</p>
                            </div>
                        </div>
                    @endif
                    @if($isClosed)
                        <div class="alert alert-dark d-flex align-items-center mb-4 shadow-sm">
                            <i class="bi bi-x-circle-fill fs-4 me-3"></i>
                            <div>
                                <h5 class="alert-heading mb-1">{{ translate('Report Closed') }}</h5>
                                <p class="mb-0">{{ translate('This case has been manually closed by an administrator.') }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Item Details Card -->
                    <div class="card shadow-sm mb-4 border-0">
                        <div class="card-header bg-white py-3 border-bottom">
                            <div class="d-flex align-items-center">
                                <span class="card-header-icon bg-primary-subtle text-primary">
                                    <i class="bi bi-box-seam"></i>
                                </span>
                                <h5 class="card-title mb-0">{{ translate('Item Information') }}</h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-7">
                                    <div class="info-row">
                                        <div class="info-label">{{ translate('Category') }}</div>
                                        <div class="info-value">
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill text-capitalize">
                                                {{ translate($lostItem->category) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">{{ translate('Description') }}</div>
                                        <div class="info-value bg-light p-3 rounded text-secondary">
                                            {{ $lostItem->description }}
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="info-row">
                                                <div class="info-label">{{ translate('Lost At') }}</div>
                                                <div class="info-value">
                                                    <i class="bi bi-calendar-event me-1 text-muted"></i>
                                                    {{ $lostItem->item_lost_at ? $lostItem->item_lost_at->format('d M Y, h:i A') : translate('Not specified') }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="info-row">
                                                <div class="info-label">{{ translate('Preference') }}</div>
                                                <div class="info-value text-capitalize">
                                                    <i class="bi bi-chat-dots me-1 text-muted"></i>
                                                    {{ translate($lostItem->contact_preference ?? 'In App') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="info-label mb-2">{{ translate('Item Image') }}</div>
                                    @if($lostItem->image_url)
                                        <a href="{{ $lostItem->image_url }}" target="_blank" class="d-block position-relative rounded overflow-hidden shadow-sm group-hover-zoom">
                                            <img src="{{ $lostItem->image_url }}" 
                                                 alt="{{ translate('Lost Item') }}" 
                                                 class="img-fluid w-100" 
                                                 style="height: 250px; object-fit: cover;">
                                            <div class="position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-50 text-white p-2 text-center small opacity-0 hover-opacity-100 transition">
                                                <i class="bi bi-zoom-in me-1"></i> {{ translate('Click to Enlarge') }}
                                            </div>
                                        </a>
                                    @else
                                        <div class="bg-light rounded d-flex flex-column align-items-center justify-content-center text-muted border border-dashed" style="height: 250px;">
                                            <i class="bi bi-image fs-1 mb-2 opacity-50"></i>
                                            <span>{{ translate('No image uploaded') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Driver Response & Status History Row -->
                    <div class="row g-4">
                        <div class="col-md-12">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-3 border-bottom">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <span class="card-header-icon bg-info-subtle text-info">
                                                <i class="bi bi-chat-left-text"></i>
                                            </span>
                                            <h5 class="card-title mb-0">{{ translate('Driver Response') }}</h5>
                                        </div>
                                        @if($lostItem->driver_response)
                                            <span class="badge bg-{{ $lostItem->driver_response == 'found' ? 'success' : 'danger' }} px-3 py-2 rounded-pill text-capitalize">
                                                {{ translate($lostItem->driver_response) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-body">
                                    @if($lostItem->driver_response)
                                        @if($lostItem->driver_notes)
                                            <div class="d-flex gap-3">
                                                <i class="bi bi-quote fs-1 text-muted opacity-25"></i>
                                                <div>
                                                    <label class="info-label">{{ translate('Driver Notes') }}</label>
                                                    <p class="fst-italic text-dark mb-0">{{ $lostItem->driver_notes }}</p>
                                                </div>
                                            </div>
                                        @else
                                            <p class="text-muted mb-0">{{ translate('Driver responded but left no notes.') }}</p>
                                        @endif
                                    @else
                                        <div class="text-center py-4">
                                            <div class="spinner-border text-primary mb-3" role="status" style="width: 2rem; height: 2rem;"></div>
                                            <p class="text-muted mb-0">{{ translate('Waiting for driver response...') }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="card shadow-sm border-0">
                                <div class="card-header bg-white py-3 border-bottom">
                                    <div class="d-flex align-items-center">
                                        <span class="card-header-icon bg-warning-subtle text-warning">
                                            <i class="bi bi-clock-history"></i>
                                        </span>
                                        <h5 class="card-title mb-0">{{ translate('Timeline') }}</h5>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="vertical-timeline ms-2">
                                        @forelse($lostItem->statusLogs as $log)
                                            <div class="vertical-timeline-item">
                                                <div class="vertical-timeline-marker"></div>
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1 fw-bold text-capitalize">
                                                            {{ translate($log->to_status) }}
                                                        </h6>
                                                        @if($log->notes)
                                                            <p class="text-muted small mb-1 bg-light p-2 rounded">{{ $log->notes }}</p>
                                                        @endif
                                                        <small class="text-muted">
                                                            {{ translate('From') }}: {{ translate($log->from_status) }}
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="d-block text-muted fw-medium">{{ $log->created_at->format('h:i A') }}</small>
                                                        <small class="text-muted" style="font-size: 0.75rem;">{{ $log->created_at->format('d M Y') }}</small>
                                                        @if($log->changedBy)
                                                            <small class="d-block mt-1 text-primary">
                                                                <i class="bi bi-person-circle me-1"></i>{{ $log->changedBy->first_name }}
                                                            </small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="text-center py-3 text-muted">
                                                {{ translate('No history available') }}
                                            </div>
                                        @endforelse
                                        
                                        <!-- Original Creation -->
                                        <div class="vertical-timeline-item">
                                            <div class="vertical-timeline-marker bg-secondary border-secondary"></div>
                                            <div>
                                                <h6 class="mb-1 fw-bold">{{ translate('Report Created') }}</h6>
                                                <small class="text-muted">{{ $lostItem->created_at->format('d M Y, h:i A') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Actions & Entities -->
                <div class="col-lg-4">
                    <!-- Action Card -->
                    <div class="card shadow-sm border-0 mb-4 bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title text-white mb-3">
                                <i class="bi bi-sliders me-2"></i>{{ translate('Manage Status') }}
                            </h5>
                            <form action="{{ route('admin.lost-items.update-status', $lostItem->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="mb-3">
                                    <label class="form-label text-white-50 small text-uppercase">{{ translate('Change Status To') }}</label>
                                    <select name="status" class="form-select border-0 shadow-none" required>
                                        @foreach(\Modules\TripManagement\Entities\LostItem::getStatuses() as $status)
                                            <option value="{{ $status }}" {{ $lostItem->status == $status ? 'selected' : '' }}>
                                                {{ translate($status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-white-50 small text-uppercase">{{ translate('Admin Note') }}</label>
                                    <textarea name="admin_notes" class="form-control border-0 shadow-none" rows="3" 
                                              placeholder="{{ translate('Optional note...') }}">{{ $lostItem->admin_notes }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-light w-100 fw-bold text-primary">
                                    {{ translate('Update Status') }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Customer Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body text-center">
                            @if($lostItem->customer)
                                <img src="{{ $lostItem->customer->profile_image ? asset('storage/app/public/customer/profile/' . $lostItem->customer->profile_image) : asset('public/assets/admin-module/img/user.png') }}" 
                                     class="avatar-lg rounded-circle mb-3 border shadow-sm" alt="Customer">
                                <h5 class="mb-1">{{ $lostItem->customer->first_name }} {{ $lostItem->customer->last_name }}</h5>
                                <p class="text-muted small mb-3">{{ translate('Customer') }}</p>
                                
                                <div class="d-flex justify-content-center gap-2 mb-3">
                                    <a href="tel:{{ $lostItem->customer->phone }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                        <i class="bi bi-telephone-fill me-1"></i> {{ translate('Call') }}
                                    </a>
                                    <a href="{{ route('admin.customer.show', $lostItem->customer->id) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                        <i class="bi bi-person-fill me-1"></i> {{ translate('Profile') }}
                                    </a>
                                </div>
                                <ul class="list-group list-group-flush text-start small">
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span class="text-muted">{{ translate('Phone') }}</span>
                                        <span class="fw-medium">{{ $lostItem->customer->phone }}</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span class="text-muted">{{ translate('Email') }}</span>
                                        <span class="fw-medium">{{ $lostItem->customer->email }}</span>
                                    </li>
                                </ul>
                            @else
                                <div class="text-muted py-3">
                                    <i class="bi bi-person-x fs-1"></i>
                                    <p class="mb-0 mt-2">{{ translate('Customer info unavailable') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Driver Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body text-center">
                            @if($lostItem->driver)
                                <img src="{{ $lostItem->driver->profile_image ? asset('storage/app/public/driver/profile/' . $lostItem->driver->profile_image) : asset('public/assets/admin-module/img/user.png') }}" 
                                     class="avatar-lg rounded-circle mb-3 border shadow-sm" alt="Driver">
                                <h5 class="mb-1">{{ $lostItem->driver->first_name }} {{ $lostItem->driver->last_name }}</h5>
                                <p class="text-muted small mb-3">{{ translate('Driver') }}</p>
                                
                                <div class="d-flex justify-content-center gap-2 mb-3">
                                    <a href="tel:{{ $lostItem->driver->phone }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                        <i class="bi bi-telephone-fill me-1"></i> {{ translate('Call') }}
                                    </a>
                                    <a href="{{ route('admin.driver.show', $lostItem->driver->id) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                        <i class="bi bi-person-fill me-1"></i> {{ translate('Profile') }}
                                    </a>
                                </div>
                                <ul class="list-group list-group-flush text-start small">
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span class="text-muted">{{ translate('Phone') }}</span>
                                        <span class="fw-medium">{{ $lostItem->driver->phone }}</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span class="text-muted">{{ translate('Email') }}</span>
                                        <span class="fw-medium">{{ $lostItem->driver->email }}</span>
                                    </li>
                                </ul>
                            @else
                                <div class="text-muted py-3">
                                    <i class="bi bi-person-x fs-1"></i>
                                    <p class="mb-0 mt-2">{{ translate('Driver info unavailable') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Trip Card -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="card-title mb-0 fs-6 fw-bold">{{ translate('Associated Trip') }}</h5>
                        </div>
                        <div class="card-body">
                            @if($lostItem->trip)
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted small">{{ translate('Ref ID') }}</span>
                                    <a href="{{ route('admin.trip.show', ['type' => 'all', 'id' => $lostItem->trip_request_id]) }}" class="badge bg-light text-dark text-decoration-none border">
                                        {{ $lostItem->trip->ref_id ?? $lostItem->trip_request_id }}
                                    </a>
                                </div>
                                
                                @if($lostItem->trip->coordinate)
                                    <div class="position-relative ps-3 border-start ms-2">
                                        <div class="mb-4 position-relative">
                                            <span class="position-absolute top-0 start-0 translate-middle bg-success rounded-circle" style="width: 10px; height: 10px; margin-left: -1px; margin-top: 5px;"></span>
                                            <label class="d-block text-success small fw-bold mb-1">{{ translate('Pickup') }}</label>
                                            <p class="small text-muted mb-0 lh-sm">{{ $lostItem->trip->coordinate->pickup_address ?? translate('N/A') }}</p>
                                        </div>
                                        <div class="position-relative">
                                            <span class="position-absolute top-0 start-0 translate-middle bg-danger rounded-circle" style="width: 10px; height: 10px; margin-left: -1px; margin-top: 5px;"></span>
                                            <label class="d-block text-danger small fw-bold mb-1">{{ translate('Drop-off') }}</label>
                                            <p class="small text-muted mb-0 lh-sm">{{ $lostItem->trip->coordinate->destination_address ?? translate('N/A') }}</p>
                                        </div>
                                    </div>
                                @endif
                            @else
                                <p class="text-muted text-center small mb-0">{{ translate('Trip info unavailable') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

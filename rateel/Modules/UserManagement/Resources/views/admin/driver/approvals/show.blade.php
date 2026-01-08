@extends('adminmodule::layouts.master')

@section('title', translate('Review Driver Application'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h2 class="fs-22 mb-0">{{ translate('Review Driver Application') }}</h2>
                <a href="{{ route('admin.driver.approvals.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> {{ translate('Back to List') }}
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row g-4">
                <!-- Driver Information Card -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="text-primary mb-3">
                                <i class="bi bi-person-fill-gear"></i> {{ translate('Driver Information') }}
                            </h5>
                            
                            <div class="text-center mb-4">
                                <img src="{{ onErrorImage(
                                    $driver->profile_image,
                                    asset('storage/app/public/driver/profile') . '/' . $driver->profile_image,
                                    asset('public/assets/admin-module/img/avatar/avatar.png'),
                                    'driver/profile/',
                                ) }}"
                                     class="rounded-circle" width="120" height="120" alt="">
                                <h5 class="mt-3 mb-0">{{ $driver->first_name }} {{ $driver->last_name }}</h5>
                                @php
                                    $stateValue = $driver->onboarding_state ?? $driver->onboarding_step ?? 'unknown';
                                    $badgeClass = match($stateValue) {
                                        'pending_approval' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'secondary'
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeClass }} mt-2">{{ ucwords(str_replace('_', ' ', $stateValue)) }}</span>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">{{ translate('Phone') }}</small>
                                <p class="mb-0"><strong>{{ $driver->phone }}</strong></p>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">{{ translate('Email') }}</small>
                                <p class="mb-0"><strong>{{ $driver->email }}</strong></p>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">{{ translate('Vehicle Type') }}</small>
                                <p class="mb-0"><span class="badge bg-primary">{{ ucfirst($driver->selected_vehicle_type ?? 'N/A') }}</span></p>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">{{ translate('Applied Date') }}</small>
                                <p class="mb-0"><strong>{{ $driver->created_at->format('d M Y, h:i A') }}</strong></p>
                            </div>

                            @if($driver->driverDetails)
                                <div class="mb-3">
                                    <small class="text-muted">{{ translate('Identity Number') }}</small>
                                    <p class="mb-0"><strong>{{ $driver->driverDetails->identity_number ?? 'N/A' }}</strong></p>
                                </div>
                            @endif

                            <!-- Approval Actions -->
                            @if(($driver->onboarding_state ?? $driver->onboarding_step) == 'pending_approval')
                                <hr>
                                <div class="d-grid gap-2 mt-4">
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                                        <i class="bi bi-check-circle"></i> {{ translate('Approve Driver') }}
                                    </button>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                        <i class="bi bi-x-circle"></i> {{ translate('Reject Driver') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Documents Section -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="bi bi-file-earmark-check"></i> {{ translate('Uploaded Documents') }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach($requiredDocs as $docType => $docName)
                                    @php
                                        $docList = $documents->get($docType, collect());
                                        $isUploaded = $docList->isNotEmpty();
                                        $hasMultiple = $docList->count() > 1;
                                        $hasLegacyDoc = isset($legacyDocs[$docType]) && $legacyDocs[$docType];
                                    @endphp
                                    
                                    <div class="col-md-6">
                                        <div class="card border {{ (!$isUploaded && !$hasLegacyDoc) ? 'border-secondary' : 'border-primary' }}">
                                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                                <h6 class="mb-0">{{ $docName }}</h6>
                                                @if($isUploaded)
                                                    <span class="badge bg-info">{{ $docList->count() }} {{ translate('file(s)') }}</span>
                                                @elseif($hasLegacyDoc)
                                                    <span class="badge bg-success">{{ translate('Doc Uploaded') }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ translate('Not Uploaded') }}</span>
                                                @endif
                                            </div>
                                            <div class="card-body text-center">
                                                @if($isUploaded)
                                                    @foreach($docList as $document)
                                                        @php
                                                            $isVerified = $document->verified ?? false;
                                                            $isRejected = $document->status === 'rejected';
                                                            $isPending = $document->status === 'pending';
                                                        @endphp
                                                        
                                                        <div class="mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <small class="text-muted">#{{ $loop->iteration }}</small>
                                                                @if($isVerified)
                                                                    <span class="badge bg-success">{{ translate('Verified') }}</span>
                                                                @elseif($isRejected)
                                                                    <span class="badge bg-danger">{{ translate('Rejected') }}</span>
                                                                @elseif($isPending)
                                                                    <span class="badge bg-warning">{{ translate('Pending') }}</span>
                                                                @endif
                                                            </div>
                                                            
                                                            <div class="position-relative mb-2">
                                                                <img src="{{ $document->file_url }}" 
                                                                     class="img-fluid rounded" 
                                                                     style="max-height: 200px; cursor: pointer;"
                                                                     onclick="window.open('{{ $document->file_url }}', '_blank')"
                                                                     alt="{{ $docName }}">
                                                            </div>
                                                            
                                                            <div class="small text-muted mb-2">
                                                                <div>{{ translate('Uploaded') }}: {{ \Carbon\Carbon::parse($document->uploaded_at)->format('d M Y, h:i A') }}</div>
                                                                <div>{{ translate('Size') }}: {{ number_format($document->file_size / 1024, 2) }} KB</div>
                                                            </div>

                                                            @if($isRejected && $document->rejection_reason)
                                                                <div class="alert alert-danger py-2 small mb-2">
                                                                    <strong>{{ translate('Rejection Reason') }}:</strong><br>
                                                                    {{ $document->rejection_reason }}
                                                                </div>
                                                            @endif

                                                            @if($isPending)
                                                                <div class="d-flex gap-2 justify-content-center">
                                                                    <form action="{{ route('admin.driver.approvals.document.verify', [$driver->id, $document->id]) }}" method="POST" class="d-inline">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('{{ translate('Are you sure you want to verify this document?') }}')">
                                                                            <i class="bi bi-check-circle"></i> {{ translate('Verify') }}
                                                                        </button>
                                                                    </form>
                                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                                            data-bs-toggle="modal" 
                                                                            data-bs-target="#rejectDocModal"
                                                                            data-driver-id="{{ $driver->id }}"
                                                                            data-document-id="{{ $document->id }}"
                                                                            data-document-name="{{ $docName }}">
                                                                        <i class="bi bi-x-circle"></i> {{ translate('Reject') }}
                                                                    </button>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @elseif($hasLegacyDoc)
                                                    <div class="py-4">
                                                        <i class="bi bi-file-earmark-check fs-1 text-success"></i>
                                                        <p class="text-success mb-2"><strong>{{ translate('Document Uploaded') }}</strong></p>
                                                        <div class="alert alert-info py-2 small mb-0">
                                                            <i class="bi bi-info-circle"></i> {{ translate('This document was uploaded using the legacy system and cannot be previewed here. Please verify separately if needed.') }}
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="py-4">
                                                        <i class="bi bi-file-earmark-x fs-1 text-muted"></i>
                                                        <p class="text-muted mb-0">{{ translate('No document uploaded') }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Document Summary -->
                            <div class="alert alert-info mt-4">
                                <div class="row text-center">
                                    <div class="col-3">
                                        <h4 class="mb-0">{{ count($requiredDocs) }}</h4>
                                        <small>{{ translate('Required Types') }}</small>
                                    </div>
                                    <div class="col-3">
                                        <h4 class="mb-0">{{ $documents->flatten()->count() }}</h4>
                                        <small>{{ translate('Total Files') }}</small>
                                    </div>
                                    <div class="col-3">
                                        <h4 class="mb-0 text-success">{{ $documents->flatten()->where('verified', true)->count() }}</h4>
                                        <small>{{ translate('Verified') }}</small>
                                    </div>
                                    <div class="col-3">
                                        <h4 class="mb-0 text-warning">{{ $documents->flatten()->where('status', 'pending')->count() }}</h4>
                                        <small>{{ translate('Pending') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Information - All Vehicles -->
                    @if($driver->vehicles && $driver->vehicles->count() > 0)
                        <div class="card mt-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-car-front"></i> {{ translate('Vehicle Information') }}
                                </h5>
                                <span class="badge bg-primary">{{ $driver->vehicles->count() }} {{ translate('Vehicle(s)') }}</span>
                            </div>
                            <div class="card-body">
                                @foreach($driver->vehicles as $vehicle)
                                    <div class="card mb-3 {{ $vehicle->is_primary ? 'border-primary' : '' }}">
                                        <div class="card-header d-flex justify-content-between align-items-center py-2 {{ $vehicle->is_primary ? 'bg-primary bg-opacity-10' : '' }}">
                                            <div>
                                                <h6 class="mb-0">
                                                    {{ $vehicle->brand?->name ?? 'N/A' }} {{ $vehicle->model?->name ?? 'N/A' }}
                                                    @if($vehicle->is_primary)
                                                        <span class="badge bg-primary ms-2">{{ translate('Primary') }}</span>
                                                    @endif
                                                </h6>
                                            </div>
                                            <div>
                                                @php
                                                    $statusClass = match($vehicle->vehicle_request_status) {
                                                        'approved' => 'success',
                                                        'pending' => 'warning',
                                                        'rejected' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $statusClass }}">
                                                    {{ ucfirst($vehicle->vehicle_request_status ?? 'pending') }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <small class="text-muted">{{ translate('Category') }}</small>
                                                    <p class="mb-0"><strong>{{ $vehicle->category?->name ?? 'N/A' }}</strong></p>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <small class="text-muted">{{ translate('License Plate') }}</small>
                                                    <p class="mb-0"><strong>{{ $vehicle->licence_plate_number ?? 'N/A' }}</strong></p>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <small class="text-muted">{{ translate('VIN Number') }}</small>
                                                    <p class="mb-0"><strong>{{ $vehicle->vin_number ?? 'N/A' }}</strong></p>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <small class="text-muted">{{ translate('License Expiry') }}</small>
                                                    <p class="mb-0"><strong>{{ $vehicle->licence_expire_date?->format('Y-m-d') ?? 'N/A' }}</strong></p>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <small class="text-muted">{{ translate('Transmission') }}</small>
                                                    <p class="mb-0"><strong>{{ ucfirst($vehicle->transmission ?? 'N/A') }}</strong></p>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <small class="text-muted">{{ translate('Fuel Type') }}</small>
                                                    <p class="mb-0"><strong>{{ ucfirst($vehicle->fuel_type ?? 'N/A') }}</strong></p>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <small class="text-muted">{{ translate('Ownership') }}</small>
                                                    <p class="mb-0"><strong>{{ ucfirst($vehicle->ownership ?? 'N/A') }}</strong></p>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <small class="text-muted">{{ translate('Created At') }}</small>
                                                    <p class="mb-0"><strong>{{ $vehicle->created_at->format('d M Y, h:i A') }}</strong></p>
                                                </div>
                                            </div>

                                            @if($vehicle->draft)
                                                <div class="alert alert-info mt-3 mb-0">
                                                    <i class="bi bi-info-circle"></i> 
                                                    {{ translate('This vehicle has pending update changes awaiting approval') }}
                                                </div>
                                            @endif

                                            @if($vehicle->has_pending_primary_request)
                                                <div class="alert alert-warning mt-3 mb-0">
                                                    <i class="bi bi-exclamation-triangle"></i> 
                                                    {{ translate('Driver requested to set this vehicle as primary') }}
                                                </div>
                                                <div class="d-flex gap-2 mt-2">
                                                    <form action="{{ route('admin.driver.approvals.vehicle.approve-primary', [$driver->id, $vehicle->id]) }}" method="POST" class="flex-fill">
                                                        @csrf
                                                        <button type="submit" class="btn btn-primary w-100 btn-sm" onclick="return confirm('{{ translate('Are you sure you want to set this as the primary vehicle?') }}')">
                                                            <i class="bi bi-check-circle"></i> {{ translate('Approve Primary Change') }}
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('admin.driver.approvals.vehicle.reject-primary', [$driver->id, $vehicle->id]) }}" method="POST" class="flex-fill">
                                                        @csrf
                                                        <button type="submit" class="btn btn-secondary w-100 btn-sm" onclick="return confirm('{{ translate('Are you sure you want to reject this primary vehicle change?') }}')">
                                                            <i class="bi bi-x-circle"></i> {{ translate('Reject Primary Change') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif

                                            @if($vehicle->vehicle_request_status === REJECTED && $vehicle->deny_note)
                                                <div class="alert alert-danger mt-3 mb-0">
                                                    <strong>{{ translate('Rejection Reason') }}:</strong><br>
                                                    {{ $vehicle->deny_note }}
                                                </div>
                                            @endif

                                            <!-- Approval Actions for Pending Vehicles -->
                                            @if($vehicle->vehicle_request_status === PENDING)
                                                <div class="d-flex gap-2 mt-3">
                                                    <form action="{{ route('admin.driver.approvals.vehicle.approve', [$driver->id, $vehicle->id]) }}" method="POST" class="flex-fill">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('{{ translate('Are you sure you want to approve this vehicle?') }}')">
                                                            <i class="bi bi-check-circle"></i> {{ translate('Approve Vehicle') }}
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-danger flex-fill" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#rejectVehicleModal"
                                                            data-driver-id="{{ $driver->id }}"
                                                            data-vehicle-id="{{ $vehicle->id }}"
                                                            data-vehicle-name="{{ $vehicle->brand?->name }} {{ $vehicle->model?->name }}">
                                                        <i class="bi bi-x-circle"></i> {{ translate('Reject Vehicle') }}
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Vehicle Images Section (Dedicated) -->
                    @if($driver->vehicles && $driver->vehicles->count() > 0)
                        @php
                            $vehiclesWithImages = $driver->vehicles->filter(function($v) {
                                return $v->documents && count($v->documents) > 0;
                            });
                        @endphp
                        
                        @if($vehiclesWithImages->count() > 0)
                            <div class="card mt-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">
                                        <i class="bi bi-images"></i> {{ translate('Vehicle Images') }}
                                    </h5>
                                </div>
                                <div class="card-body">
                                    @foreach($vehiclesWithImages as $vehicle)
                                        <div class="mb-4 pb-4 {{ !$loop->last ? 'border-bottom' : '' }}">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0">
                                                    {{ $vehicle->brand?->name ?? 'N/A' }} {{ $vehicle->model?->name ?? 'N/A' }}
                                                    <span class="badge bg-secondary ms-2">{{ $vehicle->licence_plate_number }}</span>
                                                    @if($vehicle->is_primary)
                                                        <span class="badge bg-primary ms-2">{{ translate('Primary') }}</span>
                                                    @endif
                                                </h6>
                                                @php
                                                    $statusClass = match($vehicle->vehicle_request_status) {
                                                        'approved' => 'success',
                                                        'pending' => 'warning',
                                                        'rejected' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $statusClass }}">
                                                    {{ ucfirst($vehicle->vehicle_request_status ?? 'pending') }}
                                                </span>
                                            </div>
                                            
                                            <div class="row">
                                                @foreach($vehicle->documents as $index => $document)
                                                    <div class="col-md-6 col-lg-4 mb-3">
                                                        <div class="card h-100">
                                                            <div class="card-header bg-light py-2">
                                                                <small class="text-muted fw-bold">
                                                                    @if($index === 0)
                                                                        <i class="bi bi-car-front"></i> {{ translate('Car Front') }}
                                                                    @elseif($index === 1)
                                                                        <i class="bi bi-car-front-fill"></i> {{ translate('Car Back') }}
                                                                    @else
                                                                        <i class="bi bi-file-image"></i> {{ translate('Document') }} {{ $index + 1 }}
                                                                    @endif
                                                                </small>
                                                            </div>
                                                            <div class="card-body p-2">
                                                                <a href="{{ asset('storage/' . $document) }}" target="_blank" data-lightbox="vehicle-{{ $vehicle->id }}" data-title="{{ $vehicle->brand?->name }} {{ $vehicle->model?->name }}">
                                                                    <img src="{{ asset('storage/' . $document) }}" 
                                                                         alt="{{ translate('Vehicle Image') }}" 
                                                                         class="img-fluid rounded"
                                                                         style="max-height: 250px; width: 100%; object-fit: cover; cursor: pointer;">
                                                                </a>
                                                            </div>
                                                            <div class="card-footer bg-white border-top-0 p-2">
                                                                <a href="{{ asset('storage/' . $document) }}" 
                                                                   target="_blank" 
                                                                   class="btn btn-sm btn-outline-primary w-100">
                                                                    <i class="bi bi-eye"></i> {{ translate('View Full Size') }}
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Approve Driver Application') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('admin.driver.approvals.approve', $driver->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p>{{ translate('Are you sure you want to approve this driver application?') }}</p>
                        <p class="text-muted small">{{ translate('The driver will be notified and can start accepting rides.') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button type="submit" class="btn btn-success">{{ translate('Yes, Approve') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Reject Driver Application') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('admin.driver.approvals.reject', $driver->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ translate('Rejection Reason') }}</label>
                            <textarea name="reason" class="form-control" rows="4" required 
                                      placeholder="{{ translate('Provide a clear reason for rejection...') }}"></textarea>
                        </div>
                        <p class="text-muted small">{{ translate('The driver will be notified with this reason.') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ translate('Yes, Reject') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Document Modal -->
    <div class="modal fade" id="rejectDocModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Reject Document') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectDocForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p>{{ translate('Rejecting') }}: <strong id="docName"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">{{ translate('Rejection Reason') }}</label>
                            <textarea name="reason" class="form-control" rows="3" required 
                                      placeholder="{{ translate('e.g., Image is blurry, Document is expired, etc.') }}"></textarea>
                        </div>
                        <p class="text-muted small">{{ translate('The driver will be asked to re-upload this document.') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ translate('Reject Document') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Vehicle Modal -->
    <div class="modal fade" id="rejectVehicleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Reject Vehicle') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectVehicleForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p>{{ translate('Rejecting vehicle') }}: <strong id="vehicleName"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">{{ translate('Rejection Reason') }}</label>
                            <textarea name="reason" class="form-control" rows="3" required 
                                      placeholder="{{ translate('e.g., Invalid license plate, Expired documents, etc.') }}"></textarea>
                        </div>
                        <p class="text-muted small">{{ translate('The driver will be notified with this reason.') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ translate('Reject Vehicle') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
<script>
    // Handle reject document modal
    document.getElementById('rejectDocModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const driverId = button.getAttribute('data-driver-id');
        const documentId = button.getAttribute('data-document-id');
        const documentName = button.getAttribute('data-document-name');
        
        const form = document.getElementById('rejectDocForm');
        form.action = "{{ route('admin.driver.approvals.document.reject', ['driverId' => ':driverId', 'documentId' => ':documentId']) }}"
            .replace(':driverId', driverId)
            .replace(':documentId', documentId);
        
        document.getElementById('docName').textContent = documentName;
    });

    // Handle reject vehicle modal
    const rejectVehicleModal = document.getElementById('rejectVehicleModal');
    if (rejectVehicleModal) {
        rejectVehicleModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const driverId = button.getAttribute('data-driver-id');
            const vehicleId = button.getAttribute('data-vehicle-id');
            const vehicleName = button.getAttribute('data-vehicle-name');
            
            const form = document.getElementById('rejectVehicleForm');
            form.action = "{{ route('admin.driver.approvals.vehicle.reject', ['driverId' => ':driverId', 'vehicleId' => ':vehicleId']) }}"
                .replace(':driverId', driverId)
                .replace(':vehicleId', vehicleId);
            
            document.getElementById('vehicleName').textContent = vehicleName;
        });
    }
</script>
@endpush

@extends('adminmodule::layouts.master')

@section('title', translate('Travel Approval Requests'))

@push('css')
    <style>
        .status-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.375rem;
        }
        .status-requested {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .travel-stats-card {
            border-left: 4px solid #ff6b35;
            transition: transform 0.2s;
        }
        .travel-stats-card:hover {
            transform: translateY(-2px);
        }
        .driver-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1rem;
        }
        .action-btn {
            min-width: 100px;
        }
    </style>
@endpush

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h3 class="mb-0">{{ translate('Travel Approval Requests') }}</h3>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> {{ translate('Refresh') }}
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="card travel-stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0 text-warning">{{ $counts['pending'] }}</h2>
                                    <span class="text-muted">{{ translate('Pending Requests') }}</span>
                                </div>
                                <i class="bi bi-hourglass-split fs-1 text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card travel-stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0 text-success">{{ $counts['approved'] }}</h2>
                                    <span class="text-muted">{{ translate('Approved Drivers') }}</span>
                                </div>
                                <i class="bi bi-check-circle-fill fs-1 text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card travel-stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0 text-danger">{{ $counts['rejected'] }}</h2>
                                    <span class="text-muted">{{ translate('Rejected Requests') }}</span>
                                </div>
                                <i class="bi bi-x-circle-fill fs-1 text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="card mb-3">
                <div class="card-header border-0 bg-transparent p-3">
                    <ul class="nav nav-tabs nav-tabs-bordered" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $status == 'requested' ? 'active' : '' }}" 
                               href="{{ route('admin.driver.travel-approval.index', ['status' => 'requested']) }}">
                                {{ translate('Pending') }}
                                @if($counts['pending'] > 0)
                                    <span class="badge bg-warning text-dark ms-1">{{ $counts['pending'] }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $status == 'approved' ? 'active' : '' }}"
                               href="{{ route('admin.driver.travel-approval.index', ['status' => 'approved']) }}">
                                {{ translate('Approved') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $status == 'rejected' ? 'active' : '' }}"
                               href="{{ route('admin.driver.travel-approval.index', ['status' => 'rejected']) }}">
                                {{ translate('Rejected') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $status == 'all' ? 'active' : '' }}"
                               href="{{ route('admin.driver.travel-approval.index', ['status' => 'all']) }}">
                                {{ translate('All') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ translate('Driver') }}</th>
                                    <th>{{ translate('Vehicle') }}</th>
                                    <th>{{ translate('Category') }}</th>
                                    <th>{{ translate('Status') }}</th>
                                    <th>{{ translate('Requested') }}</th>
                                    <th class="text-center">{{ translate('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $driverDetail)
                                    @php
                                        $driver = $driverDetail->user;
                                        $vehicle = $driver?->vehicle;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="{{ $driver?->profile_image ? asset('storage/' . $driver->profile_image) : asset('public/assets/admin-module/img/avatar/avatar.png') }}"
                                                     class="rounded-circle" width="40" height="40" alt="">
                                                <div>
                                                    <h6 class="mb-0">{{ $driver?->first_name }} {{ $driver?->last_name }}</h6>
                                                    <small class="text-muted">{{ $driver?->phone }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-medium">{{ $vehicle?->brand?->name ?? '-' }} {{ $vehicle?->model?->name ?? '' }}</span>
                                            <br>
                                            <small class="text-muted">{{ $vehicle?->licence_plate_number ?? '-' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">{{ $vehicle?->category?->name ?? '-' }}</span>
                                        </td>
                                        <td>
                                            @switch($driverDetail->travel_status)
                                                @case('requested')
                                                    <span class="status-badge status-requested">
                                                        <i class="bi bi-clock"></i> {{ translate('Pending') }}
                                                    </span>
                                                    @break
                                                @case('approved')
                                                    <span class="status-badge status-approved">
                                                        <i class="bi bi-check-circle"></i> {{ translate('Approved') }}
                                                    </span>
                                                    @break
                                                @case('rejected')
                                                    <span class="status-badge status-rejected">
                                                        <i class="bi bi-x-circle"></i> {{ translate('Rejected') }}
                                                    </span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td>
                                            @if($driverDetail->travel_requested_at)
                                                {{ $driverDetail->travel_requested_at->format('M d, Y') }}
                                                <br>
                                                <small class="text-muted">{{ $driverDetail->travel_requested_at->diffForHumans() }}</small>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($driverDetail->travel_status == 'requested')
                                                <div class="d-flex gap-2 justify-content-center">
                                                    <button class="btn btn-success btn-sm action-btn approve-btn"
                                                            data-driver-id="{{ $driver->id }}"
                                                            data-driver-name="{{ $driver->first_name }} {{ $driver->last_name }}">
                                                        <i class="bi bi-check-lg"></i> {{ translate('Approve') }}
                                                    </button>
                                                    <button class="btn btn-danger btn-sm action-btn reject-btn"
                                                            data-driver-id="{{ $driver->id }}"
                                                            data-driver-name="{{ $driver->first_name }} {{ $driver->last_name }}">
                                                        <i class="bi bi-x-lg"></i> {{ translate('Reject') }}
                                                    </button>
                                                </div>
                                            @elseif($driverDetail->travel_status == 'approved')
                                                <button class="btn btn-outline-danger btn-sm action-btn revoke-btn"
                                                        data-driver-id="{{ $driver->id }}"
                                                        data-driver-name="{{ $driver->first_name }} {{ $driver->last_name }}">
                                                    <i class="bi bi-shield-x"></i> {{ translate('Revoke') }}
                                                </button>
                                            @else
                                                <span class="text-muted">{{ translate('No actions') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <img src="{{ asset('public/assets/admin-module/img/empty-icons/no-data.svg') }}" 
                                                 alt="" width="100">
                                            <h5 class="mt-3 text-muted">{{ translate('No travel requests found') }}</h5>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-end mt-3">
                        {{ $requests->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Reject Travel Request') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>{{ translate('Rejecting travel request for:') }} <strong id="rejectDriverName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">{{ translate('Reason (optional)') }}</label>
                        <textarea class="form-control" id="rejectReason" rows="3" 
                                  placeholder="{{ translate('Enter reason for rejection...') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="button" class="btn btn-danger" id="confirmReject">{{ translate('Reject') }}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Revoke Modal -->
    <div class="modal fade" id="revokeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Revoke Travel Privilege') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>{{ translate('Revoking travel privilege for:') }} <strong id="revokeDriverName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">{{ translate('Reason (required)') }}</label>
                        <textarea class="form-control" id="revokeReason" rows="3" required
                                  placeholder="{{ translate('Enter reason for revocation...') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="button" class="btn btn-danger" id="confirmRevoke">{{ translate('Revoke') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        let currentDriverId = null;

        // Refresh button
        $('#refreshBtn').on('click', function() {
            location.reload();
        });

        // Approve button
        $('.approve-btn').on('click', function() {
            const driverId = $(this).data('driver-id');
            const driverName = $(this).data('driver-name');

            if (confirm('{{ translate("Are you sure you want to approve travel privilege for") }} ' + driverName + '?')) {
                approveDriver(driverId);
            }
        });

        // Reject button
        $('.reject-btn').on('click', function() {
            currentDriverId = $(this).data('driver-id');
            $('#rejectDriverName').text($(this).data('driver-name'));
            $('#rejectReason').val('');
            new bootstrap.Modal($('#rejectModal')[0]).show();
        });

        // Confirm reject
        $('#confirmReject').on('click', function() {
            rejectDriver(currentDriverId, $('#rejectReason').val());
        });

        // Revoke button
        $('.revoke-btn').on('click', function() {
            currentDriverId = $(this).data('driver-id');
            $('#revokeDriverName').text($(this).data('driver-name'));
            $('#revokeReason').val('');
            new bootstrap.Modal($('#revokeModal')[0]).show();
        });

        // Confirm revoke
        $('#confirmRevoke').on('click', function() {
            const reason = $('#revokeReason').val();
            if (!reason.trim()) {
                toastr.error('{{ translate("Reason is required for revocation") }}');
                return;
            }
            revokeDriver(currentDriverId, reason);
        });

        function approveDriver(driverId) {
            $.ajax({
                url: '{{ route("admin.driver.travel-approval.approve", "") }}/' + driverId,
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.status === 'success') {
                        toastr.success(response.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || '{{ translate("Something went wrong") }}');
                }
            });
        }

        function rejectDriver(driverId, reason) {
            $.ajax({
                url: '{{ route("admin.driver.travel-approval.reject", "") }}/' + driverId,
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', reason: reason },
                success: function(response) {
                    bootstrap.Modal.getInstance($('#rejectModal')[0]).hide();
                    if (response.status === 'success') {
                        toastr.success(response.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || '{{ translate("Something went wrong") }}');
                }
            });
        }

        function revokeDriver(driverId, reason) {
            $.ajax({
                url: '{{ route("admin.driver.travel-approval.revoke", "") }}/' + driverId,
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', reason: reason },
                success: function(response) {
                    bootstrap.Modal.getInstance($('#revokeModal')[0]).hide();
                    if (response.status === 'success') {
                        toastr.success(response.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || '{{ translate("Something went wrong") }}');
                }
            });
        }
    </script>
@endpush

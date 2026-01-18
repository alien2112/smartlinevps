@extends('adminmodule::layouts.master')

@section('title', translate('Vehicle_Change_Requests'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4">{{ translate('Vehicle Change Requests') }}</h2>

            <div class="row g-4">
                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-3">
                        <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('admin.driver.approvals.index') }}?status=pending_approval"
                                   class="nav-link {{ $status == 'pending_approval' ? 'active' : '' }}">
                                    {{ translate('Pending Approval') }}
                                    <span class="badge bg-danger ms-2">{{ $counts['pending'] }}</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('admin.driver.approvals.index') }}?status=vehicle_changes"
                                   class="nav-link {{ $status == 'vehicle_changes' ? 'active' : '' }}">
                                    {{ translate('Vehicle Changes') }}
                                    <span class="badge bg-warning ms-2">{{ $counts['vehicle_changes'] ?? 0 }}</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('admin.driver.approvals.index') }}?status=in_progress"
                                   class="nav-link {{ $status == 'in_progress' ? 'active' : '' }}">
                                    {{ translate('In Progress') }}
                                    <span class="badge bg-info ms-2">{{ $counts['in_progress'] }}</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('admin.driver.approvals.index') }}?status=approved"
                                   class="nav-link {{ $status == 'approved' ? 'active' : '' }}">
                                    {{ translate('Approved') }}
                                    <span class="badge bg-success ms-2">{{ $counts['approved'] }}</span>
                                </a>
                            </li>
                        </ul>

                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted">{{ translate('Pending Vehicle Changes') }} : </span>
                            <span class="text-warning fs-16 fw-bold">{{ $drivers->total() }}</span>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle table-hover">
                                    <thead class="table-light align-middle">
                                    <tr>
                                        <th>{{ translate('Driver Info') }}</th>
                                        <th>{{ translate('Current Vehicle') }}</th>
                                        <th>{{ translate('New Vehicle') }}</th>
                                        <th>{{ translate('Request Date') }}</th>
                                        <th class="text-center">{{ translate('Action') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($drivers as $driver)
                                        @php
                                            $currentVehicle = $driver->vehicles->where('is_primary', 1)->first();
                                            $newVehicle = $driver->vehicles->where('vehicle_request_status', 'pending')->first();
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="{{ onErrorImage(
                                                        $driver->profile_image,
                                                        asset('storage/app/public/driver/profile') . '/' . $driver->profile_image,
                                                        asset('public/assets/admin-module/img/avatar/avatar.png'),
                                                        'driver/profile/',
                                                    ) }}"
                                                         class="rounded-circle" width="40" height="40" alt="">
                                                    <div>
                                                        <h6 class="mb-0">{{ $driver->first_name }} {{ $driver->last_name }}</h6>
                                                        <small class="text-muted">{{ $driver->phone }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if($currentVehicle)
                                                    <div>
                                                        <strong>{{ $currentVehicle->brand->name ?? 'N/A' }}</strong><br>
                                                        <small class="text-muted">{{ $currentVehicle->model->name ?? 'N/A' }}</small><br>
                                                        <span class="badge bg-secondary">{{ $currentVehicle->licence_plate_number }}</span>
                                                    </div>
                                                @else
                                                    <span class="text-muted">{{ translate('No Vehicle') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($newVehicle)
                                                    <div>
                                                        <strong>{{ $newVehicle->brand->name ?? 'N/A' }}</strong><br>
                                                        <small class="text-muted">{{ $newVehicle->model->name ?? 'N/A' }}</small><br>
                                                        <span class="badge bg-warning text-dark">{{ $newVehicle->licence_plate_number }}</span>
                                                    </div>
                                                @else
                                                    <span class="text-muted">{{ translate('N/A') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($newVehicle)
                                                    <small>{{ $newVehicle->created_at->format('Y-m-d H:i') }}</small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-2">
                                                    @if($newVehicle)
                                                        <a href="{{ route('admin.driver.approvals.show', $driver->id) }}"
                                                           class="btn btn-outline-primary btn-sm"
                                                           title="{{ translate('View Details') }}">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <form action="{{ route('admin.driver.approvals.vehicle.approve-primary', [$driver->id, $newVehicle->id]) }}"
                                                              method="POST"
                                                              style="display:inline;">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="btn btn-outline-success btn-sm"
                                                                    onclick="return confirm('{{ translate('Approve this vehicle change request?') }}')"
                                                                    title="{{ translate('Approve') }}">
                                                                <i class="bi bi-check-circle"></i>
                                                            </button>
                                                        </form>
                                                        <button type="button"
                                                                class="btn btn-outline-danger btn-sm"
                                                                onclick="rejectVehicleChange('{{ $driver->id }}', '{{ $newVehicle->id }}')"
                                                                title="{{ translate('Reject') }}">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-5">
                                                <img src="{{ asset('public/assets/admin-module/img/empty-state.png') }}"
                                                     alt="" class="mb-3" style="width: 100px;">
                                                <p class="text-muted">{{ translate('No Pending Vehicle Changes') }}</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $drivers->appends(['status' => $status])->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Reject Modal --}}
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Reject Vehicle Change') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ translate('Rejection Reason') }}</label>
                            <textarea name="deny_note"
                                      class="form-control"
                                      rows="3"
                                      required
                                      placeholder="{{ translate('Enter reason for rejection...') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            {{ translate('Cancel') }}
                        </button>
                        <button type="submit" class="btn btn-danger">
                            {{ translate('Reject Request') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        function rejectVehicleChange(driverId, vehicleId) {
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            const form = document.getElementById('rejectForm');
            form.action = `{{ url('admin/driver/approvals/vehicle/reject') }}/${driverId}/${vehicleId}`;
            modal.show();
        }
    </script>
@endpush

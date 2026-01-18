@extends('adminmodule::layouts.master')

@section('title', translate('Driver_Approvals'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4">{{ translate('Driver Application Approvals') }}</h2>

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
                            <span class="text-muted">{{ translate('Total Applications') }} : </span>
                            <span class="text-primary fs-16 fw-bold">{{ $drivers->total() }}</span>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle table-hover">
                                    <thead class="table-light align-middle">
                                    <tr>
                                        <th>{{ translate('Driver Info') }}</th>
                                        <th>{{ translate('Phone') }}</th>
                                        <th>{{ translate('Vehicle Type') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th>{{ translate('Applied Date') }}</th>
                                        <th class="text-center">{{ translate('Action') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($drivers as $driver)
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
                                                        <small class="text-muted">{{ $driver->email }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $driver->phone }}</td>
                                            <td>
                                                <span class="badge bg-primary">{{ ucfirst($driver->selected_vehicle_type ?? 'N/A') }}</span>
                                            </td>
                                            <td>
                                                @php
                                                    $stateValue = $driver->onboarding_state ?? $driver->onboarding_step ?? 'unknown';
                                                    $badgeClass = match($stateValue) {
                                                        'pending_approval' => 'warning',
                                                        'approved' => 'success',
                                                        'rejected' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $badgeClass }}">{{ ucwords(str_replace('_', ' ', $stateValue)) }}</span>
                                            </td>
                                            <td>{{ $driver->created_at->format('d M Y, h:i A') }}</td>
                                            <td class="text-center">
                                                <a href="{{ route('admin.driver.approvals.show', $driver->id) }}" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-eye"></i> {{ translate('Review') }}
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <p class="text-muted mb-0">{{ translate('No applications found') }}</p>
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
@endsection

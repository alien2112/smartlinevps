@extends('adminmodule::layouts.master')

@section('title', translate('Coupon Management'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Coupon Management') }}</h2>
            <a href="{{ route('admin.coupon-management.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> {{ translate('Create Coupon') }}
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-0 text-white-50">{{ translate('Total Coupons') }}</h6>
                                <h3 class="mb-0">{{ $stats['total'] }}</h3>
                            </div>
                            <i class="bi bi-ticket-fill fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-0 text-white-50">{{ translate('Active Coupons') }}</h6>
                                <h3 class="mb-0">{{ $stats['active'] }}</h3>
                            </div>
                            <i class="bi bi-check-circle-fill fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-0 text-white-50">{{ translate('Total Redemptions') }}</h6>
                                <h3 class="mb-0">{{ $stats['total_redemptions'] }}</h3>
                            </div>
                            <i class="bi bi-arrow-repeat fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-0 opacity-75">{{ translate('Total Discount Given') }}</h6>
                                <h3 class="mb-0">{{ getCurrencyFormat($stats['total_discount_given']) }}</h3>
                            </div>
                            <i class="bi bi-cash-stack fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('admin.coupon-management.index') }}" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="{{ translate('Search code or name...') }}" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <select name="is_active" class="form-select">
                            <option value="">{{ translate('All Status') }}</option>
                            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ translate('Active') }}</option>
                            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ translate('Inactive') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="type" class="form-select">
                            <option value="">{{ translate('All Types') }}</option>
                            <option value="PERCENT" {{ request('type') === 'PERCENT' ? 'selected' : '' }}>{{ translate('Percentage') }}</option>
                            <option value="FIXED" {{ request('type') === 'FIXED' ? 'selected' : '' }}>{{ translate('Fixed Amount') }}</option>
                            <option value="FREE_RIDE_CAP" {{ request('type') === 'FREE_RIDE_CAP' ? 'selected' : '' }}>{{ translate('Free Ride') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="eligibility_type" class="form-select">
                            <option value="">{{ translate('All Eligibility') }}</option>
                            <option value="ALL" {{ request('eligibility_type') === 'ALL' ? 'selected' : '' }}>{{ translate('All Users') }}</option>
                            <option value="TARGETED" {{ request('eligibility_type') === 'TARGETED' ? 'selected' : '' }}>{{ translate('Targeted') }}</option>
                            <option value="SEGMENT" {{ request('eligibility_type') === 'SEGMENT' ? 'selected' : '' }}>{{ translate('Segment') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                        <a href="{{ route('admin.coupon-management.index') }}" class="btn btn-secondary">{{ translate('Reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Coupons Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ translate('Code') }}</th>
                                <th>{{ translate('Name') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Value') }}</th>
                                <th>{{ translate('Eligibility') }}</th>
                                <th>{{ translate('Redemptions') }}</th>
                                <th>{{ translate('Validity') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($coupons as $coupon)
                                <tr>
                                    <td>
                                        <code class="fs-6">{{ $coupon->code }}</code>
                                    </td>
                                    <td>{{ $coupon->name }}</td>
                                    <td>
                                        @if($coupon->type === 'PERCENT')
                                            <span class="badge bg-info">{{ translate('Percentage') }}</span>
                                        @elseif($coupon->type === 'FIXED')
                                            <span class="badge bg-success">{{ translate('Fixed') }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ translate('Free Ride') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($coupon->type === 'PERCENT')
                                            {{ $coupon->value }}%
                                            @if($coupon->max_discount)
                                                <small class="text-muted">(max {{ getCurrencyFormat($coupon->max_discount) }})</small>
                                            @endif
                                        @elseif($coupon->type === 'FIXED')
                                            {{ getCurrencyFormat($coupon->value) }}
                                        @else
                                            100%
                                        @endif
                                    </td>
                                    <td>
                                        @if($coupon->eligibility_type === 'ALL')
                                            <span class="badge bg-primary">{{ translate('All Users') }}</span>
                                        @elseif($coupon->eligibility_type === 'TARGETED')
                                            <span class="badge bg-warning text-dark">{{ translate('Targeted') }} ({{ $coupon->target_users_count }})</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $coupon->segment_key }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-bold">{{ $coupon->applied_redemptions_count }}</span>
                                        @if($coupon->global_limit)
                                            <small class="text-muted">/ {{ $coupon->global_limit }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <small>
                                            {{ $coupon->starts_at?->format('M d, Y') }} -<br>
                                            {{ $coupon->ends_at?->format('M d, Y') }}
                                        </small>
                                        @if($coupon->ends_at?->isPast())
                                            <span class="badge bg-danger">{{ translate('Expired') }}</span>
                                        @elseif($coupon->starts_at?->isFuture())
                                            <span class="badge bg-info">{{ translate('Scheduled') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form action="{{ route('admin.coupon-management.toggle-status', $coupon->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm {{ $coupon->is_active ? 'btn-success' : 'btn-secondary' }}">
                                                {{ $coupon->is_active ? translate('Active') : translate('Inactive') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.coupon-management.show', $coupon->id) }}">
                                                        <i class="bi bi-eye me-2"></i>{{ translate('View Details') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.coupon-management.edit', $coupon->id) }}">
                                                        <i class="bi bi-pencil me-2"></i>{{ translate('Edit') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.coupon-management.stats', $coupon->id) }}">
                                                        <i class="bi bi-bar-chart me-2"></i>{{ translate('Statistics') }}
                                                    </a>
                                                </li>
                                                @if($coupon->eligibility_type === 'TARGETED')
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.coupon-management.target-users', $coupon->id) }}">
                                                        <i class="bi bi-people me-2"></i>{{ translate('Manage Users') }}
                                                    </a>
                                                </li>
                                                @endif
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('admin.coupon-management.destroy', $coupon->id) }}" method="POST" onsubmit="return confirm('{{ translate('Are you sure you want to delete this coupon?') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="bi bi-trash me-2"></i>{{ translate('Delete') }}
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="bi bi-ticket fs-1 text-muted"></i>
                                        <p class="text-muted mt-2">{{ translate('No coupons found') }}</p>
                                        <a href="{{ route('admin.coupon-management.create') }}" class="btn btn-primary btn-sm">
                                            {{ translate('Create your first coupon') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-end mt-3">
                    {{ $coupons->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

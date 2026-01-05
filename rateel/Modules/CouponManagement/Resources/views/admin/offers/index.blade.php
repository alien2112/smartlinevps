@extends('adminmodule::layouts.master')

@section('title', translate('Offer Management'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Offer Management') }}</h2>
            <a href="{{ route('admin.offer-management.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> {{ translate('Create Offer') }}
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-0 text-white-50">{{ translate('Total Offers') }}</h6>
                                <h3 class="mb-0">{{ $stats['total'] }}</h3>
                            </div>
                            <i class="bi bi-percent fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-0 text-white-50">{{ translate('Active Offers') }}</h6>
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
                                <h6 class="mb-0 text-white-50">{{ translate('Total Usages') }}</h6>
                                <h3 class="mb-0">{{ $stats['total_usages'] }}</h3>
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
                                <h3 class="mb-0">{{ getCurrencyFormat($stats['total_discount']) }}</h3>
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
                <form action="{{ route('admin.offer-management.index') }}" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="{{ translate('Search by title...') }}" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">{{ translate('All Status') }}</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ translate('Active') }}</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ translate('Inactive') }}</option>
                            <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>{{ translate('Expired') }}</option>
                            <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>{{ translate('Scheduled') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="discount_type" class="form-select">
                            <option value="">{{ translate('All Types') }}</option>
                            <option value="percentage" {{ request('discount_type') === 'percentage' ? 'selected' : '' }}>{{ translate('Percentage') }}</option>
                            <option value="fixed" {{ request('discount_type') === 'fixed' ? 'selected' : '' }}>{{ translate('Fixed Amount') }}</option>
                            <option value="free_ride" {{ request('discount_type') === 'free_ride' ? 'selected' : '' }}>{{ translate('Free Ride') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                        <a href="{{ route('admin.offer-management.index') }}" class="btn btn-secondary">{{ translate('Reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Offers Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ translate('Offer') }}</th>
                                <th>{{ translate('Discount') }}</th>
                                <th>{{ translate('Targeting') }}</th>
                                <th>{{ translate('Usages') }}</th>
                                <th>{{ translate('Validity') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($offers as $offer)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if($offer->image)
                                                <img src="{{ asset('storage/' . $offer->image) }}" alt="" class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                            @else
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                    <i class="bi bi-percent text-muted"></i>
                                                </div>
                                            @endif
                                            <div>
                                                <strong>{{ $offer->title }}</strong>
                                                @if($offer->priority > 0)
                                                    <span class="badge bg-secondary ms-1">P{{ $offer->priority }}</span>
                                                @endif
                                                <small class="text-muted d-block">{{ Str::limit($offer->short_description, 50) }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($offer->discount_type === 'percentage')
                                            <span class="badge bg-info fs-6">{{ $offer->discount_amount }}%</span>
                                            @if($offer->max_discount)
                                                <small class="text-muted d-block">max {{ getCurrencyFormat($offer->max_discount) }}</small>
                                            @endif
                                        @elseif($offer->discount_type === 'fixed')
                                            <span class="badge bg-success fs-6">{{ getCurrencyFormat($offer->discount_amount) }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark fs-6">{{ translate('Free Ride') }}</span>
                                        @endif
                                        @if($offer->min_trip_amount > 0)
                                            <small class="text-muted d-block">min {{ getCurrencyFormat($offer->min_trip_amount) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <small>
                                            <strong>{{ translate('Zone') }}:</strong> {{ $offer->zone_type === 'all' ? 'All' : count($offer->zone_ids ?? []) . ' selected' }}<br>
                                            <strong>{{ translate('Service') }}:</strong> {{ ucfirst($offer->service_type) }}
                                        </small>
                                    </td>
                                    <td>
                                        <span class="fw-bold">{{ $offer->applied_usages_count }}</span>
                                        @if($offer->global_limit)
                                            <small class="text-muted">/ {{ $offer->global_limit }}</small>
                                        @endif
                                        <small class="text-muted d-block">{{ getCurrencyFormat($offer->total_discount_given) }} given</small>
                                    </td>
                                    <td>
                                        <small>
                                            {{ $offer->start_date?->format('M d, Y') }} -<br>
                                            {{ $offer->end_date?->format('M d, Y') }}
                                        </small>
                                    </td>
                                    <td>
                                        @php $status = $offer->status; @endphp
                                        <form action="{{ route('admin.offer-management.toggle-status', $offer->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @if($status === 'active')
                                                <button type="submit" class="btn btn-sm btn-success">{{ translate('Active') }}</button>
                                            @elseif($status === 'expired')
                                                <span class="badge bg-danger">{{ translate('Expired') }}</span>
                                            @elseif($status === 'scheduled')
                                                <span class="badge bg-info">{{ translate('Scheduled') }}</span>
                                            @elseif($status === 'exhausted')
                                                <span class="badge bg-warning text-dark">{{ translate('Exhausted') }}</span>
                                            @else
                                                <button type="submit" class="btn btn-sm btn-secondary">{{ translate('Inactive') }}</button>
                                            @endif
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.offer-management.show', $offer->id) }}">
                                                        <i class="bi bi-eye me-2"></i>{{ translate('View Details') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.offer-management.edit', $offer->id) }}">
                                                        <i class="bi bi-pencil me-2"></i>{{ translate('Edit') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.offer-management.stats', $offer->id) }}">
                                                        <i class="bi bi-bar-chart me-2"></i>{{ translate('Statistics') }}
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('admin.offer-management.destroy', $offer->id) }}" method="POST" onsubmit="return confirm('{{ translate('Are you sure?') }}')">
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
                                    <td colspan="7" class="text-center py-5">
                                        <i class="bi bi-percent fs-1 text-muted"></i>
                                        <p class="text-muted mt-2">{{ translate('No offers found') }}</p>
                                        <a href="{{ route('admin.offer-management.create') }}" class="btn btn-primary btn-sm">
                                            {{ translate('Create your first offer') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-end mt-3">
                    {{ $offers->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

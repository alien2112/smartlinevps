@extends('adminmodule::layouts.master')

@section('title', translate('Coupon Details'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fs-22 text-capitalize mb-1">
                    <code class="fs-4">{{ $coupon->code }}</code>
                    @if($coupon->is_active)
                        <span class="badge bg-success ms-2">{{ translate('Active') }}</span>
                    @else
                        <span class="badge bg-secondary ms-2">{{ translate('Inactive') }}</span>
                    @endif
                </h2>
                <p class="text-muted mb-0">{{ $coupon->name }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.coupon-management.edit', $coupon->id) }}" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> {{ translate('Edit') }}
                </a>
                <a href="{{ route('admin.coupon-management.stats', $coupon->id) }}" class="btn btn-outline-primary">
                    <i class="bi bi-bar-chart"></i> {{ translate('Statistics') }}
                </a>
                <a href="{{ route('admin.coupon-management.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Main Info -->
            <div class="col-md-8">
                <!-- Coupon Details Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Coupon Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <strong>{{ translate('Discount Type') }}:</strong>
                                <p class="mb-0">
                                    @if($coupon->type === 'PERCENT')
                                        <span class="badge bg-info fs-6">{{ $coupon->value }}% {{ translate('Off') }}</span>
                                        @if($coupon->max_discount)
                                            <small class="text-muted">({{ translate('Max') }}: {{ getCurrencyFormat($coupon->max_discount) }})</small>
                                        @endif
                                    @elseif($coupon->type === 'FIXED')
                                        <span class="badge bg-success fs-6">{{ getCurrencyFormat($coupon->value) }} {{ translate('Off') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark fs-6">{{ translate('Free Ride') }}</span>
                                        @if($coupon->max_discount)
                                            <small class="text-muted">({{ translate('Max') }}: {{ getCurrencyFormat($coupon->max_discount) }})</small>
                                        @endif
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ translate('Minimum Fare') }}:</strong>
                                <p class="mb-0">{{ getCurrencyFormat($coupon->min_fare ?? 0) }}</p>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ translate('Per User Limit') }}:</strong>
                                <p class="mb-0">{{ $coupon->per_user_limit }} {{ translate('uses per user') }}</p>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ translate('Global Limit') }}:</strong>
                                <p class="mb-0">
                                    @if($coupon->global_limit)
                                        {{ $coupon->global_used_count }} / {{ $coupon->global_limit }} {{ translate('used') }}
                                        <div class="progress mt-1" style="height: 8px;">
                                            <div class="progress-bar" style="width: {{ ($coupon->global_used_count / $coupon->global_limit) * 100 }}%"></div>
                                        </div>
                                    @else
                                        {{ translate('Unlimited') }}
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ translate('Validity Period') }}:</strong>
                                <p class="mb-0">
                                    {{ $coupon->starts_at?->format('M d, Y H:i') }} - {{ $coupon->ends_at?->format('M d, Y H:i') }}
                                    @if($coupon->ends_at?->isPast())
                                        <span class="badge bg-danger">{{ translate('Expired') }}</span>
                                    @elseif($coupon->starts_at?->isFuture())
                                        <span class="badge bg-info">{{ translate('Not Started') }}</span>
                                    @else
                                        <span class="badge bg-success">{{ translate('Active') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ translate('Eligibility') }}:</strong>
                                <p class="mb-0">
                                    @if($coupon->eligibility_type === 'ALL')
                                        <span class="badge bg-primary">{{ translate('All Users') }}</span>
                                    @elseif($coupon->eligibility_type === 'TARGETED')
                                        <span class="badge bg-warning text-dark">{{ translate('Targeted Users') }}</span>
                                        <a href="{{ route('admin.coupon-management.target-users', $coupon->id) }}" class="small">
                                            ({{ $coupon->targetUsers->count() }} {{ translate('users') }})
                                        </a>
                                    @else
                                        <span class="badge bg-secondary">{{ translate('Segment') }}: {{ $coupon->segment_key }}</span>
                                    @endif
                                </p>
                            </div>
                            @if(!empty($coupon->allowed_city_ids))
                            <div class="col-md-6">
                                <strong>{{ translate('Allowed Zones') }}:</strong>
                                <p class="mb-0">{{ count($coupon->allowed_city_ids) }} {{ translate('zones') }}</p>
                            </div>
                            @endif
                            @if(!empty($coupon->allowed_service_types))
                            <div class="col-md-6">
                                <strong>{{ translate('Allowed Services') }}:</strong>
                                <p class="mb-0">
                                    @foreach($coupon->allowed_service_types as $service)
                                        <span class="badge bg-light text-dark">{{ ucfirst($service) }}</span>
                                    @endforeach
                                </p>
                            </div>
                            @endif
                            @if($coupon->description)
                            <div class="col-12">
                                <strong>{{ translate('Description') }}:</strong>
                                <p class="mb-0">{{ $coupon->description }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Recent Redemptions -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Recent Redemptions') }}</h5>
                        <a href="{{ route('admin.coupon-management.stats', $coupon->id) }}" class="btn btn-sm btn-outline-primary">
                            {{ translate('View All') }}
                        </a>
                    </div>
                    <div class="card-body">
                        @if($recentRedemptions->isEmpty())
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mt-2">{{ translate('No redemptions yet') }}</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>{{ translate('User') }}</th>
                                            <th>{{ translate('Discount') }}</th>
                                            <th>{{ translate('Status') }}</th>
                                            <th>{{ translate('Date') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentRedemptions as $redemption)
                                            <tr>
                                                <td>
                                                    {{ $redemption->user?->first_name }} {{ $redemption->user?->last_name }}
                                                    <small class="text-muted d-block">{{ $redemption->user?->phone }}</small>
                                                </td>
                                                <td>{{ getCurrencyFormat($redemption->discount_amount) }}</td>
                                                <td>
                                                    @if($redemption->status === 'APPLIED')
                                                        <span class="badge bg-success">{{ translate('Applied') }}</span>
                                                    @elseif($redemption->status === 'RESERVED')
                                                        <span class="badge bg-warning text-dark">{{ translate('Reserved') }}</span>
                                                    @elseif($redemption->status === 'RELEASED')
                                                        <span class="badge bg-secondary">{{ translate('Released') }}</span>
                                                    @else
                                                        <span class="badge bg-danger">{{ translate('Expired') }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ $redemption->created_at?->format('M d, Y H:i') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Stats -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Quick Stats') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ translate('Total Redemptions') }}</span>
                            <strong>{{ $coupon->redemptions_count }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ translate('Applied') }}</span>
                            <strong class="text-success">{{ $coupon->applied_redemptions_count }}</strong>
                        </div>
                        @if(isset($redemptionStats['APPLIED']))
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ translate('Total Discount Given') }}</span>
                            <strong>{{ getCurrencyFormat($redemptionStats['APPLIED']->total_discount ?? 0) }}</strong>
                        </div>
                        @endif
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ translate('Created By') }}</span>
                            <strong>{{ $coupon->creator?->first_name }} {{ $coupon->creator?->last_name }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">{{ translate('Created On') }}</span>
                            <strong>{{ $coupon->created_at?->format('M d, Y') }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Quick Actions') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.coupon-management.toggle-status', $coupon->id) }}" method="POST" class="mb-3">
                            @csrf
                            <button type="submit" class="btn {{ $coupon->is_active ? 'btn-outline-warning' : 'btn-success' }} w-100">
                                @if($coupon->is_active)
                                    <i class="bi bi-pause-circle me-2"></i>{{ translate('Deactivate Coupon') }}
                                @else
                                    <i class="bi bi-play-circle me-2"></i>{{ translate('Activate Coupon') }}
                                @endif
                            </button>
                        </form>

                        @if($coupon->eligibility_type === 'TARGETED')
                        <a href="{{ route('admin.coupon-management.target-users', $coupon->id) }}" class="btn btn-outline-primary w-100 mb-3">
                            <i class="bi bi-people me-2"></i>{{ translate('Manage Target Users') }}
                        </a>
                        @endif

                        <!-- Broadcast Form -->
                        <button type="button" class="btn btn-outline-info w-100 mb-3" data-bs-toggle="modal" data-bs-target="#broadcastModal">
                            <i class="bi bi-megaphone me-2"></i>{{ translate('Send Notification') }}
                        </button>

                        <form action="{{ route('admin.coupon-management.destroy', $coupon->id) }}" method="POST" onsubmit="return confirm('{{ translate('Are you sure?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-trash me-2"></i>{{ translate('Delete Coupon') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Broadcast Modal -->
<div class="modal fade" id="broadcastModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.coupon-management.broadcast', $coupon->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Send Coupon Notification') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ translate('Notification Title') }}</label>
                        <input type="text" name="title" class="form-control" value="{{ $coupon->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ translate('Notification Body') }}</label>
                        <textarea name="body" class="form-control" rows="3" required>{{ translate('Use code') }} {{ $coupon->code }} {{ translate('to get') }} @if($coupon->type === 'PERCENT'){{ $coupon->value }}% {{ translate('off') }}@else{{ getCurrencyFormat($coupon->value) }} {{ translate('off') }}@endif!</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ translate('Send to Eligible Users') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

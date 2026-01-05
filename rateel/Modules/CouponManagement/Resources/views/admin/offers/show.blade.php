@extends('adminmodule::layouts.master')

@section('title', translate('Offer Details'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fs-22 mb-1">{{ $offer->title }}</h2>
                @php $status = $offer->status; @endphp
                <span class="badge bg-{{ $status === 'active' ? 'success' : ($status === 'expired' ? 'danger' : ($status === 'scheduled' ? 'info' : 'secondary')) }}">
                    {{ ucfirst($status) }}
                </span>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.offer-management.edit', $offer->id) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ translate('Edit') }}</a>
                <a href="{{ route('admin.offer-management.stats', $offer->id) }}" class="btn btn-outline-primary"><i class="bi bi-bar-chart"></i> {{ translate('Stats') }}</a>
                <a href="{{ route('admin.offer-management.index') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> {{ translate('Back') }}</a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-8">
                <!-- Offer Details -->
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Offer Details') }}</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <strong>{{ translate('Discount') }}:</strong>
                                <p class="mb-0">
                                    @if($offer->discount_type === 'percentage')
                                        <span class="badge bg-info fs-6">{{ $offer->discount_amount }}% {{ translate('Off') }}</span>
                                        @if($offer->max_discount) <small class="text-muted">(max {{ getCurrencyFormat($offer->max_discount) }})</small> @endif
                                    @elseif($offer->discount_type === 'fixed')
                                        <span class="badge bg-success fs-6">{{ getCurrencyFormat($offer->discount_amount) }} {{ translate('Off') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark fs-6">{{ translate('Free Ride') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ translate('Min Trip Amount') }}:</strong>
                                <p class="mb-0">{{ getCurrencyFormat($offer->min_trip_amount) }}</p>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ translate('Limit Per User') }}:</strong>
                                <p class="mb-0">{{ $offer->limit_per_user }} uses</p>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ translate('Global Limit') }}:</strong>
                                <p class="mb-0">
                                    @if($offer->global_limit)
                                        {{ $offer->total_used }} / {{ $offer->global_limit }}
                                        <div class="progress mt-1" style="height: 8px;">
                                            <div class="progress-bar" style="width: {{ min(100, ($offer->total_used / $offer->global_limit) * 100) }}%"></div>
                                        </div>
                                    @else
                                        {{ translate('Unlimited') }}
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ translate('Validity') }}:</strong>
                                <p class="mb-0">{{ $offer->start_date->format('M d, Y H:i') }} - {{ $offer->end_date->format('M d, Y H:i') }}</p>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ translate('Priority') }}:</strong>
                                <p class="mb-0">{{ $offer->priority }}</p>
                            </div>
                            <div class="col-12"><hr></div>
                            <div class="col-md-4">
                                <strong>{{ translate('Zones') }}:</strong>
                                <p class="mb-0">{{ implode(', ', $offer->zones_list) }}</p>
                            </div>
                            <div class="col-md-4">
                                <strong>{{ translate('Customer Levels') }}:</strong>
                                <p class="mb-0">{{ implode(', ', $offer->customer_levels_list) }}</p>
                            </div>
                            <div class="col-md-4">
                                <strong>{{ translate('Services') }}:</strong>
                                <p class="mb-0">{{ implode(', ', $offer->vehicle_categories_list) }}</p>
                            </div>
                            @if($offer->short_description)
                            <div class="col-12">
                                <strong>{{ translate('Description') }}:</strong>
                                <p class="mb-0">{{ $offer->short_description }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Recent Usages -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Recent Usages') }}</h5>
                    </div>
                    <div class="card-body">
                        @if($recentUsages->isEmpty())
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mt-2">{{ translate('No usages yet') }}</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>{{ translate('User') }}</th>
                                            <th>{{ translate('Trip') }}</th>
                                            <th>{{ translate('Discount') }}</th>
                                            <th>{{ translate('Status') }}</th>
                                            <th>{{ translate('Date') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentUsages as $usage)
                                        <tr>
                                            <td>{{ $usage->user?->first_name }} {{ $usage->user?->last_name }}</td>
                                            <td>{{ $usage->trip?->ref_id ?? '-' }}</td>
                                            <td>{{ getCurrencyFormat($usage->discount_amount) }}</td>
                                            <td><span class="badge bg-{{ $usage->status === 'applied' ? 'success' : 'secondary' }}">{{ ucfirst($usage->status) }}</span></td>
                                            <td>{{ $usage->created_at->format('M d, Y H:i') }}</td>
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
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Quick Stats') }}</h5></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ translate('Total Usages') }}</span>
                            <strong>{{ $offer->usages_count }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ translate('Applied') }}</span>
                            <strong class="text-success">{{ $offer->applied_usages_count }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ translate('Total Discount') }}</span>
                            <strong>{{ getCurrencyFormat($offer->total_discount_given) }}</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ translate('Created By') }}</span>
                            <strong>{{ $offer->creator?->first_name }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">{{ translate('Created On') }}</span>
                            <strong>{{ $offer->created_at->format('M d, Y') }}</strong>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Quick Actions') }}</h5></div>
                    <div class="card-body">
                        <form action="{{ route('admin.offer-management.toggle-status', $offer->id) }}" method="POST" class="mb-3">
                            @csrf
                            <button type="submit" class="btn {{ $offer->is_active ? 'btn-outline-warning' : 'btn-success' }} w-100">
                                <i class="bi bi-{{ $offer->is_active ? 'pause' : 'play' }}-circle me-2"></i>
                                {{ $offer->is_active ? translate('Deactivate') : translate('Activate') }}
                            </button>
                        </form>
                        <form action="{{ route('admin.offer-management.destroy', $offer->id) }}" method="POST" onsubmit="return confirm('{{ translate('Are you sure?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-trash me-2"></i>{{ translate('Delete Offer') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

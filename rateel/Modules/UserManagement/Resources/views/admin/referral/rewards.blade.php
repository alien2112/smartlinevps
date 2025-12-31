@extends('adminmodule::layouts.master')

@section('title', translate('Referral Rewards'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fs-22 text-capitalize mb-1">{{ translate('Referral Rewards') }}</h2>
                <p class="text-muted mb-0">{{ translate('Total Points Issued:') }} <strong>{{ number_format($totalPoints) }}</strong></p>
            </div>
            <a href="{{ route('admin.referral.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back to Dashboard') }}
            </a>
        </div>

        {{-- Filters --}}
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">{{ translate('Status') }}</label>
                        <select class="form-select" name="status">
                            <option value="">{{ translate('All') }}</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ translate('Pending') }}</option>
                            <option value="eligible" {{ request('status') === 'eligible' ? 'selected' : '' }}>{{ translate('Eligible') }}</option>
                            <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>{{ translate('Paid') }}</option>
                            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>{{ translate('Failed') }}</option>
                            <option value="fraud" {{ request('status') === 'fraud' ? 'selected' : '' }}>{{ translate('Fraud') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ translate('Trigger') }}</label>
                        <select class="form-select" name="trigger">
                            <option value="">{{ translate('All') }}</option>
                            <option value="signup" {{ request('trigger') === 'signup' ? 'selected' : '' }}>{{ translate('Signup') }}</option>
                            <option value="first_ride" {{ request('trigger') === 'first_ride' ? 'selected' : '' }}>{{ translate('First Ride') }}</option>
                            <option value="three_rides" {{ request('trigger') === 'three_rides' ? 'selected' : '' }}>{{ translate('X Rides') }}</option>
                            <option value="deposit" {{ request('trigger') === 'deposit' ? 'selected' : '' }}>{{ translate('Deposit') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ translate('Search') }}</label>
                        <input type="text" name="search" class="form-control" placeholder="{{ translate('Name or phone...') }}" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                        <a href="{{ route('admin.referral.rewards') }}" class="btn btn-outline-secondary">{{ translate('Reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Table --}}
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ translate('Referrer') }}</th>
                                <th>{{ translate('Referrer Points') }}</th>
                                <th>{{ translate('Referee') }}</th>
                                <th>{{ translate('Referee Points') }}</th>
                                <th>{{ translate('Trigger') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rewards as $reward)
                            <tr>
                                <td>
                                    @if($reward->referrer)
                                    <div>{{ $reward->referrer->first_name }} {{ $reward->referrer->last_name }}</div>
                                    <small class="text-muted">{{ $reward->referrer->phone }}</small>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-star-fill"></i> {{ $reward->referrer_points }}
                                    </span>
                                </td>
                                <td>
                                    @if($reward->referee)
                                    <div>{{ $reward->referee->first_name }} {{ $reward->referee->last_name }}</div>
                                    <small class="text-muted">{{ $reward->referee->phone }}</small>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-star-fill"></i> {{ $reward->referee_points }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-capitalize">{{ str_replace('_', ' ', $reward->trigger_type) }}</span>
                                </td>
                                <td>
                                    @php
                                        $statusColors = [
                                            'pending' => 'secondary',
                                            'eligible' => 'info',
                                            'paid' => 'success',
                                            'failed' => 'warning',
                                            'fraud' => 'danger',
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $statusColors[$reward->referrer_status] ?? 'secondary' }}">
                                        {{ ucfirst($reward->referrer_status) }}
                                    </span>
                                </td>
                                <td>{{ $reward->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">{{ translate('No rewards found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($rewards->hasPages())
            <div class="card-footer">
                {{ $rewards->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

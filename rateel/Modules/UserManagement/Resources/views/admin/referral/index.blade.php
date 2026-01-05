@extends('adminmodule::layouts.master')

@section('title', translate('Referral Dashboard'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Referral Program Dashboard') }}</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.referral.settings') }}" class="btn btn-primary">
                    <i class="bi bi-gear"></i> {{ translate('Settings') }}
                </a>
                <a href="{{ route('admin.referral.export') }}?start_date={{ $startDate }}&end_date={{ $endDate }}" class="btn btn-outline-primary">
                    <i class="bi bi-download"></i> {{ translate('Export') }}
                </a>
            </div>
        </div>

        {{-- Status Badge --}}
        <div class="alert {{ $settings->is_active ? 'alert-success' : 'alert-warning' }} d-flex align-items-center mb-4">
            <i class="bi {{ $settings->is_active ? 'bi-check-circle-fill' : 'bi-pause-circle-fill' }} me-2"></i>
            <div>
                <strong>{{ translate('Program Status:') }}</strong>
                {{ $settings->is_active ? translate('Active') : translate('Inactive') }}
                |
                <strong>{{ translate('Reward Trigger:') }}</strong>
                {{ ucfirst(str_replace('_', ' ', $settings->reward_trigger)) }}
                |
                <strong>{{ translate('Referrer Points:') }}</strong> {{ $settings->referrer_points }}
                |
                <strong>{{ translate('Referee Points:') }}</strong> {{ $settings->referee_points }}
            </div>
        </div>

        {{-- Date Filter --}}
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">{{ translate('Start Date') }}</label>
                        <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ translate('End Date') }}</label>
                        <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                        <a href="{{ route('admin.referral.index') }}" class="btn btn-outline-secondary">{{ translate('Reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-sm-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">{{ translate('Invites Sent') }}</h6>
                                <h3 class="mb-0">{{ number_format($analytics['invites_sent']) }}</h3>
                            </div>
                            <div class="icon-wrapper bg-primary-light rounded-circle p-3">
                                <i class="bi bi-send text-primary fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">{{ translate('Signups') }}</h6>
                                <h3 class="mb-0">{{ number_format($analytics['signups']) }}</h3>
                            </div>
                            <div class="icon-wrapper bg-success-light rounded-circle p-3">
                                <i class="bi bi-person-plus text-success fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">{{ translate('Conversions') }}</h6>
                                <h3 class="mb-0">{{ number_format($analytics['conversions']) }}</h3>
                            </div>
                            <div class="icon-wrapper bg-info-light rounded-circle p-3">
                                <i class="bi bi-check2-circle text-info fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">{{ translate('Points Issued') }}</h6>
                                <h3 class="mb-0">{{ number_format($analytics['total_points_issued']) }}</h3>
                            </div>
                            <div class="icon-wrapper bg-warning-light rounded-circle p-3">
                                <i class="bi bi-star text-warning fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Second Row Stats --}}
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-sm-6">
                <div class="card h-100 border-left-primary">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">{{ translate('Opened') }}</h6>
                        <h4>{{ number_format($analytics['invites_opened']) }}</h4>
                        <small class="text-muted">
                            {{ $analytics['invites_sent'] > 0 ? round(($analytics['invites_opened'] / $analytics['invites_sent']) * 100, 1) : 0 }}% {{ translate('open rate') }}
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="card h-100 border-left-success">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">{{ translate('Installs') }}</h6>
                        <h4>{{ number_format($analytics['installs']) }}</h4>
                        <small class="text-muted">
                            {{ $analytics['invites_opened'] > 0 ? round(($analytics['installs'] / $analytics['invites_opened']) * 100, 1) : 0 }}% {{ translate('install rate') }}
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="card h-100 border-left-info">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">{{ translate('First Rides') }}</h6>
                        <h4>{{ number_format($analytics['first_rides']) }}</h4>
                        <small class="text-muted">
                            {{ $analytics['signups'] > 0 ? round(($analytics['first_rides'] / $analytics['signups']) * 100, 1) : 0 }}% {{ translate('activation rate') }}
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="card h-100 border-left-danger">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">{{ translate('Fraud Blocked') }}</h6>
                        <h4>{{ number_format($analytics['fraud_blocks']) }}</h4>
                        <a href="{{ route('admin.referral.fraud-logs') }}" class="small text-danger">
                            {{ translate('View logs') }} <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            {{-- Top Referrers --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Top Referrers') }}</h5>
                        <a href="{{ route('admin.referral.leaderboard') }}" class="btn btn-sm btn-outline-primary">
                            {{ translate('View All') }}
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ translate('Name') }}</th>
                                        <th>{{ translate('Ref Code') }}</th>
                                        <th>{{ translate('Conversions') }}</th>
                                        <th>{{ translate('Points') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($analytics['top_referrers'] as $index => $referrer)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $referrer['first_name'] }} {{ $referrer['last_name'] }}</td>
                                        <td><code>{{ $referrer['ref_code'] }}</code></td>
                                        <td>{{ $referrer['period_conversions'] ?? 0 }}</td>
                                        <td>{{ number_format($referrer['period_points'] ?? 0) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4">{{ translate('No referrers yet') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fraud by Type --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Fraud Breakdown') }}</h5>
                        <a href="{{ route('admin.referral.fraud-logs') }}" class="btn btn-sm btn-outline-danger">
                            {{ translate('View Logs') }}
                        </a>
                    </div>
                    <div class="card-body">
                        @if(count($analytics['fraud_by_type']) > 0)
                        <div class="row">
                            @foreach($analytics['fraud_by_type'] as $type => $count)
                            <div class="col-6 mb-3">
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                    <span class="text-capitalize">{{ str_replace('_', ' ', $type) }}</span>
                                    <span class="badge bg-danger">{{ $count }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-4">
                            <i class="bi bi-shield-check text-success fs-1"></i>
                            <p class="mt-2 mb-0">{{ translate('No fraud detected in this period') }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Links --}}
        <div class="row g-4 mt-4">
            <div class="col-lg-3 col-sm-6">
                <a href="{{ route('admin.referral.referrals') }}" class="card text-decoration-none h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-people fs-1 text-primary"></i>
                        <h5 class="mt-2">{{ translate('All Referrals') }}</h5>
                        <p class="text-muted mb-0">{{ translate('View all referral invites') }}</p>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-sm-6">
                <a href="{{ route('admin.referral.rewards') }}" class="card text-decoration-none h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-gift fs-1 text-success"></i>
                        <h5 class="mt-2">{{ translate('Rewards') }}</h5>
                        <p class="text-muted mb-0">{{ translate('View all rewards issued') }}</p>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-sm-6">
                <a href="{{ route('admin.referral.leaderboard') }}" class="card text-decoration-none h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-trophy fs-1 text-warning"></i>
                        <h5 class="mt-2">{{ translate('Leaderboard') }}</h5>
                        <p class="text-muted mb-0">{{ translate('Top referrers ranking') }}</p>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-sm-6">
                <a href="{{ route('admin.referral.settings') }}" class="card text-decoration-none h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-gear fs-1 text-secondary"></i>
                        <h5 class="mt-2">{{ translate('Settings') }}</h5>
                        <p class="text-muted mb-0">{{ translate('Configure referral program') }}</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

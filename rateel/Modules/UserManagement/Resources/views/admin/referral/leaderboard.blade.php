@extends('adminmodule::layouts.master')

@section('title', translate('Referral Leaderboard'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Top Referrers Leaderboard') }}</h2>
            <a href="{{ route('admin.referral.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back to Dashboard') }}
            </a>
        </div>

        {{-- Period Filter --}}
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">{{ translate('Time Period') }}</label>
                        <select class="form-select" name="period">
                            <option value="week" {{ $period === 'week' ? 'selected' : '' }}>{{ translate('This Week') }}</option>
                            <option value="month" {{ $period === 'month' ? 'selected' : '' }}>{{ translate('This Month') }}</option>
                            <option value="year" {{ $period === 'year' ? 'selected' : '' }}>{{ translate('This Year') }}</option>
                            <option value="all" {{ $period === 'all' ? 'selected' : '' }}>{{ translate('All Time') }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Leaderboard Table --}}
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 60px;">{{ translate('Rank') }}</th>
                                <th>{{ translate('User') }}</th>
                                <th>{{ translate('Ref Code') }}</th>
                                <th class="text-center">{{ translate('Total Referrals') }}</th>
                                <th class="text-center">{{ translate('Successful') }}</th>
                                <th class="text-center">{{ translate('Points Earned') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topReferrers as $index => $user)
                            <tr>
                                <td class="text-center">
                                    @if($topReferrers->firstItem() + $index === 1)
                                        <span class="badge bg-warning text-dark fs-5"><i class="bi bi-trophy-fill"></i></span>
                                    @elseif($topReferrers->firstItem() + $index === 2)
                                        <span class="badge bg-secondary fs-5"><i class="bi bi-trophy-fill"></i></span>
                                    @elseif($topReferrers->firstItem() + $index === 3)
                                        <span class="badge bg-danger fs-5"><i class="bi bi-trophy-fill"></i></span>
                                    @else
                                        <span class="badge bg-light text-dark">{{ $topReferrers->firstItem() + $index }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2">
                                            @if($user->profile_image)
                                                <img src="{{ asset('storage/' . $user->profile_image) }}" alt="" class="rounded-circle" width="40" height="40">
                                            @else
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    {{ strtoupper(substr($user->first_name, 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-medium">{{ $user->first_name }} {{ $user->last_name }}</div>
                                            <small class="text-muted">{{ $user->phone }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><code>{{ $user->ref_code }}</code></td>
                                <td class="text-center">
                                    <span class="badge bg-info">{{ $user->total_referrals }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success">{{ $user->successful_referrals_period }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-star-fill"></i> {{ number_format($user->total_points_earned) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            {{ translate('Actions') }}
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('admin.customer.show', $user->id) }}">
                                                    <i class="bi bi-person me-2"></i>{{ translate('View Profile') }}
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="{{ route('admin.referral.referrals', ['search' => $user->phone]) }}">
                                                    <i class="bi bi-people me-2"></i>{{ translate('View Referrals') }}
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#blockModal{{ $user->id }}">
                                                    <i class="bi bi-ban me-2"></i>{{ translate('Block from Program') }}
                                                </button>
                                            </li>
                                        </ul>
                                    </div>

                                    {{-- Block Modal --}}
                                    <div class="modal fade" id="blockModal{{ $user->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">{{ translate('Block User from Referral Program') }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="{{ route('admin.referral.block-user', $user->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <p>{{ translate('Are you sure you want to block') }} <strong>{{ $user->first_name }} {{ $user->last_name }}</strong> {{ translate('from the referral program?') }}</p>
                                                        <p class="text-danger small">{{ translate('This will cancel all pending rewards and mark their referrals as fraud.') }}</p>
                                                        <div class="mb-3">
                                                            <label class="form-label">{{ translate('Reason') }}</label>
                                                            <textarea name="reason" class="form-control" rows="2" required placeholder="{{ translate('Enter reason for blocking...') }}"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                                                        <button type="submit" class="btn btn-danger">{{ translate('Block User') }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">{{ translate('No referrers found for this period') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($topReferrers->hasPages())
            <div class="card-footer">
                {{ $topReferrers->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@extends('adminmodule::layouts.master')

@section('title', translate('Fraud Logs'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fs-22 text-capitalize mb-1">{{ translate('Fraud Detection Logs') }}</h2>
                <p class="text-muted mb-0">{{ translate('Track suspicious referral activities') }}</p>
            </div>
            <a href="{{ route('admin.referral.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back to Dashboard') }}
            </a>
        </div>

        {{-- Stats --}}
        <div class="row g-4 mb-4">
            @foreach($fraudStats as $type => $count)
            <div class="col-lg-3 col-sm-6">
                <div class="card h-100 border-danger">
                    <div class="card-body">
                        <h6 class="text-muted text-capitalize mb-1">{{ str_replace('_', ' ', $type) }}</h6>
                        <h3 class="text-danger">{{ number_format($count) }}</h3>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Filters --}}
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">{{ translate('Fraud Type') }}</label>
                        <select class="form-select" name="type">
                            <option value="">{{ translate('All Types') }}</option>
                            <option value="self_referral" {{ request('type') === 'self_referral' ? 'selected' : '' }}>{{ translate('Self Referral') }}</option>
                            <option value="same_device" {{ request('type') === 'same_device' ? 'selected' : '' }}>{{ translate('Same Device') }}</option>
                            <option value="same_ip" {{ request('type') === 'same_ip' ? 'selected' : '' }}>{{ translate('Same IP') }}</option>
                            <option value="velocity_limit" {{ request('type') === 'velocity_limit' ? 'selected' : '' }}>{{ translate('Velocity Limit') }}</option>
                            <option value="expired_invite" {{ request('type') === 'expired_invite' ? 'selected' : '' }}>{{ translate('Expired Invite') }}</option>
                            <option value="blocked_user" {{ request('type') === 'blocked_user' ? 'selected' : '' }}>{{ translate('Blocked User') }}</option>
                            <option value="phone_not_verified" {{ request('type') === 'phone_not_verified' ? 'selected' : '' }}>{{ translate('Phone Not Verified') }}</option>
                            <option value="suspicious_pattern" {{ request('type') === 'suspicious_pattern' ? 'selected' : '' }}>{{ translate('Suspicious Pattern') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ translate('Start Date') }}</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ translate('End Date') }}</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                        <a href="{{ route('admin.referral.fraud-logs') }}" class="btn btn-outline-secondary">{{ translate('Reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Logs Table --}}
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ translate('Date/Time') }}</th>
                                <th>{{ translate('User') }}</th>
                                <th>{{ translate('Fraud Type') }}</th>
                                <th>{{ translate('Details') }}</th>
                                <th>{{ translate('IP Address') }}</th>
                                <th>{{ translate('Device') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr>
                                <td>
                                    <div>{{ $log->created_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $log->created_at->format('H:i:s') }}</small>
                                </td>
                                <td>
                                    @if($log->user)
                                    <div>{{ $log->user->first_name }} {{ $log->user->last_name }}</div>
                                    <small class="text-muted">{{ $log->user->phone }}</small>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $typeColors = [
                                            'self_referral' => 'danger',
                                            'same_device' => 'warning',
                                            'same_ip' => 'warning',
                                            'velocity_limit' => 'info',
                                            'expired_invite' => 'secondary',
                                            'blocked_user' => 'dark',
                                            'phone_not_verified' => 'secondary',
                                            'suspicious_pattern' => 'danger',
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $typeColors[$log->fraud_type] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $log->fraud_type)) }}
                                    </span>
                                </td>
                                <td>
                                    @if($log->details)
                                        @php $details = is_array($log->details) ? $log->details : json_decode($log->details, true); @endphp
                                        <small>
                                            @foreach(array_slice($details ?? [], 0, 2) as $key => $value)
                                                <div><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ is_array($value) ? json_encode($value) : $value }}</div>
                                            @endforeach
                                            @if(count($details ?? []) > 2)
                                                <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#detailsModal{{ $log->id }}">
                                                    {{ translate('View more...') }}
                                                </a>
                                            @endif
                                        </small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><code>{{ $log->ip_address ?? '-' }}</code></td>
                                <td>
                                    @if($log->device_fingerprint)
                                        <code class="small">{{ Str::limit($log->device_fingerprint, 15) }}</code>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($log->invite)
                                    <a href="{{ route('admin.referral.show', $log->referral_invite_id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @endif
                                </td>
                            </tr>

                            {{-- Details Modal --}}
                            @if($log->details && count(is_array($log->details) ? $log->details : json_decode($log->details, true) ?? []) > 2)
                            <div class="modal fade" id="detailsModal{{ $log->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">{{ translate('Fraud Log Details') }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <table class="table table-sm">
                                                @foreach((is_array($log->details) ? $log->details : json_decode($log->details, true)) ?? [] as $key => $value)
                                                <tr>
                                                    <td class="fw-medium text-capitalize">{{ str_replace('_', ' ', $key) }}</td>
                                                    <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
                                                </tr>
                                                @endforeach
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="bi bi-shield-check text-success fs-1 d-block mb-2"></i>
                                    {{ translate('No fraud logs found') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($logs->hasPages())
            <div class="card-footer">
                {{ $logs->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

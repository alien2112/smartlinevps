@extends('adminmodule::layouts.master')

@section('title', translate('Referral Details'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Referral Details') }}</h2>
            <a href="{{ route('admin.referral.referrals') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back to List') }}
            </a>
        </div>

        <div class="row g-4">
            {{-- Referral Info Card --}}
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Referral Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted mb-2">{{ translate('Referrer (Inviter)') }}</h6>
                                    @if($invite->referrer)
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3">
                                            @if($invite->referrer->profile_image)
                                                <img src="{{ asset('storage/' . $invite->referrer->profile_image) }}" alt="" class="rounded-circle" width="50" height="50">
                                            @else
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                    {{ strtoupper(substr($invite->referrer->first_name, 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-medium">{{ $invite->referrer->first_name }} {{ $invite->referrer->last_name }}</div>
                                            <small class="text-muted">{{ $invite->referrer->phone }}</small>
                                            <div>
                                                <code>{{ $invite->referrer->ref_code }}</code>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <a href="{{ route('admin.customer.show', $invite->referrer->id) }}" class="btn btn-sm btn-outline-primary">
                                            {{ translate('View Profile') }}
                                        </a>
                                    </div>
                                    @else
                                    <span class="text-muted">{{ translate('N/A') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted mb-2">{{ translate('Referee (Invited)') }}</h6>
                                    @if($invite->referee)
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3">
                                            @if($invite->referee->profile_image)
                                                <img src="{{ asset('storage/' . $invite->referee->profile_image) }}" alt="" class="rounded-circle" width="50" height="50">
                                            @else
                                                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                    {{ strtoupper(substr($invite->referee->first_name, 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-medium">{{ $invite->referee->first_name }} {{ $invite->referee->last_name }}</div>
                                            <small class="text-muted">{{ $invite->referee->phone }}</small>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <a href="{{ route('admin.customer.show', $invite->referee->id) }}" class="btn btn-sm btn-outline-primary">
                                            {{ translate('View Profile') }}
                                        </a>
                                    </div>
                                    @else
                                    <span class="text-muted">{{ translate('Not signed up yet') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Timeline Card --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Referral Journey') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            {{-- Sent --}}
                            <div class="timeline-item {{ $invite->sent_at ? 'completed' : '' }}">
                                <div class="timeline-marker {{ $invite->sent_at ? 'bg-success' : 'bg-secondary' }}">
                                    <i class="bi bi-send-fill text-white"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>{{ translate('Invite Sent') }}</h6>
                                    @if($invite->sent_at)
                                        <small class="text-muted">{{ $invite->sent_at->format('M d, Y H:i:s') }}</small>
                                        <div><span class="badge bg-secondary text-capitalize">{{ $invite->invite_channel }}</span></div>
                                    @else
                                        <small class="text-muted">{{ translate('Pending') }}</small>
                                    @endif
                                </div>
                            </div>

                            {{-- Opened --}}
                            <div class="timeline-item {{ $invite->opened_at ? 'completed' : '' }}">
                                <div class="timeline-marker {{ $invite->opened_at ? 'bg-success' : 'bg-secondary' }}">
                                    <i class="bi bi-eye-fill text-white"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>{{ translate('Link Opened') }}</h6>
                                    @if($invite->opened_at)
                                        <small class="text-muted">{{ $invite->opened_at->format('M d, Y H:i:s') }}</small>
                                    @else
                                        <small class="text-muted">{{ translate('Not yet') }}</small>
                                    @endif
                                </div>
                            </div>

                            {{-- Installed --}}
                            <div class="timeline-item {{ $invite->installed_at ? 'completed' : '' }}">
                                <div class="timeline-marker {{ $invite->installed_at ? 'bg-success' : 'bg-secondary' }}">
                                    <i class="bi bi-download text-white"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>{{ translate('App Installed') }}</h6>
                                    @if($invite->installed_at)
                                        <small class="text-muted">{{ $invite->installed_at->format('M d, Y H:i:s') }}</small>
                                    @else
                                        <small class="text-muted">{{ translate('Not yet') }}</small>
                                    @endif
                                </div>
                            </div>

                            {{-- Signed Up --}}
                            <div class="timeline-item {{ $invite->signup_at ? 'completed' : '' }}">
                                <div class="timeline-marker {{ $invite->signup_at ? 'bg-success' : 'bg-secondary' }}">
                                    <i class="bi bi-person-plus-fill text-white"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>{{ translate('Account Created') }}</h6>
                                    @if($invite->signup_at)
                                        <small class="text-muted">{{ $invite->signup_at->format('M d, Y H:i:s') }}</small>
                                    @else
                                        <small class="text-muted">{{ translate('Not yet') }}</small>
                                    @endif
                                </div>
                            </div>

                            {{-- First Ride --}}
                            <div class="timeline-item {{ $invite->first_ride_at ? 'completed' : '' }}">
                                <div class="timeline-marker {{ $invite->first_ride_at ? 'bg-success' : 'bg-secondary' }}">
                                    <i class="bi bi-car-front-fill text-white"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>{{ translate('First Ride Completed') }}</h6>
                                    @if($invite->first_ride_at)
                                        <small class="text-muted">{{ $invite->first_ride_at->format('M d, Y H:i:s') }}</small>
                                    @else
                                        <small class="text-muted">{{ translate('Not yet') }}</small>
                                    @endif
                                </div>
                            </div>

                            {{-- Reward --}}
                            <div class="timeline-item {{ $invite->reward_at ? 'completed' : '' }}">
                                <div class="timeline-marker {{ $invite->reward_at ? 'bg-warning' : 'bg-secondary' }}">
                                    <i class="bi bi-gift-fill text-white"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>{{ translate('Reward Issued') }}</h6>
                                    @if($invite->reward_at)
                                        <small class="text-muted">{{ $invite->reward_at->format('M d, Y H:i:s') }}</small>
                                    @else
                                        <small class="text-muted">{{ translate('Pending') }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Fraud Logs --}}
                @if($fraudLogs->count() > 0)
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-shield-exclamation me-2"></i>{{ translate('Fraud Alerts') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ translate('Date') }}</th>
                                        <th>{{ translate('Type') }}</th>
                                        <th>{{ translate('Details') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($fraudLogs as $log)
                                    <tr>
                                        <td>{{ $log->created_at->format('M d, Y H:i') }}</td>
                                        <td>
                                            <span class="badge bg-danger">{{ ucfirst(str_replace('_', ' ', $log->fraud_type)) }}</span>
                                        </td>
                                        <td>
                                            @if($log->details)
                                                @php $details = is_array($log->details) ? $log->details : json_decode($log->details, true); @endphp
                                                <small>
                                                    @foreach($details ?? [] as $key => $value)
                                                        {{ ucfirst(str_replace('_', ' ', $key)) }}: {{ is_array($value) ? json_encode($value) : $value }}<br>
                                                    @endforeach
                                                </small>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Side Info --}}
            <div class="col-lg-4">
                {{-- Status Card --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Status') }}</h5>
                    </div>
                    <div class="card-body">
                        @php
                            $statusColors = [
                                'sent' => 'secondary',
                                'opened' => 'info',
                                'installed' => 'primary',
                                'signed_up' => 'warning',
                                'converted' => 'success',
                                'rewarded' => 'success',
                                'expired' => 'dark',
                                'fraud_blocked' => 'danger',
                            ];
                        @endphp
                        <div class="text-center mb-3">
                            <span class="badge bg-{{ $statusColors[$invite->status] ?? 'secondary' }} fs-5 px-4 py-2">
                                {{ ucfirst(str_replace('_', ' ', $invite->status)) }}
                            </span>
                        </div>

                        @if($invite->fraud_reason)
                        <div class="alert alert-danger">
                            <strong>{{ translate('Fraud Reason:') }}</strong><br>
                            {{ $invite->fraud_reason }}
                        </div>
                        @endif

                        <table class="table table-sm">
                            <tr>
                                <td class="fw-medium">{{ translate('Invite Code') }}</td>
                                <td><code>{{ $invite->invite_code }}</code></td>
                            </tr>
                            <tr>
                                <td class="fw-medium">{{ translate('Channel') }}</td>
                                <td><span class="text-capitalize">{{ $invite->invite_channel }}</span></td>
                            </tr>
                            <tr>
                                <td class="fw-medium">{{ translate('Expires At') }}</td>
                                <td>{{ $invite->expires_at?->format('M d, Y') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-medium">{{ translate('Created') }}</td>
                                <td>{{ $invite->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                {{-- Reward Card --}}
                @if($invite->reward)
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="bi bi-star-fill me-2"></i>{{ translate('Reward Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td class="fw-medium">{{ translate('Trigger') }}</td>
                                <td><span class="text-capitalize">{{ str_replace('_', ' ', $invite->reward->trigger_type) }}</span></td>
                            </tr>
                            <tr>
                                <td class="fw-medium">{{ translate('Referrer Points') }}</td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-star-fill"></i> {{ $invite->reward->referrer_points }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-medium">{{ translate('Referee Points') }}</td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-star-fill"></i> {{ $invite->reward->referee_points }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-medium">{{ translate('Referrer Status') }}</td>
                                <td>
                                    @php
                                        $rewardStatusColors = [
                                            'pending' => 'secondary',
                                            'eligible' => 'info',
                                            'paid' => 'success',
                                            'failed' => 'warning',
                                            'fraud' => 'danger',
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $rewardStatusColors[$invite->reward->referrer_status] ?? 'secondary' }}">
                                        {{ ucfirst($invite->reward->referrer_status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-medium">{{ translate('Referee Status') }}</td>
                                <td>
                                    <span class="badge bg-{{ $rewardStatusColors[$invite->reward->referee_status] ?? 'secondary' }}">
                                        {{ ucfirst($invite->reward->referee_status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-medium">{{ translate('Issued At') }}</td>
                                <td>{{ $invite->reward->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Technical Details --}}
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Technical Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td class="fw-medium">{{ translate('Referrer IP') }}</td>
                                <td><code>{{ $invite->referrer_ip ?? '-' }}</code></td>
                            </tr>
                            <tr>
                                <td class="fw-medium">{{ translate('Referee IP') }}</td>
                                <td><code>{{ $invite->referee_ip ?? '-' }}</code></td>
                            </tr>
                            <tr>
                                <td class="fw-medium">{{ translate('Referrer Device') }}</td>
                                <td><code class="small">{{ Str::limit($invite->referrer_device_fingerprint ?? '-', 20) }}</code></td>
                            </tr>
                            <tr>
                                <td class="fw-medium">{{ translate('Referee Device') }}</td>
                                <td><code class="small">{{ Str::limit($invite->referee_device_fingerprint ?? '-', 20) }}</code></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 40px;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
    border-left: 2px solid #dee2e6;
    margin-left: 15px;
}
.timeline-item.completed {
    border-left-color: #198754;
}
.timeline-item:last-child {
    border-left: none;
    padding-bottom: 0;
}
.timeline-marker {
    position: absolute;
    left: -23px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.timeline-content {
    padding-left: 20px;
    padding-bottom: 10px;
}
</style>
@endsection

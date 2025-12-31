@extends('adminmodule::layouts.master')

@section('title', translate('All Referrals'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('All Referrals') }}</h2>
            <a href="{{ route('admin.referral.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back to Dashboard') }}
            </a>
        </div>

        {{-- Filters --}}
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">{{ translate('Status') }}</label>
                        <select class="form-select" name="status">
                            <option value="">{{ translate('All') }}</option>
                            <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>{{ translate('Sent') }}</option>
                            <option value="opened" {{ request('status') === 'opened' ? 'selected' : '' }}>{{ translate('Opened') }}</option>
                            <option value="installed" {{ request('status') === 'installed' ? 'selected' : '' }}>{{ translate('Installed') }}</option>
                            <option value="signed_up" {{ request('status') === 'signed_up' ? 'selected' : '' }}>{{ translate('Signed Up') }}</option>
                            <option value="converted" {{ request('status') === 'converted' ? 'selected' : '' }}>{{ translate('Converted') }}</option>
                            <option value="rewarded" {{ request('status') === 'rewarded' ? 'selected' : '' }}>{{ translate('Rewarded') }}</option>
                            <option value="fraud_blocked" {{ request('status') === 'fraud_blocked' ? 'selected' : '' }}>{{ translate('Fraud Blocked') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ translate('Channel') }}</label>
                        <select class="form-select" name="channel">
                            <option value="">{{ translate('All') }}</option>
                            <option value="link" {{ request('channel') === 'link' ? 'selected' : '' }}>{{ translate('Link') }}</option>
                            <option value="code" {{ request('channel') === 'code' ? 'selected' : '' }}>{{ translate('Code') }}</option>
                            <option value="qr" {{ request('channel') === 'qr' ? 'selected' : '' }}>{{ translate('QR') }}</option>
                            <option value="sms" {{ request('channel') === 'sms' ? 'selected' : '' }}>{{ translate('SMS') }}</option>
                            <option value="whatsapp" {{ request('channel') === 'whatsapp' ? 'selected' : '' }}>{{ translate('WhatsApp') }}</option>
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
                    <div class="col-md-2">
                        <label class="form-label">{{ translate('Search') }}</label>
                        <input type="text" name="search" class="form-control" placeholder="{{ translate('Name, phone, code...') }}" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">{{ translate('Filter') }}</button>
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
                                <th>{{ translate('Code') }}</th>
                                <th>{{ translate('Referee') }}</th>
                                <th>{{ translate('Channel') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Sent At') }}</th>
                                <th>{{ translate('Signup At') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($referrals as $referral)
                            <tr>
                                <td>
                                    @if($referral->referrer)
                                    <div>{{ $referral->referrer->first_name }} {{ $referral->referrer->last_name }}</div>
                                    <small class="text-muted">{{ $referral->referrer->phone }}</small>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><code>{{ $referral->invite_code }}</code></td>
                                <td>
                                    @if($referral->referee)
                                    <div>{{ $referral->referee->first_name }} {{ $referral->referee->last_name }}</div>
                                    <small class="text-muted">{{ $referral->referee->phone }}</small>
                                    @else
                                    <span class="text-muted">{{ translate('Not signed up') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-secondary text-capitalize">{{ $referral->invite_channel }}</span>
                                </td>
                                <td>
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
                                    <span class="badge bg-{{ $statusColors[$referral->status] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $referral->status)) }}
                                    </span>
                                    @if($referral->fraud_reason)
                                    <br><small class="text-danger">{{ $referral->fraud_reason }}</small>
                                    @endif
                                </td>
                                <td>{{ $referral->sent_at?->format('M d, Y H:i') }}</td>
                                <td>{{ $referral->signup_at?->format('M d, Y H:i') ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('admin.referral.show', $referral->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">{{ translate('No referrals found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($referrals->hasPages())
            <div class="card-footer">
                {{ $referrals->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

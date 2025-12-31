@extends('adminmodule::layouts.master')

@section('title', translate('Referral Settings'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Referral Program Settings') }}</h2>
            <a href="{{ route('admin.referral.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back to Dashboard') }}
            </a>
        </div>

        <form action="{{ route('admin.referral.settings.update') }}" method="POST">
            @csrf
            @method('PUT')

            {{-- Program Status --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-toggle-on"></i>
                        {{ translate('Program Status') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                    {{ $settings->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    <strong>{{ translate('Referral Program Active') }}</strong>
                                    <br><small class="text-muted">{{ translate('Enable or disable the entire referral program') }}</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="show_leaderboard" id="show_leaderboard"
                                    {{ $settings->show_leaderboard ? 'checked' : '' }}>
                                <label class="form-check-label" for="show_leaderboard">
                                    <strong>{{ translate('Show Leaderboard') }}</strong>
                                    <br><small class="text-muted">{{ translate('Allow users to see top referrers') }}</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Points Configuration --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-star"></i>
                        {{ translate('Points Configuration') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Referrer Points') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-star-fill text-warning"></i></span>
                                <input type="number" class="form-control" name="referrer_points"
                                    value="{{ old('referrer_points', $settings->referrer_points) }}"
                                    min="0" max="10000" required>
                            </div>
                            <small class="text-muted">{{ translate('Points given to the user who invites') }}</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Referee Points') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-star-fill text-warning"></i></span>
                                <input type="number" class="form-control" name="referee_points"
                                    value="{{ old('referee_points', $settings->referee_points) }}"
                                    min="0" max="10000" required>
                            </div>
                            <small class="text-muted">{{ translate('Points given to the new user who signs up') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Reward Trigger --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-bullseye"></i>
                        {{ translate('Reward Trigger') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('When to Issue Reward') }} <span class="text-danger">*</span></label>
                            <select class="form-select" name="reward_trigger" id="reward_trigger" required>
                                <option value="signup" {{ $settings->reward_trigger === 'signup' ? 'selected' : '' }}>
                                    {{ translate('On Signup') }} - {{ translate('When referee creates account') }}
                                </option>
                                <option value="first_ride" {{ $settings->reward_trigger === 'first_ride' ? 'selected' : '' }}>
                                    {{ translate('On First Ride') }} - {{ translate('When referee completes first paid ride') }}
                                </option>
                                <option value="three_rides" {{ $settings->reward_trigger === 'three_rides' ? 'selected' : '' }}>
                                    {{ translate('On X Rides') }} - {{ translate('When referee completes X paid rides') }}
                                </option>
                                <option value="deposit" {{ $settings->reward_trigger === 'deposit' ? 'selected' : '' }}>
                                    {{ translate('On First Deposit') }} - {{ translate('When referee adds money to wallet') }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3" id="required_rides_wrapper" style="{{ $settings->reward_trigger === 'three_rides' ? '' : 'display:none' }}">
                            <label class="form-label">{{ translate('Required Rides') }}</label>
                            <input type="number" class="form-control" name="required_rides"
                                value="{{ old('required_rides', $settings->required_rides) }}"
                                min="1" max="100">
                            <small class="text-muted">{{ translate('Number of rides required') }}</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Minimum Ride Fare') }}</label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control" name="min_ride_fare"
                                    value="{{ old('min_ride_fare', $settings->min_ride_fare) }}"
                                    min="0">
                                <span class="input-group-text">{{ get_cache('currency_code') ?? 'EGP' }}</span>
                            </div>
                            <small class="text-muted">{{ translate('Minimum fare for ride to count') }}</small>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>{{ translate('Recommended:') }}</strong>
                        {{ translate('Use "On First Ride" trigger to ensure users are actually using the app before rewarding referrers. This prevents fake account abuse.') }}
                    </div>
                </div>
            </div>

            {{-- Limits --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-speedometer2"></i>
                        {{ translate('Limits & Thresholds') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Max Referrals Per Day') }} <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="max_referrals_per_day"
                                value="{{ old('max_referrals_per_day', $settings->max_referrals_per_day) }}"
                                min="1" max="1000" required>
                            <small class="text-muted">{{ translate('Per user, per day') }}</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Max Total Referrals') }} <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="max_referrals_total"
                                value="{{ old('max_referrals_total', $settings->max_referrals_total) }}"
                                min="1" max="100000" required>
                            <small class="text-muted">{{ translate('Maximum lifetime referrals per user') }}</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Invite Expiry Days') }} <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="invite_expiry_days"
                                value="{{ old('invite_expiry_days', $settings->invite_expiry_days) }}"
                                min="1" max="365" required>
                            <small class="text-muted">{{ translate('Days before invite expires') }}</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Cooldown Minutes') }}</label>
                            <input type="number" class="form-control" name="cooldown_minutes"
                                value="{{ old('cooldown_minutes', $settings->cooldown_minutes) }}"
                                min="0" max="1440">
                            <small class="text-muted">{{ translate('Minutes between generating invites') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fraud Prevention --}}
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-shield-exclamation"></i>
                        {{ translate('Fraud Prevention') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="block_same_device" id="block_same_device"
                                    {{ $settings->block_same_device ? 'checked' : '' }}>
                                <label class="form-check-label" for="block_same_device">
                                    <strong>{{ translate('Block Same Device') }}</strong>
                                    <br><small class="text-muted">{{ translate('Prevent referrals from same device') }}</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="block_same_ip" id="block_same_ip"
                                    {{ $settings->block_same_ip ? 'checked' : '' }}>
                                <label class="form-check-label" for="block_same_ip">
                                    <strong>{{ translate('Block Same IP') }}</strong>
                                    <br><small class="text-muted">{{ translate('Prevent referrals from same IP address') }}</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="require_phone_verified" id="require_phone_verified"
                                    {{ $settings->require_phone_verified ? 'checked' : '' }}>
                                <label class="form-check-label" for="require_phone_verified">
                                    <strong>{{ translate('Require Phone Verification') }}</strong>
                                    <br><small class="text-muted">{{ translate('Only reward if phone is verified') }}</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>{{ translate('Warning:') }}</strong>
                        {{ translate('Disabling fraud prevention may lead to abuse. Only disable for testing purposes.') }}
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="d-flex justify-content-end gap-3">
                <a href="{{ route('admin.referral.index') }}" class="btn btn-secondary">{{ translate('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> {{ translate('Save Settings') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('script')
<script>
    document.getElementById('reward_trigger').addEventListener('change', function() {
        const wrapper = document.getElementById('required_rides_wrapper');
        if (this.value === 'three_rides') {
            wrapper.style.display = 'block';
        } else {
            wrapper.style.display = 'none';
        }
    });
</script>
@endpush

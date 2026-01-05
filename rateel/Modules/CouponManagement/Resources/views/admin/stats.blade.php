@extends('adminmodule::layouts.master')

@section('title', translate('Coupon Statistics'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fs-22 text-capitalize">{{ translate('Statistics for') }}: <code>{{ $coupon->code }}</code></h2>
                <p class="text-muted mb-0">{{ $coupon->name }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.coupon-management.show', $coupon->id) }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> {{ translate('Back to Details') }}
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h6 class="text-white-50">{{ translate('Total Redemptions') }}</h6>
                        <h2 class="mb-0">{{ $stats['total_redemptions'] }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h6 class="text-white-50">{{ translate('Applied') }}</h6>
                        <h2 class="mb-0">{{ $stats['applied_redemptions'] }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h6 class="text-white-50">{{ translate('Unique Users') }}</h6>
                        <h2 class="mb-0">{{ $stats['unique_users'] }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h6 class="opacity-75">{{ translate('Total Discount Given') }}</h6>
                        <h2 class="mb-0">{{ getCurrencyFormat($stats['total_discount']) }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Progress -->
        @if($coupon->global_limit)
        <div class="card mb-4">
            <div class="card-body">
                <h5>{{ translate('Usage Limit Progress') }}</h5>
                <div class="d-flex justify-content-between mb-2">
                    <span>{{ $coupon->global_used_count }} {{ translate('of') }} {{ $coupon->global_limit }} {{ translate('used') }}</span>
                    <span>{{ $stats['remaining_uses'] }} {{ translate('remaining') }}</span>
                </div>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar {{ $coupon->isGlobalLimitReached() ? 'bg-danger' : 'bg-success' }}" 
                         role="progressbar" 
                         style="width: {{ ($coupon->global_used_count / $coupon->global_limit) * 100 }}%">
                        {{ round(($coupon->global_used_count / $coupon->global_limit) * 100, 1) }}%
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Daily Stats Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Daily Redemptions (Last 30 Days)') }}</h5>
            </div>
            <div class="card-body">
                @if($stats['daily_stats']->isEmpty())
                    <div class="text-center py-5">
                        <i class="bi bi-graph-up fs-1 text-muted"></i>
                        <p class="text-muted mt-2">{{ translate('No redemption data yet') }}</p>
                    </div>
                @else
                    <canvas id="dailyStatsChart" height="100"></canvas>
                @endif
            </div>
        </div>

        <!-- Daily Stats Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Daily Breakdown') }}</h5>
            </div>
            <div class="card-body">
                @if($stats['daily_stats']->isEmpty())
                    <div class="text-center py-4">
                        <p class="text-muted">{{ translate('No data available') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>{{ translate('Date') }}</th>
                                    <th>{{ translate('Redemptions') }}</th>
                                    <th>{{ translate('Total Discount') }}</th>
                                    <th>{{ translate('Avg. Discount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['daily_stats'] as $day)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($day->date)->format('M d, Y (l)') }}</td>
                                        <td>{{ $day->count }}</td>
                                        <td>{{ getCurrencyFormat($day->discount) }}</td>
                                        <td>{{ getCurrencyFormat($day->count > 0 ? $day->discount / $day->count : 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <th>{{ translate('Total') }}</th>
                                    <th>{{ $stats['daily_stats']->sum('count') }}</th>
                                    <th>{{ getCurrencyFormat($stats['daily_stats']->sum('discount')) }}</th>
                                    <th>-</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if(!$stats['daily_stats']->isEmpty())
        const dailyData = @json($stats['daily_stats']->reverse());
        
        new Chart(document.getElementById('dailyStatsChart'), {
            type: 'bar',
            data: {
                labels: dailyData.map(d => d.date),
                datasets: [{
                    label: '{{ translate("Redemptions") }}',
                    data: dailyData.map(d => d.count),
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: '{{ translate("Discount Amount") }}',
                    data: dailyData.map(d => parseFloat(d.discount)),
                    type: 'line',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: '{{ translate("Redemptions") }}'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: '{{ translate("Discount Amount") }}'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        @endif
    });
</script>
@endpush

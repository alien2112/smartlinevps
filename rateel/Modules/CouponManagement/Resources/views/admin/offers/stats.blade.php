@extends('adminmodule::layouts.master')

@section('title', translate('Offer Statistics'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fs-22 text-capitalize">{{ translate('Statistics for') }}: {{ $offer->title }}</h2>
            </div>
            <a href="{{ route('admin.offer-management.show', $offer->id) }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back') }}
            </a>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h6 class="text-white-50">{{ translate('Total Usages') }}</h6>
                        <h2 class="mb-0">{{ $stats['usage']['total_usages'] }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h6 class="text-white-50">{{ translate('Applied') }}</h6>
                        <h2 class="mb-0">{{ $stats['usage']['applied'] }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h6 class="text-white-50">{{ translate('Unique Users') }}</h6>
                        <h2 class="mb-0">{{ $stats['usage']['unique_users'] }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h6 class="opacity-75">{{ translate('Total Discount') }}</h6>
                        <h2 class="mb-0">{{ getCurrencyFormat($stats['financials']['total_discount_given']) }}</h2>
                    </div>
                </div>
            </div>
        </div>

        @if($stats['limits']['global_limit'])
        <div class="card mb-4">
            <div class="card-body">
                <h5>{{ translate('Usage Limit Progress') }}</h5>
                <div class="d-flex justify-content-between mb-2">
                    <span>{{ $stats['limits']['total_used'] }} {{ translate('of') }} {{ $stats['limits']['global_limit'] }}</span>
                    <span>{{ $stats['limits']['remaining'] ?? 0 }} {{ translate('remaining') }}</span>
                </div>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar bg-success" style="width: {{ min(100, ($stats['limits']['total_used'] / $stats['limits']['global_limit']) * 100) }}%">
                        {{ round(($stats['limits']['total_used'] / $stats['limits']['global_limit']) * 100, 1) }}%
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ translate('Daily Usage (Last 30 Days)') }}</h5></div>
            <div class="card-body">
                @if(empty($stats['daily_stats']) || count($stats['daily_stats']) === 0)
                    <div class="text-center py-5">
                        <i class="bi bi-graph-up fs-1 text-muted"></i>
                        <p class="text-muted mt-2">{{ translate('No usage data yet') }}</p>
                    </div>
                @else
                    <canvas id="dailyStatsChart" height="100"></canvas>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ translate('Daily Breakdown') }}</h5></div>
            <div class="card-body">
                @if(empty($stats['daily_stats']) || count($stats['daily_stats']) === 0)
                    <div class="text-center py-4"><p class="text-muted">{{ translate('No data') }}</p></div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>{{ translate('Date') }}</th>
                                    <th>{{ translate('Usages') }}</th>
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
    @if(!empty($stats['daily_stats']) && count($stats['daily_stats']) > 0)
    const dailyData = @json(collect($stats['daily_stats'])->reverse()->values());
    
    new Chart(document.getElementById('dailyStatsChart'), {
        type: 'bar',
        data: {
            labels: dailyData.map(d => d.date),
            datasets: [{
                label: '{{ translate("Usages") }}',
                data: dailyData.map(d => d.count),
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
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
            scales: {
                y: { type: 'linear', position: 'left', title: { display: true, text: '{{ translate("Usages") }}' }},
                y1: { type: 'linear', position: 'right', title: { display: true, text: '{{ translate("Discount") }}' }, grid: { drawOnChartArea: false }}
            }
        }
    });
    @endif
});
</script>
@endpush

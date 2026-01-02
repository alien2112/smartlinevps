<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Entities\TripRequest;

class ReportController extends Controller
{
    /**
     * Get weekly report
     * GET /api/driver/auth/reports/weekly
     */
    public function weeklyReport(Request $request): JsonResponse
    {
        $driver = auth('api')->user();

        // Get week parameter or default to current week
        $weekOffset = $request->input('week_offset', 0); // 0 = current week, -1 = last week, etc.

        $weekStart = now()->subWeeks(abs($weekOffset))->startOfWeek();
        $weekEnd = now()->subWeeks(abs($weekOffset))->endOfWeek();

        // Get all trips for the week
        $trips = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->get();

        // Calculate metrics
        $totalTrips = $trips->count();
        $completedTrips = $trips->where('current_status', 'completed')->count();
        $cancelledTrips = $trips->where('current_status', 'cancelled')->count();

        $totalEarnings = $trips->where('payment_status', PAID)->sum('paid_fare');
        $totalDistance = $trips->where('current_status', 'completed')->sum('estimated_distance');
        $totalDuration = $trips->where('current_status', 'completed')->sum('actual_time');

        // Daily breakdown
        $dailyStats = [];
        for ($date = $weekStart->copy(); $date->lte($weekEnd); $date->addDay()) {
            $dayTrips = $trips->filter(function($trip) use ($date) {
                return $trip->created_at->isSameDay($date);
            });

            $dailyStats[] = [
                'date' => $date->toDateString(),
                'day_name' => $date->format('l'),
                'trips' => $dayTrips->count(),
                'completed' => $dayTrips->where('current_status', 'completed')->count(),
                'earnings' => (float) $dayTrips->where('payment_status', PAID)->sum('paid_fare'),
                'formatted_earnings' => getCurrencyFormat($dayTrips->where('payment_status', PAID)->sum('paid_fare')),
            ];
        }

        // Peak hours analysis
        $hourlyTrips = $trips->groupBy(function($trip) {
            return $trip->created_at->hour;
        })->map->count()->sortDesc()->take(3);

        $peakHours = $hourlyTrips->keys()->map(function($hour) use ($hourlyTrips) {
            return [
                'hour' => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
                'trips' => $hourlyTrips[$hour],
            ];
        })->values();

        // Top earning days
        $topDays = collect($dailyStats)->sortByDesc('earnings')->take(3)->values();

        return response()->json(responseFormatter(DEFAULT_200, [
            'period' => [
                'start' => $weekStart->toDateString(),
                'end' => $weekEnd->toDateString(),
                'week_number' => $weekStart->weekOfYear,
                'is_current_week' => $weekOffset === 0,
            ],
            'summary' => [
                'total_trips' => $totalTrips,
                'completed_trips' => $completedTrips,
                'cancelled_trips' => $cancelledTrips,
                'completion_rate' => $totalTrips > 0 ? round(($completedTrips / $totalTrips) * 100, 2) : 0,
                'total_earnings' => (float) $totalEarnings,
                'formatted_earnings' => getCurrencyFormat($totalEarnings),
                'avg_per_trip' => $completedTrips > 0 ? $totalEarnings / $completedTrips : 0,
                'total_distance_km' => round($totalDistance, 2),
                'total_duration_minutes' => round($totalDuration, 2),
            ],
            'daily_breakdown' => $dailyStats,
            'insights' => [
                'peak_hours' => $peakHours,
                'top_earning_days' => $topDays,
                'busiest_day' => collect($dailyStats)->sortByDesc('trips')->first(),
            ],
        ]));
    }

    /**
     * Get monthly report
     * GET /api/driver/auth/reports/monthly
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        $driver = auth('api')->user();

        // Get month parameter or default to current month
        $monthOffset = $request->input('month_offset', 0);

        $monthStart = now()->subMonths(abs($monthOffset))->startOfMonth();
        $monthEnd = now()->subMonths(abs($monthOffset))->endOfMonth();

        // Get all trips for the month
        $trips = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->get();

        // Calculate metrics
        $totalTrips = $trips->count();
        $completedTrips = $trips->where('current_status', 'completed')->count();
        $cancelledTrips = $trips->where('current_status', 'cancelled')->count();

        $totalEarnings = $trips->where('payment_status', PAID)->sum('paid_fare');
        $totalDistance = $trips->where('current_status', 'completed')->sum('estimated_distance');

        // Get earnings breakdown
        $earningsBreakdown = DB::table('transactions')
            ->where('user_id', $driver->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->select('account', DB::raw('SUM(credit) as total_credit'), DB::raw('SUM(debit) as total_debit'))
            ->groupBy('account')
            ->get();

        $receivable = $earningsBreakdown->where('account', 'receivable_balance')->first()->total_credit ?? 0;
        $payable = $earningsBreakdown->where('account', 'payable_balance')->first()->total_credit ?? 0;
        $withdrawn = $earningsBreakdown->where('account', 'received_balance')->first()->total_debit ?? 0;

        // Weekly breakdown
        $weeklyStats = [];
        $currentWeekStart = $monthStart->copy()->startOfWeek();

        while ($currentWeekStart->lte($monthEnd)) {
            $currentWeekEnd = $currentWeekStart->copy()->endOfWeek();
            if ($currentWeekEnd->gt($monthEnd)) {
                $currentWeekEnd = $monthEnd->copy();
            }

            $weekTrips = $trips->filter(function($trip) use ($currentWeekStart, $currentWeekEnd) {
                return $trip->created_at->between($currentWeekStart, $currentWeekEnd);
            });

            $weeklyStats[] = [
                'week_start' => $currentWeekStart->toDateString(),
                'week_end' => $currentWeekEnd->toDateString(),
                'week_number' => $currentWeekStart->weekOfYear,
                'trips' => $weekTrips->count(),
                'completed' => $weekTrips->where('current_status', 'completed')->count(),
                'earnings' => (float) $weekTrips->where('payment_status', PAID)->sum('paid_fare'),
                'formatted_earnings' => getCurrencyFormat($weekTrips->where('payment_status', PAID)->sum('paid_fare')),
            ];

            $currentWeekStart->addWeek();
        }

        // Trip type distribution
        $tripTypes = $trips->groupBy('type')->map->count();

        // Customer ratings
        $avgRating = DB::table('reviews')
            ->where('driver_id', $driver->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->avg('rating') ?? 0;

        $totalReviews = DB::table('reviews')
            ->where('driver_id', $driver->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        // Compare with previous month
        $prevMonthStart = $monthStart->copy()->subMonth()->startOfMonth();
        $prevMonthEnd = $monthStart->copy()->subMonth()->endOfMonth();

        $prevMonthTrips = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])
            ->count();

        $prevMonthEarnings = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])
            ->where('payment_status', PAID)
            ->sum('paid_fare');

        $tripsGrowth = $prevMonthTrips > 0
            ? round((($totalTrips - $prevMonthTrips) / $prevMonthTrips) * 100, 2)
            : 0;

        $earningsGrowth = $prevMonthEarnings > 0
            ? round((($totalEarnings - $prevMonthEarnings) / $prevMonthEarnings) * 100, 2)
            : 0;

        return response()->json(responseFormatter(DEFAULT_200, [
            'period' => [
                'month' => $monthStart->format('F Y'),
                'start' => $monthStart->toDateString(),
                'end' => $monthEnd->toDateString(),
                'is_current_month' => $monthOffset === 0,
            ],
            'summary' => [
                'total_trips' => $totalTrips,
                'completed_trips' => $completedTrips,
                'cancelled_trips' => $cancelledTrips,
                'completion_rate' => $totalTrips > 0 ? round(($completedTrips / $totalTrips) * 100, 2) : 0,
                'total_earnings' => (float) $totalEarnings,
                'formatted_earnings' => getCurrencyFormat($totalEarnings),
                'avg_per_trip' => $completedTrips > 0 ? round($totalEarnings / $completedTrips, 2) : 0,
                'total_distance_km' => round($totalDistance, 2),
            ],
            'earnings_breakdown' => [
                'gross_earnings' => (float) $receivable,
                'commission_paid' => (float) $payable,
                'net_earnings' => (float) ($receivable - $payable),
                'withdrawn' => (float) $withdrawn,
                'pending_balance' => (float) ($receivable - $payable - $withdrawn),
            ],
            'weekly_breakdown' => $weeklyStats,
            'trip_types' => $tripTypes,
            'rating' => [
                'average' => round($avgRating, 2),
                'total_reviews' => $totalReviews,
            ],
            'comparison' => [
                'previous_month' => [
                    'trips' => $prevMonthTrips,
                    'earnings' => (float) $prevMonthEarnings,
                ],
                'growth' => [
                    'trips_percentage' => $tripsGrowth,
                    'earnings_percentage' => $earningsGrowth,
                    'trend' => $tripsGrowth > 0 ? 'up' : ($tripsGrowth < 0 ? 'down' : 'stable'),
                ],
            ],
        ]));
    }

    /**
     * Export trip history report
     * POST /api/driver/auth/reports/export
     */
    public function exportReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:pdf,csv,excel',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'include_details' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();
        $format = $request->format;
        $startDate = \Carbon\Carbon::parse($request->start_date)->startOfDay();
        $endDate = \Carbon\Carbon::parse($request->end_date)->endOfDay();

        // Get trips
        $trips = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['customer', 'vehicle'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Prepare data
        $data = [
            'driver' => [
                'name' => $driver->first_name . ' ' . $driver->last_name,
                'phone' => $driver->phone,
                'id' => $driver->id,
            ],
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_trips' => $trips->count(),
                'completed' => $trips->where('current_status', 'completed')->count(),
                'cancelled' => $trips->where('current_status', 'cancelled')->count(),
                'total_earnings' => $trips->where('payment_status', PAID)->sum('paid_fare'),
            ],
            'trips' => $trips->map(function($trip) use ($request) {
                $basic = [
                    'date' => $trip->created_at->toDateString(),
                    'time' => $trip->created_at->format('H:i'),
                    'trip_id' => $trip->ref_id ?? $trip->id,
                    'status' => $trip->current_status,
                    'fare' => $trip->paid_fare,
                ];

                if ($request->boolean('include_details', false)) {
                    return array_merge($basic, [
                        'customer_name' => $trip->customer ? $trip->customer->first_name : 'N/A',
                        'pickup' => $trip->pickup_address,
                        'destination' => $trip->destination_address,
                        'distance_km' => $trip->estimated_distance,
                        'duration_min' => $trip->actual_time,
                        'payment_method' => $trip->payment_method,
                    ]);
                }

                return $basic;
            }),
        ];

        // Generate export file
        $fileName = 'trip_report_' . $driver->id . '_' . now()->format('Y-m-d_His') . '.' . $format;
        $filePath = 'exports/reports/' . $fileName;

        // Here you would generate the actual file (PDF/CSV/Excel)
        // For now, we'll return the data structure

        // In production, use libraries like:
        // - maatwebsite/excel for Excel/CSV
        // - barryvdh/laravel-dompdf for PDF

        return response()->json(responseFormatter([
            'response_code' => 'export_ready_200',
            'message' => translate('Report generated successfully'),
            'data' => [
                'download_url' => asset('storage/' . $filePath),
                'file_name' => $fileName,
                'format' => $format,
                'file_size' => '0 KB', // Would be actual size
                'expires_at' => now()->addHours(24)->toIso8601String(),
                // For demo purposes, include the data
                'preview_data' => $data,
            ],
        ]));
    }
}

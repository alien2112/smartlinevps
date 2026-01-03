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
     *
     * Uses aggregate queries instead of loading all trips into memory
     * to prevent OOM errors for drivers with many trips.
     */
    public function weeklyReport(Request $request): JsonResponse
    {
        $driver = auth('api')->user();

        // Get week parameter or default to current week
        $weekOffset = abs($request->input('week_offset', 0)); // 0 = current week, -1 = last week, etc.

        $weekStart = now()->subWeeks($weekOffset)->startOfWeek();
        $weekEnd = now()->subWeeks($weekOffset)->endOfWeek();

        // Use aggregate query instead of loading all trips
        $summary = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->selectRaw('
                COUNT(*) as total_trips,
                SUM(CASE WHEN current_status = "completed" THEN 1 ELSE 0 END) as completed_trips,
                SUM(CASE WHEN current_status = "cancelled" THEN 1 ELSE 0 END) as cancelled_trips,
                SUM(CASE WHEN payment_status = ? THEN paid_fare ELSE 0 END) as total_earnings,
                SUM(CASE WHEN current_status = "completed" THEN COALESCE(estimated_distance, 0) ELSE 0 END) as total_distance,
                SUM(CASE WHEN current_status = "completed" THEN COALESCE(actual_time, 0) ELSE 0 END) as total_duration
            ', [PAID])
            ->first();

        $totalTrips = $summary->total_trips ?? 0;
        $completedTrips = $summary->completed_trips ?? 0;
        $cancelledTrips = $summary->cancelled_trips ?? 0;
        $totalEarnings = $summary->total_earnings ?? 0;
        $totalDistance = $summary->total_distance ?? 0;
        $totalDuration = $summary->total_duration ?? 0;

        // Daily breakdown using aggregate query
        $dailyData = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as trips,
                SUM(CASE WHEN current_status = "completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN payment_status = ? THEN paid_fare ELSE 0 END) as earnings
            ', [PAID])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Build daily stats for all days in the week
        $dailyStats = [];
        for ($date = $weekStart->copy(); $date->lte($weekEnd); $date->addDay()) {
            $dateStr = $date->toDateString();
            $dayData = $dailyData->get($dateStr);

            $dailyStats[] = [
                'date' => $dateStr,
                'day_name' => $date->format('l'),
                'trips' => $dayData->trips ?? 0,
                'completed' => $dayData->completed ?? 0,
                'earnings' => (float) ($dayData->earnings ?? 0),
                'formatted_earnings' => getCurrencyFormat($dayData->earnings ?? 0),
            ];
        }

        // Peak hours analysis using aggregate query
        $hourlyData = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as trip_count')
            ->groupBy('hour')
            ->orderByDesc('trip_count')
            ->limit(3)
            ->get();

        $peakHours = $hourlyData->map(function($item) {
            return [
                'hour' => sprintf('%02d:00 - %02d:00', $item->hour, $item->hour + 1),
                'trips' => $item->trip_count,
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
     *
     * Uses aggregate queries instead of loading all trips into memory
     * to prevent OOM errors for drivers with many trips.
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        $driver = auth('api')->user();

        // Get month parameter or default to current month
        $monthOffset = abs($request->input('month_offset', 0));

        $monthStart = now()->subMonths($monthOffset)->startOfMonth();
        $monthEnd = now()->subMonths($monthOffset)->endOfMonth();

        // Use aggregate query instead of loading all trips
        $summary = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('
                COUNT(*) as total_trips,
                SUM(CASE WHEN current_status = "completed" THEN 1 ELSE 0 END) as completed_trips,
                SUM(CASE WHEN current_status = "cancelled" THEN 1 ELSE 0 END) as cancelled_trips,
                SUM(CASE WHEN payment_status = ? THEN paid_fare ELSE 0 END) as total_earnings,
                SUM(CASE WHEN current_status = "completed" THEN COALESCE(estimated_distance, 0) ELSE 0 END) as total_distance
            ', [PAID])
            ->first();

        $totalTrips = $summary->total_trips ?? 0;
        $completedTrips = $summary->completed_trips ?? 0;
        $cancelledTrips = $summary->cancelled_trips ?? 0;
        $totalEarnings = $summary->total_earnings ?? 0;
        $totalDistance = $summary->total_distance ?? 0;

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

        // Weekly breakdown using aggregate query
        $weeklyData = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('
                YEARWEEK(created_at, 1) as year_week,
                MIN(DATE(created_at)) as week_start,
                MAX(DATE(created_at)) as week_end,
                WEEK(created_at, 1) as week_number,
                COUNT(*) as trips,
                SUM(CASE WHEN current_status = "completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN payment_status = ? THEN paid_fare ELSE 0 END) as earnings
            ', [PAID])
            ->groupBy('year_week', 'week_number')
            ->orderBy('year_week')
            ->get();

        $weeklyStats = $weeklyData->map(function($week) {
            return [
                'week_start' => $week->week_start,
                'week_end' => $week->week_end,
                'week_number' => $week->week_number,
                'trips' => $week->trips,
                'completed' => $week->completed,
                'earnings' => (float) $week->earnings,
                'formatted_earnings' => getCurrencyFormat($week->earnings),
            ];
        })->values();

        // Trip type distribution using aggregate query
        $tripTypes = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        // Customer ratings (reviews table uses received_by, not driver_id)
        $avgRating = DB::table('reviews')
            ->where('received_by', $driver->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->avg('rating') ?? 0;

        $totalReviews = DB::table('reviews')
            ->where('received_by', $driver->id)
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
        $includeDetails = $request->boolean('include_details', false);

        // Get trips
        $trips = TripRequest::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['customer', 'vehicle'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Prepare summary data
        $summary = [
            'driver_name' => $driver->first_name . ' ' . $driver->last_name,
            'driver_phone' => $driver->phone,
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'total_trips' => $trips->count(),
            'completed_trips' => $trips->where('current_status', 'completed')->count(),
            'cancelled_trips' => $trips->where('current_status', 'cancelled')->count(),
            'total_earnings' => $trips->where('payment_status', PAID)->sum('paid_fare'),
        ];

        // Prepare trip rows
        $tripRows = $trips->map(function($trip) use ($includeDetails) {
            $row = [
                'Date' => $trip->created_at->toDateString(),
                'Time' => $trip->created_at->format('H:i'),
                'Trip ID' => $trip->ref_id ?? $trip->id,
                'Status' => ucfirst($trip->current_status),
                'Fare' => number_format($trip->paid_fare, 2),
            ];

            if ($includeDetails) {
                $row['Customer'] = $trip->customer ? $trip->customer->first_name : 'N/A';
                $row['Pickup'] = $trip->pickup_address ?? 'N/A';
                $row['Destination'] = $trip->destination_address ?? 'N/A';
                $row['Distance (km)'] = $trip->estimated_distance ?? 0;
                $row['Duration (min)'] = $trip->actual_time ?? 0;
                $row['Payment Method'] = $trip->payment_method ?? 'N/A';
            }

            return $row;
        })->toArray();

        // Ensure export directory exists
        $exportDir = storage_path('app/public/exports/reports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $fileName = 'trip_report_' . $driver->id . '_' . now()->format('Y-m-d_His');

        try {
            switch ($format) {
                case 'csv':
                    $filePath = $this->generateCSV($fileName, $summary, $tripRows, $exportDir);
                    break;
                case 'excel':
                    $filePath = $this->generateExcel($fileName, $summary, $tripRows, $exportDir);
                    break;
                case 'pdf':
                    $filePath = $this->generatePDF($fileName, $summary, $tripRows, $exportDir, $driver);
                    break;
                default:
                    $filePath = $this->generateCSV($fileName, $summary, $tripRows, $exportDir);
            }

            $fileSize = filesize($filePath);
            $fileSizeFormatted = $this->formatFileSize($fileSize);

            return response()->json(responseFormatter([
                'response_code' => 'export_ready_200',
                'message' => translate('Report generated successfully'),
                'data' => [
                    'download_url' => asset('storage/exports/reports/' . basename($filePath)),
                    'file_name' => basename($filePath),
                    'format' => $format,
                    'file_size' => $fileSizeFormatted,
                    'expires_at' => now()->addHours(24)->toIso8601String(),
                    'summary' => $summary,
                ],
            ]));
        } catch (\Exception $e) {
            return response()->json(responseFormatter([
                'response_code' => 'export_failed_500',
                'message' => translate('Failed to generate report: :error', ['error' => $e->getMessage()]),
            ]), 500);
        }
    }

    /**
     * Generate CSV file
     */
    private function generateCSV(string $fileName, array $summary, array $rows, string $exportDir): string
    {
        $filePath = $exportDir . '/' . $fileName . '.csv';
        $handle = fopen($filePath, 'w');

        // Add BOM for UTF-8
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write summary header
        fputcsv($handle, ['Trip Report']);
        fputcsv($handle, ['Driver', $summary['driver_name']]);
        fputcsv($handle, ['Phone', $summary['driver_phone']]);
        fputcsv($handle, ['Period', $summary['period_start'] . ' to ' . $summary['period_end']]);
        fputcsv($handle, ['Total Trips', $summary['total_trips']]);
        fputcsv($handle, ['Completed', $summary['completed_trips']]);
        fputcsv($handle, ['Cancelled', $summary['cancelled_trips']]);
        fputcsv($handle, ['Total Earnings', number_format($summary['total_earnings'], 2)]);
        fputcsv($handle, []); // Empty row

        // Write column headers
        if (!empty($rows)) {
            fputcsv($handle, array_keys($rows[0]));

            // Write data rows
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
        }

        fclose($handle);
        return $filePath;
    }

    /**
     * Generate Excel-compatible XML file (no external library required)
     */
    private function generateExcel(string $fileName, array $summary, array $rows, string $exportDir): string
    {
        $filePath = $exportDir . '/' . $fileName . '.xls';

        $html = '<?xml version="1.0" encoding="UTF-8"?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style>
    table { border-collapse: collapse; }
    th, td { border: 1px solid #000; padding: 5px; }
    th { background-color: #4472C4; color: white; font-weight: bold; }
    .summary-label { font-weight: bold; background-color: #D9E2F3; }
    .summary-value { background-color: #E2EFDA; }
    .header { font-size: 16px; font-weight: bold; background-color: #4472C4; color: white; }
</style>
</head>
<body>
<table>
    <tr><td colspan="6" class="header">Trip Report</td></tr>
    <tr><td class="summary-label">Driver</td><td class="summary-value" colspan="5">' . htmlspecialchars($summary['driver_name']) . '</td></tr>
    <tr><td class="summary-label">Phone</td><td class="summary-value" colspan="5">' . htmlspecialchars($summary['driver_phone']) . '</td></tr>
    <tr><td class="summary-label">Period</td><td class="summary-value" colspan="5">' . $summary['period_start'] . ' to ' . $summary['period_end'] . '</td></tr>
    <tr><td class="summary-label">Total Trips</td><td class="summary-value" colspan="5">' . $summary['total_trips'] . '</td></tr>
    <tr><td class="summary-label">Completed</td><td class="summary-value" colspan="5">' . $summary['completed_trips'] . '</td></tr>
    <tr><td class="summary-label">Cancelled</td><td class="summary-value" colspan="5">' . $summary['cancelled_trips'] . '</td></tr>
    <tr><td class="summary-label">Total Earnings</td><td class="summary-value" colspan="5">' . number_format($summary['total_earnings'], 2) . '</td></tr>
    <tr><td colspan="6"></td></tr>';

        // Add data headers and rows
        if (!empty($rows)) {
            $html .= '<tr>';
            foreach (array_keys($rows[0]) as $header) {
                $html .= '<th>' . htmlspecialchars($header) . '</th>';
            }
            $html .= '</tr>';

            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . htmlspecialchars($cell) . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</table></body></html>';

        file_put_contents($filePath, $html);
        return $filePath;
    }

    /**
     * Generate PDF file (HTML-based, can be printed to PDF by client or use wkhtmltopdf if available)
     */
    private function generatePDF(string $fileName, array $summary, array $rows, string $exportDir, $driver): string
    {
        $filePath = $exportDir . '/' . $fileName . '.html'; // HTML file that can be printed as PDF

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        .header { background: linear-gradient(135deg, #4472C4, #2E5AA8); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 5px 0 0 0; opacity: 0.9; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .summary-card { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; }
        .summary-card .value { font-size: 24px; font-weight: bold; color: #4472C4; }
        .summary-card .label { color: #666; font-size: 12px; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #4472C4; color: white; padding: 12px 8px; text-align: left; font-size: 12px; }
        td { padding: 10px 8px; border-bottom: 1px solid #eee; font-size: 12px; }
        tr:hover { background: #f5f5f5; }
        .status-completed { color: #28a745; font-weight: bold; }
        .status-cancelled { color: #dc3545; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #666; font-size: 11px; }
        @media print { .header { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Trip Report</h1>
        <p>' . htmlspecialchars($summary['driver_name']) . ' | ' . $summary['period_start'] . ' to ' . $summary['period_end'] . '</p>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="value">' . $summary['total_trips'] . '</div>
            <div class="label">Total Trips</div>
        </div>
        <div class="summary-card">
            <div class="value">' . $summary['completed_trips'] . '</div>
            <div class="label">Completed</div>
        </div>
        <div class="summary-card">
            <div class="value">' . $summary['cancelled_trips'] . '</div>
            <div class="label">Cancelled</div>
        </div>
        <div class="summary-card">
            <div class="value">' . number_format($summary['total_earnings'], 2) . '</div>
            <div class="label">Total Earnings</div>
        </div>
    </div>';

        if (!empty($rows)) {
            $html .= '<table><thead><tr>';
            foreach (array_keys($rows[0]) as $header) {
                $html .= '<th>' . htmlspecialchars($header) . '</th>';
            }
            $html .= '</tr></thead><tbody>';

            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $key => $cell) {
                    $class = '';
                    if ($key === 'Status') {
                        $class = strtolower($cell) === 'completed' ? 'status-completed' : 'status-cancelled';
                    }
                    $html .= '<td class="' . $class . '">' . htmlspecialchars($cell) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '
    <div class="footer">
        Generated on ' . now()->format('F j, Y \a\t g:i A') . '<br>
        This is an auto-generated report. For questions, contact support.
    </div>
</body>
</html>';

        file_put_contents($filePath, $html);

        // Try to convert to PDF if wkhtmltopdf is available
        $pdfPath = $exportDir . '/' . $fileName . '.pdf';
        $wkhtmltopdf = '/usr/local/bin/wkhtmltopdf';

        if (file_exists($wkhtmltopdf) || is_executable('/usr/bin/wkhtmltopdf')) {
            $cmd = (file_exists($wkhtmltopdf) ? $wkhtmltopdf : '/usr/bin/wkhtmltopdf');
            exec($cmd . ' --quiet ' . escapeshellarg($filePath) . ' ' . escapeshellarg($pdfPath) . ' 2>&1', $output, $returnCode);

            if ($returnCode === 0 && file_exists($pdfPath)) {
                unlink($filePath); // Remove HTML file
                return $pdfPath;
            }
        }

        // Return HTML file if PDF conversion not available (can be printed to PDF by browser)
        return $filePath;
    }

    /**
     * Format file size
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}

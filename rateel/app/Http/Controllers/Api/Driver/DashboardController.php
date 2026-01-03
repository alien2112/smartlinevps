<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

class DashboardController extends Controller
{
    /**
     * Get dashboard widgets
     * GET /api/driver/auth/dashboard/widgets
     */
    public function widgets(): JsonResponse
    {
        $driver = auth('api')->user();

        // Today's earnings
        $todayEarnings = TripRequest::where('driver_id', $driver->id)
            ->where('payment_status', PAID)
            ->whereDate('created_at', today())
            ->sum('paid_fare');

        // Today's trip count
        $todayTrips = TripRequest::where('driver_id', $driver->id)
            ->whereIn('current_status', ['completed'])
            ->whereDate('created_at', today())
            ->count();

        // Weekly summary
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $weeklyEarnings = TripRequest::where('driver_id', $driver->id)
            ->where('payment_status', PAID)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->sum('paid_fare');

        $weeklyTrips = TripRequest::where('driver_id', $driver->id)
            ->whereIn('current_status', ['completed'])
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->count();

        // Monthly summary
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $monthlyEarnings = TripRequest::where('driver_id', $driver->id)
            ->where('payment_status', PAID)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('paid_fare');

        $monthlyTrips = TripRequest::where('driver_id', $driver->id)
            ->whereIn('current_status', ['completed'])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        // Wallet balance
        $wallet = DB::table('user_accounts')
            ->where('user_id', $driver->id)
            ->first();

        $withdrawableAmount = $wallet
            ? max(0, (float) $wallet->receivable_balance - (float) $wallet->payable_balance)
            : 0;

        // Driver rating
        $rating = $driver->receivedReviews()->avg('rating') ?? 0;
        $totalReviews = $driver->receivedReviews()->count();

        // Active promotions count (promotions are general, not driver-specific unless target_driver_id is set)
        $activePromotions = DB::table('driver_promotions')
            ->where('is_active', true)
            ->where(function($q) use ($driver) {
                $q->whereNull('target_driver_id')
                  ->orWhere('target_driver_id', $driver->id);
            })
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->count();

        // Upcoming reminders
        $reminders = [];

        // Check vehicle insurance expiry (check all vehicles, but prioritize primary)
        $primaryVehicle = $driver->primaryVehicle;
        if ($primaryVehicle && $primaryVehicle->insurance_expiry_date) {
            $daysUntilExpiry = now()->diffInDays($primaryVehicle->insurance_expiry_date, false);
            if ($daysUntilExpiry >= 0 && $daysUntilExpiry <= 30) {
                $reminders[] = [
                    'type' => 'insurance_expiry',
                    'title' => translate('Insurance Expiring Soon'),
                    'message' => translate('Your vehicle insurance expires in :days days', ['days' => $daysUntilExpiry]),
                    'days_remaining' => $daysUntilExpiry,
                    'priority' => $daysUntilExpiry <= 7 ? 'high' : 'normal',
                ];
            }
        }

        // Check document expiry
        $expiringDocs = DB::table('driver_documents')
            ->where('driver_id', $driver->id)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays(30))
            ->get();

        foreach ($expiringDocs as $doc) {
            $daysUntilExpiry = now()->diffInDays($doc->expiry_date, false);
            $reminders[] = [
                'type' => 'document_expiry',
                'title' => translate('Document Expiring Soon'),
                'message' => translate(':type expires in :days days', [
                    'type' => ucfirst($doc->type),
                    'days' => $daysUntilExpiry
                ]),
                'days_remaining' => $daysUntilExpiry,
                'priority' => $daysUntilExpiry <= 7 ? 'high' : 'normal',
                'document_type' => $doc->type,
            ];
        }

        // Unread notifications
        $unreadNotifications = DriverNotification::where('driver_id', $driver->id)
            ->unread()
            ->notExpired()
            ->count();

        return response()->json(responseFormatter(DEFAULT_200, [
            'today' => [
                'earnings' => (float) $todayEarnings,
                'formatted_earnings' => getCurrencyFormat($todayEarnings),
                'trips' => $todayTrips,
                'avg_per_trip' => $todayTrips > 0 ? $todayEarnings / $todayTrips : 0,
            ],
            'weekly' => [
                'earnings' => (float) $weeklyEarnings,
                'formatted_earnings' => getCurrencyFormat($weeklyEarnings),
                'trips' => $weeklyTrips,
                'avg_per_trip' => $weeklyTrips > 0 ? $weeklyEarnings / $weeklyTrips : 0,
            ],
            'monthly' => [
                'earnings' => (float) $monthlyEarnings,
                'formatted_earnings' => getCurrencyFormat($monthlyEarnings),
                'trips' => $monthlyTrips,
                'avg_per_trip' => $monthlyTrips > 0 ? $monthlyEarnings / $monthlyTrips : 0,
            ],
            'wallet' => [
                'withdrawable_amount' => $withdrawableAmount,
                'formatted_withdrawable' => getCurrencyFormat($withdrawableAmount),
                'receivable' => $wallet ? (float) $wallet->receivable_balance : 0,
                'payable' => $wallet ? (float) $wallet->payable_balance : 0,
            ],
            'rating' => [
                'average' => round($rating, 2),
                'total_reviews' => $totalReviews,
                'stars' => round($rating),
            ],
            'notifications' => [
                'unread_count' => $unreadNotifications,
            ],
            'promotions' => [
                'active_count' => $activePromotions,
            ],
            'reminders' => $reminders,
            'status' => [
                'is_online' => $driver->driverDetails?->is_online ?? false,
                'availability' => $driver->driverDetails?->availability_status ?? 'unavailable',
            ],
        ]));
    }

    /**
     * Get recent activity feed
     * GET /api/driver/auth/dashboard/recent-activity
     */
    public function recentActivity(): JsonResponse
    {
        $driver = auth('api')->user();

        $activities = [];

        // Recent trips (last 5)
        $recentTrips = TripRequest::where('driver_id', $driver->id)
            ->whereIn('current_status', ['completed', 'cancelled'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentTrips as $trip) {
            $activities[] = [
                'id' => $trip->id,
                'type' => 'trip',
                'title' => $trip->current_status === 'completed'
                    ? translate('Trip Completed')
                    : translate('Trip Cancelled'),
                'description' => translate('Fare: :amount', ['amount' => getCurrencyFormat($trip->paid_fare)]),
                'icon' => $trip->current_status === 'completed' ? 'check_circle' : 'cancel',
                'timestamp' => $trip->created_at->toIso8601String(),
                'time_ago' => $trip->created_at->diffForHumans(),
                'data' => [
                    'trip_id' => $trip->id,
                    'status' => $trip->current_status,
                    'fare' => $trip->paid_fare,
                ],
            ];
        }

        // Recent earnings/withdrawals
        $recentTransactions = DB::table('transactions')
            ->where('user_id', $driver->id)
            ->whereIn('account', ['receivable_balance', 'received_balance'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentTransactions as $txn) {
            if ($txn->credit > 0) {
                $activities[] = [
                    'id' => $txn->id,
                    'type' => 'earning',
                    'title' => translate('Earnings Received'),
                    'description' => translate('Amount: :amount', ['amount' => getCurrencyFormat($txn->credit)]),
                    'icon' => 'payments',
                    'timestamp' => $txn->created_at,
                    'time_ago' => \Carbon\Carbon::parse($txn->created_at)->diffForHumans(),
                    'data' => [
                        'amount' => $txn->credit,
                        'account' => $txn->account,
                    ],
                ];
            }
        }

        // Sort by timestamp
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        // Limit to 10 most recent
        $activities = array_slice($activities, 0, 10);

        return response()->json(responseFormatter(DEFAULT_200, [
            'activities' => $activities,
            'count' => count($activities),
        ]));
    }

    /**
     * Get promotional banners
     * GET /api/driver/auth/dashboard/promotional-banners
     */
    public function promotionalBanners(): JsonResponse
    {
        $driver = auth('api')->user();

        // Get active promotions for this driver
        $promotions = DB::table('driver_promotions')
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->where(function($query) {
                $query->whereNull('starts_at')
                      ->orWhere('starts_at', '<=', now());
            })
            ->where(function($query) use ($driver) {
                $query->whereNull('target_driver_id')
                      ->orWhere('target_driver_id', $driver->id);
            })
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $banners = $promotions->map(function($promo) {
            return [
                'id' => $promo->id,
                'title' => $promo->title,
                'description' => $promo->description,
                'image_url' => $promo->image_url ? asset('storage/' . $promo->image_url) : null,
                'action_type' => $promo->action_type, // link, deep_link, claim
                'action_url' => $promo->action_url,
                'expires_at' => $promo->expires_at,
                'priority' => $promo->priority,
            ];
        });

        return response()->json(responseFormatter(DEFAULT_200, [
            'banners' => $banners,
            'count' => $banners->count(),
        ]));
    }
}

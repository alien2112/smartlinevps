<?php

namespace Modules\UserManagement\Http\Controllers\Web\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\UserManagement\Entities\ReferralFraudLog;
use Modules\UserManagement\Entities\ReferralInvite;
use Modules\UserManagement\Entities\ReferralReward;
use Modules\UserManagement\Entities\ReferralSetting;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Service\ReferralService;

class ReferralController extends Controller
{
    protected ReferralService $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    /**
     * Referral Dashboard - Main Overview
     */
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $analytics = $this->referralService->getAnalytics($startDate, $endDate);
        $settings = ReferralSetting::getSettings();

        return view('usermanagement::admin.referral.index', compact('analytics', 'settings', 'startDate', 'endDate'));
    }

    /**
     * Referral Settings Page
     */
    public function settings()
    {
        $settings = ReferralSetting::getSettings();
        return view('usermanagement::admin.referral.settings', compact('settings'));
    }

    /**
     * Update Referral Settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'referrer_points' => 'required|integer|min:0|max:10000',
            'referee_points' => 'required|integer|min:0|max:10000',
            'reward_trigger' => 'required|in:signup,first_ride,three_rides,deposit',
            'min_ride_fare' => 'required|numeric|min:0',
            'required_rides' => 'required|integer|min:1|max:100',
            'max_referrals_per_day' => 'required|integer|min:1|max:1000',
            'max_referrals_total' => 'required|integer|min:1|max:100000',
            'invite_expiry_days' => 'required|integer|min:1|max:365',
            'cooldown_minutes' => 'required|integer|min:0|max:1440',
        ]);

        $settings = ReferralSetting::first();

        if (!$settings) {
            $settings = new ReferralSetting();
        }

        $settings->fill([
            'referrer_points' => $request->referrer_points,
            'referee_points' => $request->referee_points,
            'reward_trigger' => $request->reward_trigger,
            'min_ride_fare' => $request->min_ride_fare,
            'required_rides' => $request->required_rides,
            'max_referrals_per_day' => $request->max_referrals_per_day,
            'max_referrals_total' => $request->max_referrals_total,
            'invite_expiry_days' => $request->invite_expiry_days,
            'cooldown_minutes' => $request->cooldown_minutes,
            'block_same_device' => $request->has('block_same_device'),
            'block_same_ip' => $request->has('block_same_ip'),
            'require_phone_verified' => $request->has('require_phone_verified'),
            'is_active' => $request->has('is_active'),
            'show_leaderboard' => $request->has('show_leaderboard'),
        ]);

        $settings->save();

        flash()->success(translate('Referral settings updated successfully'));
        return back();
    }

    /**
     * All Referrals List
     */
    public function referrals(Request $request)
    {
        $query = ReferralInvite::with(['referrer:id,first_name,last_name,phone,ref_code', 'referee:id,first_name,last_name,phone']);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('channel')) {
            $query->where('invite_channel', $request->channel);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invite_code', 'like', "%{$search}%")
                    ->orWhereHas('referrer', function ($rq) use ($search) {
                        $rq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('referee', function ($rq) use ($search) {
                        $rq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date . ' 23:59:59']);
        }

        $referrals = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('usermanagement::admin.referral.referrals', compact('referrals'));
    }

    /**
     * Rewards List
     */
    public function rewards(Request $request)
    {
        $query = ReferralReward::with([
            'referrer:id,first_name,last_name,phone',
            'referee:id,first_name,last_name,phone',
            'invite',
        ]);

        // Filters
        if ($request->filled('status')) {
            $query->where('referrer_status', $request->status);
        }

        if ($request->filled('trigger')) {
            $query->where('trigger_type', $request->trigger);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('referrer', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $rewards = $query->orderBy('created_at', 'desc')->paginate(20);

        $totalPoints = ReferralReward::where('referrer_status', 'paid')
            ->sum(DB::raw('referrer_points + referee_points'));

        return view('usermanagement::admin.referral.rewards', compact('rewards', 'totalPoints'));
    }

    /**
     * Top Referrers / Leaderboard
     */
    public function leaderboard(Request $request)
    {
        $period = $request->input('period', 'month');

        $start = match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->subYears(10),
        };

        $topReferrers = User::select('users.*')
            ->selectRaw('COUNT(ri.id) as total_referrals')
            ->selectRaw('SUM(CASE WHEN ri.status IN ("converted", "rewarded") THEN 1 ELSE 0 END) as successful_referrals_period')
            ->selectRaw('COALESCE(SUM(rr.referrer_points), 0) as total_points_earned')
            ->leftJoin('referral_invites as ri', function ($join) use ($start) {
                $join->on('users.id', '=', 'ri.referrer_id')
                    ->where('ri.created_at', '>=', $start);
            })
            ->leftJoin('referral_rewards as rr', function ($join) use ($start) {
                $join->on('users.id', '=', 'rr.referrer_id')
                    ->where('rr.referrer_status', '=', 'paid')
                    ->where('rr.created_at', '>=', $start);
            })
            ->groupBy('users.id')
            ->having('total_referrals', '>', 0)
            ->orderBy('successful_referrals_period', 'desc')
            ->paginate(50);

        return view('usermanagement::admin.referral.leaderboard', compact('topReferrers', 'period'));
    }

    /**
     * Fraud Logs
     */
    public function fraudLogs(Request $request)
    {
        $query = ReferralFraudLog::with(['user:id,first_name,last_name,phone', 'invite']);

        if ($request->filled('type')) {
            $query->where('fraud_type', $request->type);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date . ' 23:59:59']);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        $fraudStats = ReferralFraudLog::selectRaw('fraud_type, COUNT(*) as count')
            ->groupBy('fraud_type')
            ->pluck('count', 'fraud_type');

        return view('usermanagement::admin.referral.fraud-logs', compact('logs', 'fraudStats'));
    }

    /**
     * View single referral detail
     */
    public function show(string $id)
    {
        $invite = ReferralInvite::with(['referrer', 'referee', 'reward'])->findOrFail($id);

        $fraudLogs = ReferralFraudLog::where('referral_invite_id', $id)
            ->orWhere('user_id', $invite->referee_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('usermanagement::admin.referral.show', compact('invite', 'fraudLogs'));
    }

    /**
     * Block a user from referral program
     */
    public function blockUser(Request $request, string $userId)
    {
        $user = User::findOrFail($userId);

        // Cancel all pending rewards
        ReferralReward::where('referrer_id', $userId)
            ->whereIn('referrer_status', ['pending', 'eligible'])
            ->update(['referrer_status' => 'fraud', 'notes' => 'Blocked by admin: ' . $request->input('reason')]);

        // Mark invites as fraud
        ReferralInvite::where('referrer_id', $userId)
            ->whereIn('status', ['sent', 'opened', 'installed', 'signed_up', 'converted'])
            ->update(['status' => 'fraud_blocked', 'fraud_reason' => $request->input('reason')]);

        // Log the action
        $this->referralService->logFraud(null, $userId, 'blocked_user', [
            'blocked_by' => auth()->id(),
            'reason' => $request->input('reason'),
        ]);

        flash()->success(translate('User blocked from referral program'));
        return back();
    }

    /**
     * Export referrals to CSV
     */
    public function export(Request $request)
    {
        $query = ReferralInvite::with(['referrer:id,first_name,last_name,phone', 'referee:id,first_name,last_name,phone']);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date . ' 23:59:59']);
        }

        $referrals = $query->orderBy('created_at', 'desc')->get();

        $filename = 'referrals_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($referrals) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'ID',
                'Referrer Name',
                'Referrer Phone',
                'Referee Name',
                'Referee Phone',
                'Status',
                'Channel',
                'Sent At',
                'Signup At',
                'First Ride At',
                'Reward At',
            ]);

            foreach ($referrals as $r) {
                fputcsv($file, [
                    $r->id,
                    $r->referrer?->first_name . ' ' . $r->referrer?->last_name,
                    $r->referrer?->phone,
                    $r->referee?->first_name . ' ' . $r->referee?->last_name,
                    $r->referee?->phone,
                    $r->status,
                    $r->invite_channel,
                    $r->sent_at,
                    $r->signup_at,
                    $r->first_ride_at,
                    $r->reward_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

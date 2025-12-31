<?php

namespace Modules\UserManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\ReferralInvite;
use Modules\UserManagement\Entities\ReferralReward;
use Modules\UserManagement\Entities\ReferralSetting;
use Modules\UserManagement\Service\ReferralService;

class ReferralController extends Controller
{
    protected ReferralService $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    /**
     * Get user's referral code and shareable link
     */
    public function getMyCode(): JsonResponse
    {
        $user = auth('api')->user();
        $settings = ReferralSetting::getSettings();

        // Generate ref_code if not exists
        if (empty($user->ref_code)) {
            $user->ref_code = $this->referralService->generateUniqueRefCode();
            $user->save();
        }

        $baseUrl = config('app.url');

        return response()->json(responseFormatter(DEFAULT_200, [
            'ref_code' => $user->ref_code,
            'shareable_link' => "{$baseUrl}/invite/{$user->ref_code}",
            'qr_data' => "{$baseUrl}/invite/{$user->ref_code}",
            'is_active' => $settings->is_active,
            'referrer_points' => $settings->referrer_points,
            'referee_points' => $settings->referee_points,
            'reward_trigger' => $settings->reward_trigger,
            'message' => $this->getRewardMessage($settings),
        ]));
    }

    /**
     * Generate a tracked invite
     */
    public function generateInvite(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'nullable|in:link,code,qr,sms,whatsapp,copy',
            'platform' => 'nullable|in:ios,android,web',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, errorProcessor($validator)), 400);
        }

        $user = auth('api')->user();
        $channel = $request->input('channel', 'link');
        $platform = $request->input('platform');

        $invite = $this->referralService->generateInvite($user, $channel, $platform);

        if (!$invite) {
            return response()->json(responseFormatter(constant: DEFAULT_400, content: [
                'message' => 'Unable to generate invite. Please try again later.',
            ]), 400);
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'invite_token' => $invite->invite_token,
            'invite_code' => $invite->invite_code,
            'shareable_link' => $invite->shareable_link,
            'channel' => $invite->invite_channel,
        ]));
    }

    /**
     * Get user's referral stats
     */
    public function getStats(): JsonResponse
    {
        $user = auth('api')->user();
        $stats = $this->referralService->getUserStats($user);

        return response()->json(responseFormatter(DEFAULT_200, $stats));
    }

    /**
     * Get referral history
     */
    public function getHistory(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 1);

        $invites = ReferralInvite::where('referrer_id', $user->id)
            ->with(['referee:id,first_name,last_name,profile_image'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $offset);

        $data = $invites->map(function ($invite) {
            return [
                'id' => $invite->id,
                'status' => $invite->status,
                'channel' => $invite->invite_channel,
                'sent_at' => $invite->sent_at?->toISOString(),
                'signup_at' => $invite->signup_at?->toISOString(),
                'first_ride_at' => $invite->first_ride_at?->toISOString(),
                'reward_at' => $invite->reward_at?->toISOString(),
                'referee' => $invite->referee ? [
                    'id' => $invite->referee->id,
                    'name' => $invite->referee->first_name,
                    'image' => $invite->referee->profile_image,
                ] : null,
                'is_converted' => in_array($invite->status, [
                    ReferralInvite::STATUS_CONVERTED,
                    ReferralInvite::STATUS_REWARDED,
                ]),
            ];
        });

        return response()->json(responseFormatter(DEFAULT_200, [
            'invites' => $data,
            'total' => $invites->total(),
        ], limit: $limit, offset: $offset));
    }

    /**
     * Get rewards history
     */
    public function getRewards(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 1);

        $rewards = ReferralReward::where('referrer_id', $user->id)
            ->with(['referee:id,first_name,last_name,profile_image'])
            ->where('referrer_status', ReferralReward::STATUS_PAID)
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $offset);

        $data = $rewards->map(function ($reward) {
            return [
                'id' => $reward->id,
                'points' => $reward->referrer_points,
                'trigger_type' => $reward->trigger_type,
                'paid_at' => $reward->referrer_paid_at?->toISOString(),
                'referee' => $reward->referee ? [
                    'id' => $reward->referee->id,
                    'name' => $reward->referee->first_name,
                    'image' => $reward->referee->profile_image,
                ] : null,
            ];
        });

        return response()->json(responseFormatter(DEFAULT_200, [
            'rewards' => $data,
            'total' => $rewards->total(),
            'total_points' => ReferralReward::where('referrer_id', $user->id)
                ->where('referrer_status', ReferralReward::STATUS_PAID)
                ->sum('referrer_points'),
        ], limit: $limit, offset: $offset));
    }

    /**
     * Validate a referral code (before signup)
     */
    public function validateCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, errorProcessor($validator)), 400);
        }

        $result = $this->referralService->validateCode($request->input('code'));

        if (!$result['valid']) {
            return response()->json(responseFormatter(constant: DEFAULT_400, content: $result), 400);
        }

        return response()->json(responseFormatter(DEFAULT_200, $result));
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard(Request $request): JsonResponse
    {
        $settings = ReferralSetting::getSettings();

        if (!$settings->show_leaderboard) {
            return response()->json(responseFormatter(DEFAULT_400, [
                'message' => 'Leaderboard is not available',
            ]), 400);
        }

        $period = $request->input('period', 'month'); // week, month, all
        $limit = $request->input('limit', 10);

        $start = match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->subYears(10),
        };

        $topReferrers = $this->referralService->getTopReferrers($start, now(), $limit);

        // Get current user's rank
        $user = auth('api')->user();
        $userRank = null;

        if ($user) {
            $userConversions = ReferralInvite::where('referrer_id', $user->id)
                ->successful()
                ->where('created_at', '>=', $start)
                ->count();

            if ($userConversions > 0) {
                $usersAbove = \DB::table('referral_invites')
                    ->select('referrer_id')
                    ->whereIn('status', ['converted', 'rewarded'])
                    ->where('created_at', '>=', $start)
                    ->groupBy('referrer_id')
                    ->havingRaw('COUNT(*) > ?', [$userConversions])
                    ->count();

                $userRank = $usersAbove + 1;
            }
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'leaderboard' => $topReferrers,
            'my_rank' => $userRank,
            'my_conversions' => $user ? ReferralInvite::where('referrer_id', $user->id)
                ->successful()
                ->where('created_at', '>=', $start)
                ->count() : 0,
            'period' => $period,
        ]));
    }

    /**
     * Get reward message based on settings
     */
    protected function getRewardMessage(ReferralSetting $settings): string
    {
        $referrerPoints = $settings->referrer_points;
        $refereePoints = $settings->referee_points;

        return match ($settings->reward_trigger) {
            'signup' => "Invite friends and earn {$referrerPoints} points when they sign up! They get {$refereePoints} points too.",
            'first_ride' => "Invite friends and earn {$referrerPoints} points when they complete their first ride! They get {$refereePoints} points too.",
            'three_rides' => "Invite friends and earn {$referrerPoints} points when they complete {$settings->required_rides} rides! They get {$refereePoints} points too.",
            'deposit' => "Invite friends and earn {$referrerPoints} points when they make their first deposit! They get {$refereePoints} points too.",
            default => "Invite friends and earn {$referrerPoints} points!",
        };
    }
}

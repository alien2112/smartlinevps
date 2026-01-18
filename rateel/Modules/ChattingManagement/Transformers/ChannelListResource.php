<?php

namespace Modules\ChattingManagement\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Updated: 2026-01-14 - Fixed N+1 query pattern in unread conversation counts
 */
class ChannelListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->channelable_id,
            'updated_at' => $this->updated_at,
            'channel_users' => ChannelUserResource::collection($this->whenLoaded('channel_users')),
            'last_channel_conversations' => $this->whenLoaded('last_channel_conversations'),
            // Updated 2026-01-14: Optimized to avoid N+1 queries by using pre-loaded data efficiently
            'unread_customer_channel_conversations' => $this->getUnreadCountByUserType(CUSTOMER),
            'unread_driver_channel_conversations' => $this->getUnreadCountByUserType(DRIVER),
        ];
    }

    /**
     * Get unread conversation count for a specific user type
     * Updated: 2026-01-14 - Optimized to prevent N+1 queries
     *
     * @param string $userType CUSTOMER or DRIVER constant
     * @return int
     */
    private function getUnreadCountByUserType(string $userType): int
    {
        // Return 0 if relationships not loaded
        if (!$this->relationLoaded('channel_conversations') || !$this->relationLoaded('channel_users')) {
            return 0;
        }

        // Find the user ID for the given user type from already-loaded channel_users
        // Use first() instead of foreach to avoid unnecessary iterations
        $channelUser = $this->channel_users->first(function ($cu) use ($userType) {
            // Check if user relation is loaded to avoid N+1
            return $cu->relationLoaded('user') && $cu->user->user_type === $userType;
        });

        if (!$channelUser) {
            return 0;
        }

        // Count unread from already-loaded conversations collection
        return $this->channel_conversations
            ->where('user_id', $channelUser->user->id)
            ->where('is_read', 0)
            ->count();

        /* ============================================================
         * OLD CODE - Commented 2026-01-14
         * This code caused N+1 queries by iterating and accessing
         * lazy-loaded user relationship in foreach loop
         * ============================================================
         *
         * foreach ($this->channel_users as $channel_user) {
         *     if ($channel_user->user->user_type == $userType){
         *         $userId = $channel_user->user->id;
         *         return $this->channel_conversations->where('user_id',$userId)->where('is_read', 0)->count();
         *     }
         * }
         */
    }
}

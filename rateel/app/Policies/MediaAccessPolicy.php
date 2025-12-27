<?php

namespace App\Policies;

use Modules\UserManagement\Entities\User;

/**
 * Authorization policy for media access.
 * 
 * Determines if a user can access a specific media object based on
 * the object key path and user's role/ownership.
 */
class MediaAccessPolicy
{
    /**
     * Check if user can access the given object key.
     *
     * Rules:
     * - Driver can access: driver/{their_id}/**
     * - Customer can access: customer/{their_id}/**
     * - Admin/Employee can access: all driver/**, customer/**, vehicle/**
     * - Vehicle images are accessible by their assigned driver
     *
     * @param User $user The authenticated user
     * @param string $objectKey The media object key
     * @return bool
     */
    public function canAccess(User $user, string $objectKey): bool
    {
        // Validate object key format
        if (empty($objectKey) || !$this->isValidObjectKey($objectKey)) {
            return false;
        }

        // Admin/Employee can access everything
        if ($this->isAdminOrEmployee($user)) {
            return true;
        }

        // Extract category and owner from object key
        $category = $this->extractCategoryFromPath($objectKey);
        $ownerId = $this->extractOwnerIdFromPath($objectKey);

        if ($category === null || $ownerId === null) {
            return false;
        }

        // Check ownership based on category
        return match ($category) {
            'driver' => $this->canAccessDriverMedia($user, $ownerId),
            'customer' => $this->canAccessCustomerMedia($user, $ownerId),
            'vehicle' => $this->canAccessVehicleMedia($user, $ownerId),
            default => false,
        };
    }

    /**
     * Check if user can access driver media.
     */
    private function canAccessDriverMedia(User $user, string $ownerId): bool
    {
        // Must be a driver
        if ($user->user_type !== 'driver') {
            return false;
        }

        // Must be their own media
        return $user->id === $ownerId;
    }

    /**
     * Check if user can access customer media.
     */
    private function canAccessCustomerMedia(User $user, string $ownerId): bool
    {
        // Must be a customer
        if ($user->user_type !== 'customer') {
            return false;
        }

        // Must be their own media
        return $user->id === $ownerId;
    }

    /**
     * Check if user can access vehicle media.
     */
    private function canAccessVehicleMedia(User $user, string $ownerId): bool
    {
        // Drivers can access their vehicle's media
        if ($user->user_type === 'driver') {
            // Check if this driver owns this vehicle
            $vehicle = $user->vehicle;
            if ($vehicle && $vehicle->id === $ownerId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is admin or employee.
     */
    private function isAdminOrEmployee(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'super-admin', 'admin-employee'], true);
    }

    /**
     * Extract category from object key path.
     * 
     * Object key format: {category}/{owner_id}/{sub_category}/{filename}
     */
    private function extractCategoryFromPath(string $objectKey): ?string
    {
        $parts = explode('/', $objectKey);
        return $parts[0] ?? null;
    }

    /**
     * Extract owner ID from object key path.
     * 
     * Object key format: {category}/{owner_id}/{sub_category}/{filename}
     */
    private function extractOwnerIdFromPath(string $objectKey): ?string
    {
        $parts = explode('/', $objectKey);
        return $parts[1] ?? null;
    }

    /**
     * Validate object key format and security.
     */
    private function isValidObjectKey(string $objectKey): bool
    {
        // Check for path traversal
        if (str_contains($objectKey, '..')) {
            return false;
        }

        // Check for backslashes
        if (str_contains($objectKey, '\\')) {
            return false;
        }

        // Check for null bytes
        if (str_contains($objectKey, "\0")) {
            return false;
        }

        // Must have at least 4 parts: category/owner_id/sub_category/filename
        $parts = explode('/', $objectKey);
        if (count($parts) < 4) {
            return false;
        }

        return true;
    }

    /**
     * Get the owner ID for a given object key.
     */
    public function getOwnerId(string $objectKey): ?string
    {
        return $this->extractOwnerIdFromPath($objectKey);
    }

    /**
     * Get the category for a given object key.
     */
    public function getCategory(string $objectKey): ?string
    {
        return $this->extractCategoryFromPath($objectKey);
    }
}

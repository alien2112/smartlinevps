<?php

namespace App\Enums;

/**
 * Driver Onboarding State Machine
 *
 * Defines all valid states and transitions for the driver onboarding process.
 * The state machine enforces a strict order of steps and prevents invalid transitions.
 */
enum DriverOnboardingState: string
{
    case OTP_PENDING = 'otp_pending';
    case OTP_VERIFIED = 'otp_verified';
    case PASSWORD_SET = 'password_set';
    case PROFILE_COMPLETE = 'profile_complete';
    case VEHICLE_SELECTED = 'vehicle_selected';
    case DOCUMENTS_PENDING = 'documents_pending';
    case KYC_VERIFICATION = 'kyc_verification';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUSPENDED = 'suspended';

    /**
     * Get the next step action name for the client
     */
    public function nextStep(): string
    {
        return match ($this) {
            self::OTP_PENDING => 'verify_otp',
            self::OTP_VERIFIED => 'set_password',
            self::PASSWORD_SET => 'submit_profile',
            self::PROFILE_COMPLETE => 'select_vehicle',
            self::VEHICLE_SELECTED => 'upload_documents',
            self::DOCUMENTS_PENDING => 'submit_for_kyc',
            self::KYC_VERIFICATION => 'wait_for_kyc',
            self::PENDING_APPROVAL => 'wait_for_approval',
            self::APPROVED => 'approved',
            self::REJECTED => 'fix_issues',
            self::SUSPENDED => 'contact_support',
        };
    }

    /**
     * Get human-readable label for the state
     */
    public function label(): string
    {
        return match ($this) {
            self::OTP_PENDING => 'Phone Verification Pending',
            self::OTP_VERIFIED => 'Phone Verified',
            self::PASSWORD_SET => 'Password Set',
            self::PROFILE_COMPLETE => 'Profile Complete',
            self::VEHICLE_SELECTED => 'Vehicle Selected',
            self::DOCUMENTS_PENDING => 'Documents Uploaded',
            self::KYC_VERIFICATION => 'KYC Verification',
            self::PENDING_APPROVAL => 'Pending Admin Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Application Rejected',
            self::SUSPENDED => 'Account Suspended',
        };
    }

    /**
     * Check if transition to a new state is allowed
     */
    public function canTransitionTo(self $newState): bool
    {
        $allowedTransitions = $this->allowedTransitions();
        return in_array($newState, $allowedTransitions);
    }

    /**
     * Get allowed transitions from current state
     *
     * @return array<DriverOnboardingState>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OTP_PENDING => [self::OTP_VERIFIED],
            self::OTP_VERIFIED => [self::PASSWORD_SET],
            self::PASSWORD_SET => [self::PROFILE_COMPLETE],
            self::PROFILE_COMPLETE => [self::VEHICLE_SELECTED],
            self::VEHICLE_SELECTED => [self::DOCUMENTS_PENDING],
            self::DOCUMENTS_PENDING => [self::KYC_VERIFICATION, self::VEHICLE_SELECTED], // Can go back if doc rejected
            self::KYC_VERIFICATION => [self::PENDING_APPROVAL, self::DOCUMENTS_PENDING], // Can go back if KYC fails
            self::PENDING_APPROVAL => [self::APPROVED, self::REJECTED, self::KYC_VERIFICATION],
            self::APPROVED => [self::SUSPENDED],
            self::REJECTED => [self::OTP_PENDING], // Can restart the process
            self::SUSPENDED => [self::APPROVED], // Admin can unsuspend
        };
    }

    /**
     * Check if this state allows the driver to operate (accept trips)
     */
    public function canOperate(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Check if this state is a terminal state (end of onboarding)
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED, self::SUSPENDED]);
    }

    /**
     * Check if this state requires admin action
     */
    public function requiresAdminAction(): bool
    {
        return in_array($this, [self::KYC_VERIFICATION, self::PENDING_APPROVAL, self::REJECTED, self::SUSPENDED]);
    }

    /**
     * Check if onboarding is complete (regardless of approval)
     */
    public function isOnboardingComplete(): bool
    {
        return in_array($this, [
            self::KYC_VERIFICATION,
            self::PENDING_APPROVAL,
            self::APPROVED,
            self::REJECTED,
            self::SUSPENDED
        ]);
    }

    /**
     * Get the required step for this state
     */
    public function requiredStep(): ?string
    {
        return match ($this) {
            self::OTP_PENDING => 'verify-otp',
            self::OTP_VERIFIED => 'password',
            self::PASSWORD_SET => 'profile',
            self::PROFILE_COMPLETE => 'vehicle',
            self::VEHICLE_SELECTED => 'documents',
            self::DOCUMENTS_PENDING => 'submit',
            self::KYC_VERIFICATION => 'kyc',
            default => null,
        };
    }

    /**
     * Get the progress percentage
     */
    public function progressPercentage(): int
    {
        return match ($this) {
            self::OTP_PENDING => 0,
            self::OTP_VERIFIED => 15,
            self::PASSWORD_SET => 30,
            self::PROFILE_COMPLETE => 50,
            self::VEHICLE_SELECTED => 70,
            self::DOCUMENTS_PENDING => 80,
            self::KYC_VERIFICATION => 90,
            self::PENDING_APPROVAL => 95,
            self::APPROVED => 100,
            self::REJECTED => 95,
            self::SUSPENDED => 100,
        };
    }

    /**
     * Create from string with fallback
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::OTP_PENDING;
    }

    /**
     * Get all states as array for validation
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace Modules\UserManagement\Service;


use App\Service\BaseService;
use Modules\UserManagement\Repository\OtpVerificationRepositoryInterface;
use Modules\UserManagement\Service\Interface\OtpVerificationServiceInterface;

class OtpVerificationService extends BaseService implements OtpVerificationServiceInterface
{
    protected $otpVerificationRepository;

    public function __construct(OtpVerificationRepositoryInterface $otpVerificationRepository)
    {
        parent::__construct($otpVerificationRepository);
        $this->otpVerificationRepository = $otpVerificationRepository;
    }

    public function storeOtp(string $phone, string $otp): void
    {
        $expires_at = env('APP_MODE') == 'live' ? 10 : 1440; // 10 mins for live, 24 hours for dev
        
        $verification = $this->otpVerificationRepository->findOneBy(['phone_or_email' => $phone]);
        
        if ($verification) {
            $this->otpVerificationRepository->update(id: $verification->id, data: [
                'otp' => $otp,
                'expires_at' => now()->addMinutes($expires_at),
                'is_temp_blocked' => false,
                'failed_attempt' => 0,
                'blocked_at' => null,
            ]);
        } else {
            $this->otpVerificationRepository->create(data: [
                'phone_or_email' => $phone,
                'otp' => $otp,
                'expires_at' => now()->addMinutes($expires_at),
                'is_temp_blocked' => false,
                'failed_attempt' => 0,
                'blocked_at' => null,
            ]);
        }
    }

    public function verifyOtp(string $phone, string $otp): bool
    {
        $verification = $this->otpVerificationRepository->findOneBy([
            'phone_or_email' => $phone,
        ]);

        if (!$verification) {
            return false;
        }

        if ($verification->is_temp_blocked) {
            $block_time = businessConfig('temporary_block_time')?->value ?? 30; // minutes
            if ($verification->blocked_at && now()->diffInMinutes($verification->blocked_at) < $block_time) {
                return false;
            }
            // Reset block if time passed
            $this->otpVerificationRepository->update(id: $verification->id, data: [
                'is_temp_blocked' => false,
                'failed_attempt' => 0,
                'blocked_at' => null,
            ]);
        }

        if (now() > $verification->expires_at) {
            return false;
        }

        if ((string)$verification->otp !== (string)$otp) {
            $verification->increment('failed_attempt');
            $max_hits = businessConfig('maximum_otp_hit')?->value ?? 5;
            if ($verification->failed_attempt >= $max_hits) {
                $this->otpVerificationRepository->update(id: $verification->id, data: [
                    'is_temp_blocked' => true,
                    'blocked_at' => now(),
                ]);
            }
            return false;
        }

        // Success - delete the OTP record
        $this->otpVerificationRepository->delete(id: $verification->id);
        return true;
    }
}

<?php

namespace Modules\AuthManagement\Service;

use App\Service\BaseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Modules\BusinessManagement\Repository\SettingRepositoryInterface;
use Modules\Gateways\Traits\SmsGateway;
use Modules\UserManagement\Repository\OtpVerificationRepositoryInterface;
use Modules\UserManagement\Repository\UserRepositoryInterface;

class AuthService extends BaseService implements Interface\AuthServiceInterface
{
    use SmsGateway;

    protected $userRepository;
    protected $otpVerificationRepository;
    protected $settingRepository;

    public function __construct(UserRepositoryInterface $userRepository, OtpVerificationRepositoryInterface $otpVerificationRepository, SettingRepositoryInterface $settingRepository)
    {
        parent::__construct($userRepository);
        $this->userRepository = $userRepository;
        $this->otpVerificationRepository = $otpVerificationRepository;
        $this->settingRepository = $settingRepository;
    }

    public function checkClientRoute($request)
    {
        $route = str_contains($request->route()?->getPrefix(), 'customer');
        $phoneOrEmail = $request->phone_or_email;

        // Check if it's an email
        if (filter_var($phoneOrEmail, FILTER_VALIDATE_EMAIL)) {
            // Search by email - try requested user type first
            if ($route) {
                $user = $this->userRepository->findOneBy(criteria: ['email' => $phoneOrEmail, 'user_type' => CUSTOMER]);
            } else {
                $user = $this->userRepository->findOneBy(criteria: ['email' => $phoneOrEmail, 'user_type' => DRIVER]);
            }

            // If not found, check for admin-employee
            if (!$user) {
                $user = $this->userRepository->findOneBy(criteria: ['email' => $phoneOrEmail, 'user_type' => 'admin-employee']);
            }

            return $user;
        }

        // It's a phone number - search with multiple formats
        $normalizedPhone = $this->normalizePhoneNumber($phoneOrEmail);
        $originalPhone = preg_replace('/[^0-9+]/', '', $phoneOrEmail);

        // Try to find user with normalized phone first (new format: +20...)
        if ($route) {
            $user = $this->userRepository->findOneBy(criteria: ['phone' => $normalizedPhone, 'user_type' => CUSTOMER]);
            // If not found, try with original format (old format: 01...)
            if (!$user) {
                $user = $this->userRepository->findOneBy(criteria: ['phone' => $originalPhone, 'user_type' => CUSTOMER]);
            }
        } else {
            $user = $this->userRepository->findOneBy(criteria: ['phone' => $normalizedPhone, 'user_type' => DRIVER]);
            // If not found, try with original format (old format: 01...)
            if (!$user) {
                $user = $this->userRepository->findOneBy(criteria: ['phone' => $originalPhone, 'user_type' => DRIVER]);
            }
        }

        // If still not found, check for admin-employee with both phone formats
        if (!$user) {
            $user = $this->userRepository->findOneBy(criteria: ['phone' => $normalizedPhone, 'user_type' => 'admin-employee']);
            if (!$user) {
                $user = $this->userRepository->findOneBy(criteria: ['phone' => $originalPhone, 'user_type' => 'admin-employee']);
            }
        }

        return $user;
    }

    private function generateOtp($user, $otp)
    {
        $expires_at = env('APP_MODE') == 'live' ? 3 : 1000;
        $attributes = [
            'phone_or_email' => $user->phone,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes($expires_at),
        ];
        $verification = $this->otpVerificationRepository->findOneBy(['phone_or_email' => $user->phone]);
        if ($verification) {
            $verification->delete();
        }
        $this->otpVerificationRepository->create(data: $attributes);
        return $otp;
    }

    public function updateLoginUser(string|int $id, array $data): ?Model
    {
        return $this->userRepository->update(id: $id, data: $data);
    }


    public function sendOtpToClient($user, $type = null)
    {
        if ($type == 'trip') {
            $otp = env('APP_MODE') == 'live' ? random_int(1000, 9999) : '0000';
            if (self::send($user->phone, $otp) == "not_found") {
                return $this->generateOtp($user, '0000');
            }
            return $this->generateOtp($user, $otp);
        }
        $dataValues = $this->settingRepository->getBy(criteria: ['settings_type' => SMS_CONFIG]);
        if ($dataValues->where('live_values.status', 1)->isNotEmpty() && env('APP_MODE') == 'live') {
            $otp = rand(1000, 9999);
        } else {
            $otp = '0000';
        }

        if (self::send($user->phone, $otp) == "not_found") {
            return $this->generateOtp($user, '0000');
        }
        return $this->generateOtp($user, $otp);

    }

    /**
     * Send OTP for pending registration (before user account is created)
     * 
     * @param string $phone Phone number to send OTP to
     * @param array $registrationData Registration data to store temporarily
     * @param string $userType 'customer' or 'driver'
     * @return string The OTP code
     */
    public function sendOtpForRegistration(string $phone, array $registrationData, string $userType)
    {
        $dataValues = $this->settingRepository->getBy(criteria: ['settings_type' => SMS_CONFIG]);
        if ($dataValues->where('live_values.status', 1)->isNotEmpty() && env('APP_MODE') == 'live') {
            $otp = rand(1000, 9999);
        } else {
            $otp = '0000';
        }

        // Delete any existing OTP for this phone
        $verification = $this->otpVerificationRepository->findOneBy(['phone_or_email' => $phone]);
        if ($verification) {
            $verification->delete();
        }

        $expires_at = env('APP_MODE') == 'live' ? 3 : 1000;
        
        // Store OTP with registration data
        $attributes = [
            'phone_or_email' => $phone,
            'otp' => $otp,
            'registration_data' => $registrationData,
            'user_type' => $userType,
            'expires_at' => \Carbon\Carbon::now()->addMinutes($expires_at),
        ];
        
        $this->otpVerificationRepository->create(data: $attributes);

        // Attempt to send SMS
        if (self::send($phone, $otp) == "not_found") {
            // Update OTP to 0000 if SMS sending fails
            $verification = $this->otpVerificationRepository->findOneBy(['phone_or_email' => $phone]);
            if ($verification) {
                $verification->update(['otp' => '0000']);
            }
            return '0000';
        }

        return $otp;
    }

    /**
     * Normalize phone number to international format
     *
     * @param string $phone
     * @return string
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except leading +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ensure + prefix for international format
        if (!str_starts_with($phone, '+')) {
            // Assume Egyptian number if starts with 0
            if (str_starts_with($phone, '0')) {
                $phone = '+20' . substr($phone, 1);
            } elseif (str_starts_with($phone, '20')) {
                $phone = '+' . $phone;
            } else {
                // For numbers that don't start with 0 or 20, assume they need +20 prefix
                $phone = '+20' . $phone;
            }
        }

        return $phone;
    }
}

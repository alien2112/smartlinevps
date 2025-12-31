<?php

namespace Modules\UserManagement\Service\Interface;

use App\Service\BaseServiceInterface;

interface OtpVerificationServiceInterface extends BaseServiceInterface
{
    public function storeOtp(string $phone, string $otp): void;

    public function verifyOtp(string $phone, string $otp): bool;
}

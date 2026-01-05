<?php

namespace Modules\AuthManagement\Service\Interface;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Model;

interface AuthServiceInterface extends BaseServiceInterface
{
    public function checkClientRoute($request);
//    public function generateOtp($user);

    public function sendOtpToClient($user,$type=null);

    public function sendOtpForRegistration(string $phone, array $registrationData, string $userType);

    public function updateLoginUser(string|int $id, array $data): ?Model;

}

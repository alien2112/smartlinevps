<?php

namespace Modules\BusinessManagement\Service\Interface;

use App\Service\BaseServiceInterface;

interface ReferralEarningServiceInterface extends BaseServiceInterface
{
    public function storeInfo(array $data);

}

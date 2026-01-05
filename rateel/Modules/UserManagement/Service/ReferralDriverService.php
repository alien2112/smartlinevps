<?php

namespace Modules\UserManagement\Service;

use App\Service\BaseService;
use Modules\UserManagement\Repository\ReferralDriverRepositoryInterface;
use Modules\UserManagement\Service\Interface\ReferralDriverServiceInterface;

class ReferralDriverService extends BaseService implements Interface\ReferralDriverServiceInterface
{
    protected $referralDriverRepository;

    public function __construct(ReferralDriverRepositoryInterface $referralDriverRepository)
    {
        parent::__construct($referralDriverRepository);
        $this->referralDriverRepository = $referralDriverRepository;
    }
}

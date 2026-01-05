<?php

namespace Modules\BusinessManagement\Service;

use App\Service\BaseService;
use Modules\BusinessManagement\Repository\ParcelRefundReasonRepositoryInterface;
use Modules\BusinessManagement\Service\Interface\ParcelRefundReasonServiceInterface;

class ParcelRefundReasonService extends BaseService implements Interface\ParcelRefundReasonServiceInterface
{
    protected $parcelRefundReasonRepository;
    public function __construct(ParcelRefundReasonRepositoryInterface $parcelRefundReasonRepository)
    {
        parent::__construct($parcelRefundReasonRepository);
        $this->parcelRefundReasonRepository = $parcelRefundReasonRepository;
    }
}

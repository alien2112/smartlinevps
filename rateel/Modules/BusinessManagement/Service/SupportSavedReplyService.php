<?php

namespace Modules\BusinessManagement\Service;

use App\Service\BaseService;
use Modules\BusinessManagement\Repository\SupportSavedReplyRepositoryInterface;

class SupportSavedReplyService extends BaseService implements Interface\SupportSavedReplyServiceInterface
{
    protected $supportSavedReplyRepository;
    public function __construct(SupportSavedReplyRepositoryInterface $supportSavedReplyRepository)
    {
        parent::__construct($supportSavedReplyRepository);
        $this->supportSavedReplyRepository = $supportSavedReplyRepository;
    }
}

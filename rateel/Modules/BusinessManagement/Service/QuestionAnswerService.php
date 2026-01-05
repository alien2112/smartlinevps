<?php

namespace Modules\BusinessManagement\Service;

use App\Service\BaseService;
use Modules\BusinessManagement\Repository\QuestionAnswerRepositoryInterface;

class QuestionAnswerService extends BaseService implements Interface\QuestionAnswerServiceInterface
{
    protected $questionAnswerRepository;

    public function __construct(QuestionAnswerRepositoryInterface $questionAnswerRepository)
    {
        parent::__construct($questionAnswerRepository);
        $this->questionAnswerRepository = $questionAnswerRepository;
    }
}

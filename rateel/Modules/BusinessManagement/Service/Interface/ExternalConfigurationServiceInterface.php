<?php

namespace Modules\BusinessManagement\Service\Interface;

use App\Service\BaseServiceInterface;

interface ExternalConfigurationServiceInterface extends BaseServiceInterface
{
    public function storeExternalInfo(array $data): bool;

    public function updateExternalConfiguration(array $data);
}

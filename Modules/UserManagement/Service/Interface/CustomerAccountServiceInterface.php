<?php

namespace Modules\UserManagement\Service\Interface;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerAccountServiceInterface extends BaseServiceInterface
{
    public function createWalletTransaction($customer, array $data ): ?Model;

    public function export(Collection $data): Collection|LengthAwarePaginator|\Illuminate\Support\Collection;
    public function updateManyWithIncrement(array $ids, $column, $amount = 0);

}

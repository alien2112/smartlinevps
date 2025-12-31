<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Console;

use Illuminate\Console\Command;
use Modules\CouponManagement\Service\CouponService;

class ExpireStaleReservationsCommand extends Command
{
    protected $signature = 'coupons:expire-reservations';

    protected $description = 'Expire stale coupon reservations that have passed their expiry time';

    public function handle(CouponService $couponService): int
    {
        $this->info('Expiring stale coupon reservations...');

        $expired = $couponService->expireStaleReservations();

        $this->info("Expired {$expired} stale reservations.");

        return self::SUCCESS;
    }
}

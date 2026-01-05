<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Console;

use Illuminate\Console\Command;
use Modules\CouponManagement\Service\OfferService;

class DeactivateExpiredOffersCommand extends Command
{
    protected $signature = 'offers:deactivate-expired';

    protected $description = 'Deactivate offers that have passed their end date';

    public function handle(OfferService $offerService): int
    {
        $this->info('Checking for expired offers...');

        $count = $offerService->deactivateExpiredOffers();

        $this->info("Deactivated {$count} expired offers.");

        return self::SUCCESS;
    }
}

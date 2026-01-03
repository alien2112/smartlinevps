<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\TripManagement\Service\Interface\ParcelRefundServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;

class GlobalDataServiceProvider extends ServiceProvider
{


    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {


        View::composer(
            'adminmodule::partials._sidebar',
            function ($view) {
                $tripCount = [];
                $parcelRefundCount = [];
                $view->with('tripCount', $this->getTripCounts());
                $view->with('parcelRefundCount', $this->getParcelRefundCounts());
            }
        );
    }



    private function getTripCounts()
    {
        $tripService = app()->make(TripRequestServiceInterface::class);

        return [
            'all' => $tripService->index()->count(),
            'completed' => $tripService->getBy(criteria:['current_status' => COMPLETED])->count(),
            'pending' => $tripService->getBy(criteria:['current_status' => PENDING])->count(),
            'accepted' => $tripService->getBy(criteria:['current_status' => ACCEPTED])->count(),
            'ongoing' => $tripService->getBy(criteria:['current_status' => ONGOING])->count(),
            'cancelled' => $tripService->getBy(criteria:['current_status' => CANCELLED])->count(),
            'returning' => $tripService->getBy(criteria:['current_status' => RETURNING])->count(),
            'returned' => $tripService->getBy(criteria:['current_status' => RETURNED])->count(),
        ];
    }
    private function getParcelRefundCounts()
    {
        $parcelRefundService = app()->make(ParcelRefundServiceInterface::class);

        return [
            'all' => $parcelRefundService->index()->count(),
            'pending' => $parcelRefundService->getBy(criteria:['status' => PENDING])->count(),
            'approved' => $parcelRefundService->getBy(criteria:['status' => APPROVED])->count(),
            'denied' => $parcelRefundService->getBy(criteria:['status' => DENIED])->count(),
            'refunded' => $parcelRefundService->getBy(criteria:['status' => REFUNDED])->count(),
        ];
    }
}

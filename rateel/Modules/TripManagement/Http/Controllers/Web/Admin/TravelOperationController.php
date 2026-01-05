<?php

namespace Modules\TripManagement\Http\Controllers\Web\Admin;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TripManagement\Entities\TripRequest;
use Carbon\Carbon;

class TravelOperationController extends Controller
{
    /**
     * Display the scheduled travel calendar and operations view.
     * @return Renderable
     */
    public function calendar(Request $request)
    {
        // Fetch upcoming travel trips
        $query = TripRequest::where('trip_type', 'travel')
            ->where('scheduled_at', '>=', Carbon::today());

        if ($request->has('status')) {
            $query->where('current_status', $request->status);
        }

        $trips = $query->orderBy('scheduled_at')->get();

        // Group trips by date for calendar view
        $calendarData = $trips->groupBy(function($date) {
            return Carbon::parse($date->scheduled_at)->format('Y-m-d');
        });

        return view('tripmanagement::admin.travel.calendar', compact('calendarData'));
    }

    /**
     * Display analytics for travel analytics (Pricing/Distance).
     * @return Renderable
     */
    public function analytics()
    {
        $trips = TripRequest::where('trip_type', 'travel')
            ->select('id', 'estimated_distance', 'offer_price', 'actual_fare', 'seats_requested')
            ->whereNotNull('offer_price')
            ->get();

        // Basic calculation for Avg Offer Price / km
        $totalDistance = $trips->sum('estimated_distance');
        $totalOffer = $trips->sum('offer_price');
        $avgPricePerKm = $totalDistance > 0 ? $totalOffer / $totalDistance : 0;

        // Data for charts
        $chartData = $trips->map(function ($trip) {
            return [
                'distance' => $trip->estimated_distance,
                'price' => $trip->offer_price,
                'seats' => $trip->seats_requested
            ];
        });

        return view('tripmanagement::admin.travel.analytics', compact('avgPricePerKm', 'chartData', 'trips'));
    }
}

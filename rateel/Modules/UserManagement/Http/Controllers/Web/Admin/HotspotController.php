<?php

namespace Modules\UserManagement\Http\Controllers\Web\Admin;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\UserManagement\Entities\UserAddress;
use Illuminate\Support\Facades\DB;

class HotspotController extends Controller
{
    /**
     * Display a heatmap of popular user saved addresses.
     * @return Renderable
     */
    public function index()
    {
        // Fetch all addresses to plot on map
        // Optimization: In a real large app, you'd aggregate this with a DB query
        // e.g. clustering nearby points. For 10k users, fetching points is okay for now but limit it.
        $hotspots = UserAddress::select('latitude', 'longitude', 'address_label')
            ->limit(1000) 
            ->get();
            
        return view('usermanagement::admin.hotspots.index', compact('hotspots'));
    }
}

<?php

namespace Modules\VehicleManagement\Http\Controllers\Web\New\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Modules\VehicleManagement\Entities\VehicleYear;
use Brian2694\Toastr\Facades\Toastr;

class VehicleYearController extends Controller
{
   public function index(Request $request): View
    {
        $this->authorize('vehicle_view');
    
        $query = VehicleYear::query();
    
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('year', 'like', "%$search%");
        }
    
        $years = $query->orderBy('created_at', 'desc')->paginate(paginationLimit());
    
        return view('vehiclemanagement::admin.year.index', compact('years'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('vehicle_add');

        $request->validate([
            'year' => 'required|integer|min:1900|max:' . date('Y'),
        ]);

        VehicleYear::create([
            'year' => $request->year,
        ]);

        Toastr::success('Year added successfully.');
        return back();
    }

    public function edit($id): View
    {
        $this->authorize('vehicle_edit');

        $year = VehicleYear::findOrFail($id);
        return view('vehiclemanagement::admin.year.edit', compact('year'));
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $this->authorize('vehicle_edit');

        $request->validate([
            'year' => 'required|integer|min:1900|max:' . date('Y'),
        ]);

        $year = VehicleYear::findOrFail($id);
        $year->update([
            'year' => $request->year,
        ]);

        Toastr::success('Year updated successfully.');
        return redirect()->route('admin.vehicle.attribute-setup.year.index');
    }

    public function destroy($id): RedirectResponse
    {
        $this->authorize('vehicle_delete');

        $year = VehicleYear::findOrFail($id);
        $year->delete();

        Toastr::success('Year deleted successfully.');
        return redirect()->route('admin.vehicle.attribute-setup.year.index');
    }

    public function getAllAjax(Request $request): JsonResponse
    {
        $years = VehicleYear::orderBy('year', 'desc')->get();

        $selectYears = $years->map(function ($item) {
            return [
                'text' => $item->year,
                'id' => $item->id
            ];
        });

        return response()->json($selectYears);
    }
}

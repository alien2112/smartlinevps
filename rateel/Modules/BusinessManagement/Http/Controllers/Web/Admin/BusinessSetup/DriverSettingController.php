<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\DriverSettingStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interface\BusinessSettingServiceInterface;

class DriverSettingController extends BaseController
{
    protected $businessSettingService;

    public function __construct(BusinessSettingServiceInterface $businessSettingService)
    {
        parent::__construct($businessSettingService);
        $this->businessSettingService = $businessSettingService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('business_view');

        $settings = $this->businessSettingService->getBy(criteria: ['settings_type' => DRIVER_SETTINGS]);
        return view('businessmanagement::admin.business-setup.driver', compact('settings'));
    }

    public function store(DriverSettingStoreOrUpdateRequest $request): RedirectResponse|Renderable
    {
        $this->authorize('business_view');
        $this->businessSettingService->storeDriverSetting($request->validated());
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }
    public function vehicleUpdate(Request $request): RedirectResponse|Renderable
    {
        $this->authorize('business_view');
        $this->businessSettingService->storeVehicleUpdateDriverSetting($request->all());
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }
}

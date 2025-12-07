<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\ReferralEarningSettingStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interface\ReferralEarningServiceInterface;

class ReferralEarningSettingController extends BaseController
{
    use AuthorizesRequests;

    protected $referralEarningService;

    public function __construct(ReferralEarningServiceInterface $referralEarningService)
    {
        parent::__construct($referralEarningService);
        $this->referralEarningService = $referralEarningService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('business_view');

        $customerSettings = $this->referralEarningService->getBy(criteria: ['settings_type'=>CUSTOMER]);
        $driverSettings = $this->referralEarningService->getBy(criteria: ['settings_type'=>DRIVER]);
        return view('businessmanagement::admin.business-setup.referral-earning', compact('customerSettings','driverSettings'));
    }

    public function store(ReferralEarningSettingStoreOrUpdateRequest $request): RedirectResponse|Renderable
    {
        $this->authorize('business_view');
        $this->referralEarningService->storeInfo($request->validated());
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }
}

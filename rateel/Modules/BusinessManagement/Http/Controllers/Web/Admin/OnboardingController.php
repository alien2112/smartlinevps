<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BusinessManagement\Entities\BusinessSetting;
use Brian2694\Toastr\Facades\Toastr;

class OnboardingController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $onboardingData = BusinessSetting::where(['key_name' => 'onboarding_screens', 'settings_type' => 'app_settings'])->first();
        $screens = isset($onboardingData) ? json_decode($onboardingData->value, true) : [];
        
        return view('businessmanagement::admin.onboarding.index', compact('screens'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function update(Request $request)
    {
        $screens = [];
        if ($request->has('screens')) {
            foreach ($request->screens as $key => $screen) {
                $imageName = isset($screen['existing_image']) ? $screen['existing_image'] : null;
                
                if (isset($screen['image'])) {
                     $imageName = file_uploader('onboarding/', 'png', $screen['image']);
                }

                $screens[] = [
                    'title' => $screen['title'],
                    'description' => $screen['description'],
                    'image' => $imageName
                ];
            }
        }

        BusinessSetting::updateOrCreate(
            ['key_name' => 'onboarding_screens', 'settings_type' => 'app_settings'],
            ['value' => json_encode($screens)]
        );

        Toastr::success(translate('Onboarding_Screens_Updated_Successfully'));
        return back();
    }
}

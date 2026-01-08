<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SupportController extends Controller
{
    /**
     * Get app version and info
     * GET /api/customer/auth/support/app-info
     */
    public function appInfo(): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, [
            'app_name' => config('app.name'),
            'app_version' => '1.0.0',
            'api_version' => '2.0',
            'minimum_supported_version' => '1.0.0',
            'latest_version' => '1.0.0',
            'force_update_required' => false,
            'support_email' => businessConfig('business_support_email')?->value ?? 'support@smartline-it.com',
            'support_phone' => businessConfig('business_support_phone')?->value ?? '+20 xxx xxx xxxx',
            'emergency_number' => '911',
        ]));
    }
}

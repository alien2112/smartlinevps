<?php

namespace Modules\UserManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Interfaces\CustomerInterface;
use Modules\UserManagement\Transformers\CustomerResource;

class CustomerController extends Controller
{
    private CustomerInterface $customer;

    public function __construct(CustomerInterface $customer)
    {
        $this->customer = $customer;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function profileInfo(Request $request): JsonResponse
    {
        if (strcmp($request->user()->user_type, CUSTOMER_USER_TYPES) == 0) {
            $attributes = [
                'relations' => ['userAccount', 'level'],
                'withCount' => 'customerTrips',
                'withAvgRelation' => 'receivedReviews',
                'withAvgColumn' => 'rating'
            ];
            $customer = $this->customer->getBy(column: 'id', value: auth()->id(), attributes: $attributes);
            $customer = new CustomerResource($customer);

            return response()->json(responseFormatter(DEFAULT_200, $customer), 200);
        }

        return response()->json(responseFormatter(DEFAULT_401), 401);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $maxSize = config('image.max_size', 500);
        $mimes = implode(',', config('image.allowed_mimes', ['jpeg', 'jpg', 'png', 'gif', 'webp']));
        
        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'unique:users,email,' . $request->user()->id,
            'profile_image' => "image|mimes:{$mimes}|max:{$maxSize}",
            'identification_type' => 'in:nid,passport,driving_license',
            'identification_number' => 'sometimes',
            'identity_images' => 'sometimes|array',
            'identity_images.*' => "image|mimes:{$mimes}|max:{$maxSize}",
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $this->customer->update(attributes: $request->all(), id: $request->user()->id);

        //Mart profile update
        if (checkSelfExternalConfiguration()) {
            $martBaseUrl = externalConfig('mart_base_url')?->value;
            $systemSelfToken = externalConfig('system_self_token')?->value;
            $martToken = externalConfig('mart_token')?->value;
            try {
                $response = Http::asForm()->post($martBaseUrl . '/api/v1/customer/external-update-data',
                    [
                        'bearer_token' => $request->bearerToken(),
                        'token' => $martToken,
                        'external_base_url' => url('/'),
                        'external_token' => $systemSelfToken,
                    ]);
            }catch (\Exception $exception){

            }

        }

        return response()->json(responseFormatter(DEFAULT_UPDATE_200), 200);
    }
}

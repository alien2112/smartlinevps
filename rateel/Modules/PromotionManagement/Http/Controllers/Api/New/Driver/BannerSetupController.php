<?php

namespace Modules\PromotionManagement\Http\Controllers\Api\New\Driver;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PromotionManagement\Service\Interface\BannerSetupServiceInterface;
use Modules\PromotionManagement\Transformers\BannerResource;
use Modules\PromotionManagement\Entities\BannerSetup;

class BannerSetupController extends Controller
{
    protected $bannerService;
    public function __construct(BannerSetupServiceInterface $bannerService)
    {
        $this->bannerService = $bannerService;
    }
    // public function list(Request $request):JsonResponse
    // {

    //     $today = Carbon::today();
    //     $banner = $this->bannerService->list($today,limit:$request['limit'],offset:$request['offset']);
    //     $data = BannerResource::collection($banner);
    //     return response()->json(responseFormatter(DEFAULT_200, $data,$request['limit'], $request['offset']));

    // }
    
    public function list(Request $request): JsonResponse
    {
        $today = Carbon::today();
    
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
    
        // تحويل offset إلى رقم الصفحة (paginate يعتمد على رقم الصفحة وليس الإزاحة المباشرة)
        $page = floor($offset / $limit) + 1;
    
        $banners = BannerSetup::where('is_active', 1)
            ->where('target_audience', 'driver')
            ->where(function ($query) use ($today) {
                $query->where('time_period', '!=', 'period')
                    ->orWhere(function ($periodQuery) use ($today) {
                        $periodQuery->whereNull('start_date')
                            ->orWhere(function ($dateQuery) use ($today) {
                                $dateQuery->where('start_date', '<=', $today)
                                          ->where('end_date', '>=', $today);
                            });
                    });
            })
            ->paginate($limit, ['*'], 'page', $page);
    
        $data = BannerResource::collection($banners);
    
        return response()->json(responseFormatter(DEFAULT_200, $data, $limit, $offset));
    }

     public function RedirectionCount(Request $request)
     {
         $banner = $this->bannerService->findOne($request->banner_id);

         if(!is_null($banner)){
             $banner->total_redirection = $banner->total_redirection + 1;
             $banner->save();
             return response()->json(responseFormatter(DEFAULT_STORE_200, $banner->total_redirection));
         }

         return response()->json(responseFormatter(DEFAULT_404));
     }
}

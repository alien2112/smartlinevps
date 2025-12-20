<?php

namespace Modules\TripManagement\Http\Controllers\Web\New;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\TripManagement\Service\Interface\LostItemServiceInterface;
use Modules\TripManagement\Entities\LostItem;

class LostItemController extends Controller
{
    protected $lostItemService;

    public function __construct(LostItemServiceInterface $lostItemService)
    {
        $this->lostItemService = $lostItemService;
    }

    /**
     * Display list of lost item reports
     */
    public function index(Request $request)
    {
        $this->authorize('trip_view');
        
        $criteria = $request->all();
        $relations = ['customer', 'driver', 'trip'];
        
        $lostItems = $this->lostItemService->index(
            criteria: $criteria,
            relations: $relations,
            orderBy: ['created_at' => 'desc'],
            limit: paginationLimit(),
            offset: $request['page'] ?? 1
        );
        
        // Get counts for status tabs
        $statusCounts = [
            'all' => LostItem::count(),
            'pending' => LostItem::where('status', 'pending')->count(),
            'found' => LostItem::where('status', 'found')->count(),
            'returned' => LostItem::where('status', 'returned')->count(),
            'closed' => LostItem::where('status', 'closed')->count(),
        ];
        
        return view('tripmanagement::admin.lost-items.index', compact('lostItems', 'statusCounts'));
    }

    /**
     * Display lost item details
     */
    public function show($id)
    {
        $this->authorize('trip_view');
        
        $lostItem = $this->lostItemService->findOneBy(
            criteria: ['id' => $id],
            relations: ['customer', 'driver', 'trip.coordinate', 'statusLogs.changedBy']
        );
        
        if (!$lostItem) {
            Toastr::error(translate('Lost item not found'));
            return redirect()->route('admin.lost-items.index');
        }
        
        return view('tripmanagement::admin.lost-items.show', compact('lostItem'));
    }

    /**
     * Update lost item status
     */
    public function updateStatus(Request $request, $id)
    {
        $this->authorize('trip_edit');
        
        $lostItem = $this->lostItemService->findOneBy(criteria: ['id' => $id]);
        
        if (!$lostItem) {
            Toastr::error(translate('Lost item not found'));
            return redirect()->back();
        }
        
        $data = [
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
        ];
        
        $this->lostItemService->updateStatus($id, $request->status, $request->admin_notes, Auth::id());
        
        // Send notification to customer
        if ($lostItem->customer) {
            sendDeviceNotification(
                fcm_token: $lostItem->customer->fcm_token,
                title: translate("Lost Item Status Update"),
                description: translate("Your lost item report status has been updated to: ") . $request->status,
                status: 1,
                ride_request_id: $lostItem->trip_request_id,
                type: 'lost_item',
                action: 'lost_item_status_updated',
                user_id: $lostItem->customer_id
            );
        }
        
        Toastr::success(translate('Status updated successfully'));
        return redirect()->back();
    }

    /**
     * Export lost items
     */
    public function export(Request $request)
    {
        $this->authorize('trip_export');
        
        $criteria = $request->all();
        $relations = ['customer', 'driver', 'trip'];
        
        $data = $this->lostItemService->export(
            criteria: $criteria,
            relations: $relations
        );
        
        return exportData($data, $request['file'], 'tripmanagement::admin.lost-items.print');
    }
}

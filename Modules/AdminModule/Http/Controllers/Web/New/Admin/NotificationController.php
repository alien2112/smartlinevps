<?php

namespace Modules\AdminModule\Http\Controllers\Web\New\Admin;

use Modules\AdminModule\Entities\Helpers;
use App\CPU\ImageManager;
use App\Http\Controllers\Controller;
use Modules\AdminModule\Entities\Notification;
use App\Model\Translation;
use Modules\AdminModule\Entities\NotificationMessage;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Modules\UserManagement\Entities\User;
use Modules\BusinessManagement\Http\Requests\FirebaseConfigurationStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\NotificationSetupStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interface\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interface\FirebasePushNotificationServiceInterface;
use Modules\BusinessManagement\Service\Interface\NotificationSettingServiceInterface;

class NotificationController extends Controller
{
    // public function __construct(
    //     private NotificationMessage $notification_message,
    // ){

    // }
    //  protected $notificationSettingService;
    // protected $firebasePushNotificationService;
    // protected $businessSettingService;

    // public function __construct(NotificationSettingServiceInterface $notificationSettingService, FirebasePushNotificationServiceInterface $firebasePushNotificationService, BusinessSettingServiceInterface $businessSettingService)
    // {
    //     // parent::__construct($notificationSettingService);
    //     $this->notificationSettingService = $notificationSettingService;
    //     $this->firebasePushNotificationService = $firebasePushNotificationService;
    //     $this->businessSettingService = $businessSettingService;
    // }
    public function index(Request $request)
    {
        $query_param = [];
        $search = $request['search'];
        if ($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $notifications = Notification::where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->Where('title', 'like', "%{$value}%");
                }
            });
            $query_param = ['search' => $request['search']];
        }else{
            $notifications = new Notification();
        }
        $notifications = $notifications->latest()->paginate(10)->appends($query_param);
        return view('adminmodule::notification.index', compact('notifications','search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'description' => 'required',
            'sent_to' => 'required|in:captin,customer',
        ], [
            'title.required' => 'title is required!',
        ]);

        $notification = new Notification;
        $notification->title = $request->title;
        $notification->description = $request->description;
        $notification->sent_to = $request->sent_to;

        if ($request->has('image')) {
            // $notification->image = fileUploader('push-notification/', 'webp', $request->file('image'));
            $notification->image = fileUploader('notification/', 'webp', $request->file('image'));
        } else {
            $notification->image = 'null';
        }
        
        $notification->status             = 1;
        $notification->notification_count = 1;
        $notification->save();
        
         // تحديد نوع المرسل إليه (كابتن أو عميل)
        if ($request->sent_to === 'captin') {
            // إرسال الإشعار إلى جميع الكابتن
            $receiverTokens = $this->getAllCaptainTokens(); // دالة لاستخراج توكنات الكابتن
                // dd($receiverTokens);
        } elseif ($request->sent_to === 'customer') {
            // إرسال الإشعار إلى جميع العملاء
            $receiverTokens = $this->getAllCustomerTokens(); // دالة لاستخراج توكنات العملاء
            
        } else {
            // إذا كانت القيمة غير صالحة
            Toastr::error('Invalid recipient type');
            return back();
        }
    
        // إرسال الإشعار إلى جميع الأجهزة
         foreach ($receiverTokens as  $receiver) {
        // استخدام الدالة sendDeviceNotification لإرسال إشعار FCM
        sendDeviceNotification(
            fcm_token: $receiver->fcm_token,
            title: $request->title,
            description: $request->description,
            status: 1,
            image:$request->file('image'),
            type: 'send_notification', // يمكنك تحديد نوع الإشعار كـ "إعادة إرسال"
            action: 'send_notification', // يمكن إضافة أكشن في حالة وجوده
            user_id: $receiver->id
        );
    }

        Toastr::success(translate('notification_sent_successfully'));
        return back();
    }
    public function getAllCaptainTokens()
    {
        // استرجاع جميع الكابتن من قاعدة البيانات واستخراج التوكنات
        return User::where('user_type', 'driver') // تأكد من أن الكابتن يتم تحديده باستخدام `role` أو أي عمود آخر
            ->select('id','fcm_token') // استخراج جميع توكنات الكابتن
            ->get();
    }
    public function getAllCustomerTokens()
    {
        // استرجاع جميع العملاء من قاعدة البيانات واستخراج التوكنات
        return User::where('user_type', 'customer') // تأكد من أن العملاء يتم تحديدهم باستخدام `role` أو أي عمود آخر
            ->select('id','fcm_token') // استخراج جميع توكنات العملاء
            ->get();
    }

    public function edit($id)
    {
        $notification = Notification::find($id);
        return view('adminmodule::notification.edit', compact('notification'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required',
            'description' => 'required',
        ], [
            'title.required' => 'title is required!',
        ]);

        $notification = Notification::find($id);
        $notification->title = $request->title;
        $notification->description = $request->description;
        $notification->sent_to = $request->sent_to;
         $notification->product_slug = $request->product;
        if ($request->hasFile('image')) {
            if ($notification->image && $notification->image !== 'null') {
            Storage::disk('public')->delete('notification/' . $notification->image);
        }
            $notification->image = fileUploader('notification/', 'webp', $request->file('image'));
        }
        $notification->save();

        Toastr::success(translate('notification_updated_successfully'));
        return redirect('/admin/add-new');
    }

    public function status(Request $request)
{
    $notification = Notification::find($request->id);

    if ($notification) {
        $notification->status = $request->status;
        $notification->save();
            Toastr::success(translate('status_updated_successfully'));
        return back();
    }
        Toastr::warning(translate('status_updated_faild'));
    return back();
}


    // public function resendNotification(Request $request){
    //     $notification = Notification::find($request->id);

    //     $data = array();
    //     try {
    //         Helpers::send_push_notif_to_topic($notification);
    //         $notification->notification_count += 1;
    //         $notification->save();

    //         $data['success'] = true;
    //         $data['message'] = translate("push_notification_successfully");
    //     } catch (\Exception $e) {
    //         $data['success'] = false;
    //         $data['message'] = translate("push_notification_failed");
    //     }

    //     return $data;
    // }
    public function resendNotification(Request $request)
{
    // العثور على الإشعار باستخدام الـ id المرسل
    $notification = Notification::find($request->id);
    
    // التأكد من أن الإشعار موجود
    if (!$notification) {
        return response()->json([
            'success' => false,
            'message' => 'Notification not found',
        ]);
    }

    $receiverTokens = [];

    // تحديد نوع المرسل إليه (كابتن أو عميل)
    if ($notification->sent_to === 'captin') {
        $receiverTokens = $this->getAllCaptainTokens(); // دالة لاستخراج توكنات الكابتن
    } elseif ($notification->sent_to === 'customer') {
        $receiverTokens = $this->getAllCustomerTokens(); // دالة لاستخراج توكنات العملاء
    }

    $data = array();

    try {
        // إرسال الإشعار إلى جميع الأجهزة باستخدام توكنات FCM
        foreach ($receiverTokens as $receiver) {
            // إرسال الإشعار باستخدام دالة sendDeviceNotification
            sendDeviceNotification(
                fcm_token: $receiver->fcm_token,
                title: $notification->title,
                description: $notification->description,
                status: 1, // حالة الإشعار
                image: $notification->image, // الصورة في الإشعار
                type: 'resend_notification', // يمكنك تحديد نوع الإشعار كـ "إعادة إرسال"
                action: 'resend_notification', // يمكن إضافة أكشن في حالة وجوده
                user_id: $receiver->id, // إرسال الـ ID للمستخدم
            );
        }

        // زيادة عدد الإشعارات المرسلة
        $notification->notification_count += 1;
        $notification->save();

        $data['success'] = true;
        $data['message'] = translate("push_notification_successfully");
    } catch (\Exception $e) {
        // في حالة حدوث خطأ
        $data['success'] = false;
        $data['message'] = translate("push_notification_failed");
    }

    return back();
}


    public function delete(Request $request)
    {
        $notification = Notification::find($request->id);
        // ImageManager::delete('/notification/' . $notification['image']);
        // Storage::disk('public')->delete('notification/' . $notification['image']);
        if ($notification->image && $notification->image !== 'null') {
            Storage::disk('public')->delete('notification/' . $notification->image);
        }
        $notification->delete();
        Toastr::success(translate('notification_deleted_successfully'));
        return back();
    }

}

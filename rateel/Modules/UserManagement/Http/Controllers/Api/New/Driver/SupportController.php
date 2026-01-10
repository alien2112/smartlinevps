<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\SupportTicket;

class SupportController extends Controller
{
    /**
     * Get app version and support info
     * GET /api/driver/support/app-info
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
            'support_whatsapp' => businessConfig('business_support_whatsapp')?->value ?? null,
            'working_hours' => businessConfig('support_working_hours')?->value ?? '9:00 AM - 9:00 PM',
            'emergency_number' => '911',
            'help_center_url' => businessConfig('help_center_url')?->value ?? null,
        ]));
    }

    /**
     * Get frequently asked questions
     * GET /api/driver/support/faq
     */
    public function faq(): JsonResponse
    {
        // Could be fetched from database in future
        $faqs = [
            [
                'id' => 1,
                'question' => 'كيف أسحب أرباحي؟',
                'question_en' => 'How do I withdraw my earnings?',
                'answer' => 'يمكنك سحب أرباحك من خلال قائمة المحفظة > طلب سحب',
                'answer_en' => 'You can withdraw your earnings from Wallet menu > Request Withdrawal',
                'category' => 'payment',
            ],
            [
                'id' => 2,
                'question' => 'كيف أغير حالتي إلى متاح؟',
                'question_en' => 'How do I change my status to available?',
                'answer' => 'انقر على زر التبديل في الشاشة الرئيسية لتغيير حالتك',
                'answer_en' => 'Click the toggle button on the home screen to change your status',
                'category' => 'trip',
            ],
            [
                'id' => 3,
                'question' => 'ما هي نسبة العمولة؟',
                'question_en' => 'What is the commission rate?',
                'answer' => 'تختلف نسبة العمولة حسب فئة السيارة. راجع تفاصيل العقد الخاص بك.',
                'answer_en' => 'Commission rate varies by vehicle category. Check your contract details.',
                'category' => 'payment',
            ],
            [
                'id' => 4,
                'question' => 'كيف أحدث بيانات سيارتي؟',
                'question_en' => 'How do I update my vehicle information?',
                'answer' => 'اذهب إلى الملف الشخصي > السيارة > تعديل البيانات',
                'answer_en' => 'Go to Profile > Vehicle > Edit Information',
                'category' => 'account',
            ],
            [
                'id' => 5,
                'question' => 'ماذا أفعل في حالة الطوارئ؟',
                'question_en' => 'What do I do in an emergency?',
                'answer' => 'استخدم زر SOS في التطبيق أو اتصل بالطوارئ مباشرة',
                'answer_en' => 'Use the SOS button in the app or call emergency services directly',
                'category' => 'other',
            ],
            [
                'id' => 6,
                'question' => 'لماذا رصيد محفظتي سالب؟',
                'question_en' => 'Why is my wallet balance negative?',
                'answer' => 'عند استلام نقدية من العملاء، يتم خصم العمولة من محفظتك. يرجى شحن المحفظة لتسوية الرصيد السالب.',
                'answer_en' => 'When you receive cash from customers, the commission is deducted from your wallet. Please top-up your wallet to clear the negative balance.',
                'category' => 'payment',
            ],
        ];

        return response()->json(responseFormatter(DEFAULT_200, [
            'faqs' => $faqs,
            'categories' => [
                ['id' => 'all', 'name' => 'الكل', 'name_en' => 'All'],
                ['id' => 'payment', 'name' => 'الدفع', 'name_en' => 'Payment'],
                ['id' => 'trip', 'name' => 'الرحلات', 'name_en' => 'Trips'],
                ['id' => 'account', 'name' => 'الحساب', 'name_en' => 'Account'],
                ['id' => 'other', 'name' => 'أخرى', 'name_en' => 'Other'],
            ],
        ]));
    }

    /**
     * Submit a support ticket
     * POST /api/driver/support/ticket
     */
    public function createTicket(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'category' => 'nullable|in:payment,trip,account,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'trip_id' => 'nullable|uuid|exists:trip_requests,id',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        $ticket = SupportTicket::create([
            'user_id' => $driver->id,
            'user_type' => 'driver',
            'subject' => $request->subject,
            'message' => $request->message,
            'category' => $request->category ?? SupportTicket::CATEGORY_OTHER,
            'priority' => $request->priority ?? 'normal',
            'trip_id' => $request->trip_id,
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        // Send notification to admin (optional)
        // dispatch(new \App\Jobs\NotifyAdminNewTicketJob($ticket));

        return response()->json(responseFormatter(DEFAULT_STORE_200, [
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
            'message' => 'Your support ticket has been submitted. We will respond shortly.',
        ]));
    }

    /**
     * Get driver's support tickets
     * GET /api/driver/support/tickets
     */
    public function getTickets(Request $request): JsonResponse
    {
        $driver = auth('api')->user();
        
        $query = SupportTicket::where('user_id', $driver->id)
            ->orderBy('created_at', 'desc');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $limit = $request->get('limit', 10);
        $tickets = $query->paginate($limit);

        $data = $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'message' => $ticket->message,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'admin_response' => $ticket->admin_response,
                'responded_at' => $ticket->responded_at?->toIso8601String(),
                'created_at' => $ticket->created_at->toIso8601String(),
                'trip_id' => $ticket->trip_id,
            ];
        });

        return response()->json(responseFormatter(DEFAULT_200, [
            'tickets' => $data,
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]));
    }

    /**
     * Get single ticket details
     * GET /api/driver/support/ticket/{id}
     */
    public function getTicket(string $id): JsonResponse
    {
        $driver = auth('api')->user();
        
        $ticket = SupportTicket::where('user_id', $driver->id)
            ->where('id', $id)
            ->with('trip:id,ref_id,created_at')
            ->first();

        if (!$ticket) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'admin_response' => $ticket->admin_response,
            'responded_at' => $ticket->responded_at?->toIso8601String(),
            'created_at' => $ticket->created_at->toIso8601String(),
            'trip' => $ticket->trip ? [
                'id' => $ticket->trip->id,
                'ref_id' => $ticket->trip->ref_id,
                'created_at' => $ticket->trip->created_at->toIso8601String(),
            ] : null,
        ]));
    }
}

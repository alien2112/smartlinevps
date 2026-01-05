<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\AppFeedback;
use App\Models\IssueReport;
use App\Models\DriverNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    /**
     * Get FAQs
     * GET /api/driver/auth/support/faqs
     */
    public function faqs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => 'sometimes|in:general,trips,payments,account,vehicle',
            'search' => 'sometimes|string|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $query = Faq::active()->forDriver()->ordered();

        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('question', 'LIKE', "%{$search}%")
                  ->orWhere('answer', 'LIKE', "%{$search}%");
            });
        }

        $faqs = $query->get()->groupBy('category');

        return response()->json(responseFormatter(DEFAULT_200, [
            'faqs' => $faqs,
            'categories' => [
                'general' => translate('General'),
                'trips' => translate('Trips & Bookings'),
                'payments' => translate('Payments & Earnings'),
                'account' => translate('Account & Profile'),
                'vehicle' => translate('Vehicle & Documents'),
            ],
        ]));
    }

    /**
     * Mark FAQ as helpful/not helpful
     * POST /api/driver/auth/support/faqs/{id}/feedback
     */
    public function faqFeedback(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'helpful' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $faq = Faq::find($id);

        if (!$faq) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $faq->incrementView();

        if ($request->helpful) {
            $faq->markAsHelpful();
        } else {
            $faq->markAsNotHelpful();
        }

        return response()->json(responseFormatter([
            'response_code' => 'feedback_recorded_200',
            'message' => translate('Thank you for your feedback'),
        ]));
    }

    /**
     * Create support ticket
     * POST /api/driver/auth/support/tickets
     */
    public function createTicket(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'description' => 'required|string|min:10',
            'category' => 'required|in:technical,account,payment,trip_issue,other',
            'priority' => 'sometimes|in:low,normal,high,urgent',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        $ticket = SupportTicket::create([
            'driver_id' => $driver->id,
            'subject' => $request->subject,
            'description' => $request->description,
            'category' => $request->category,
            'priority' => $request->input('priority', 'normal'),
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        // Create initial message
        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $driver->id,
            'message' => $request->description,
            'is_admin_reply' => false,
        ]);

        // Notify admins (you can implement this based on your notification system)
        \Modules\AdminModule\Entities\AdminNotification::create([
            'model' => 'support_ticket',
            'model_id' => $ticket->id,
            'message' => 'new_support_ticket',
        ]);

        return response()->json(responseFormatter([
            'response_code' => 'ticket_created_201',
            'message' => translate('Support ticket created successfully'),
            'data' => [
                'ticket_number' => $ticket->ticket_number,
                'ticket_id' => $ticket->id,
                'status' => $ticket->status,
            ],
        ]), 201);
    }

    /**
     * Get all support tickets
     * GET /api/driver/auth/support/tickets
     */
    public function tickets(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:all,open,in_progress,resolved,closed',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $query = SupportTicket::where('driver_id', $driver->id)
            ->with(['latestMessage'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $total = $query->count();
        $tickets = $query->skip($offset)->take($limit)->get();

        return response()->json(responseFormatter(DEFAULT_200, [
            'tickets' => $tickets->map(fn($t) => [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'subject' => $t->subject,
                'category' => $t->category,
                'priority' => $t->priority,
                'status' => $t->status,
                'last_message' => $t->latestMessage?->message,
                'last_message_at' => $t->latestMessage?->created_at?->toIso8601String(),
                'created_at' => $t->created_at->toIso8601String(),
                'has_unread_replies' => $t->messages()->where('is_admin_reply', true)->where('is_read', false)->exists(),
            ]),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]));
    }

    /**
     * Get ticket details with messages
     * GET /api/driver/auth/support/tickets/{id}
     */
    public function ticketDetails(string $id): JsonResponse
    {
        $driver = auth('api')->user();

        $ticket = SupportTicket::where('driver_id', $driver->id)
            ->with(['messages.user', 'assignedAgent'])
            ->find($id);

        if (!$ticket) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        // Mark admin replies as read
        $ticket->messages()
            ->where('is_admin_reply', true)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(responseFormatter(DEFAULT_200, [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at->toIso8601String(),
                'resolved_at' => $ticket->resolved_at?->toIso8601String(),
                'resolution_note' => $ticket->resolution_note,
                'rating' => $ticket->rating,
            ],
            'messages' => $ticket->messages->map(fn($m) => [
                'id' => $m->id,
                'message' => $m->message,
                'attachments' => $m->attachments,
                'is_admin_reply' => $m->is_admin_reply,
                'sender_name' => $m->is_admin_reply ? ($m->user->first_name ?? 'Support Team') : 'You',
                'created_at' => $m->created_at->toIso8601String(),
                'time_ago' => $m->created_at->diffForHumans(),
            ]),
        ]));
    }

    /**
     * Reply to ticket
     * POST /api/driver/auth/support/tickets/{id}/reply
     */
    public function replyToTicket(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:1',
            'attachments' => 'sometimes|array|max:3',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        $ticket = SupportTicket::where('driver_id', $driver->id)->find($id);

        if (!$ticket) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            return response()->json(responseFormatter([
                'response_code' => 'ticket_closed_400',
                'message' => translate('Cannot reply to a closed ticket'),
            ]), 400);
        }

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('support-attachments', $fileName, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'url' => Storage::url($path),
                ];
            }
        }

        $message = SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $driver->id,
            'message' => $request->message,
            'attachments' => count($attachments) > 0 ? $attachments : null,
            'is_admin_reply' => false,
        ]);

        // Update ticket status if needed
        if ($ticket->status === SupportTicket::STATUS_RESOLVED) {
            $ticket->update(['status' => SupportTicket::STATUS_OPEN]);
        }

        return response()->json(responseFormatter([
            'response_code' => 'reply_sent_200',
            'message' => translate('Reply sent successfully'),
            'data' => [
                'message_id' => $message->id,
            ],
        ]));
    }

    /**
     * Rate resolved ticket
     * POST /api/driver/auth/support/tickets/{id}/rate
     */
    public function rateTicket(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        $ticket = SupportTicket::where('driver_id', $driver->id)->find($id);

        if (!$ticket) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        if ($ticket->status !== SupportTicket::STATUS_RESOLVED) {
            return response()->json(responseFormatter([
                'response_code' => 'ticket_not_resolved_400',
                'message' => translate('Can only rate resolved tickets'),
            ]), 400);
        }

        $ticket->rateSupport($request->rating, $request->input('comment'));

        return response()->json(responseFormatter([
            'response_code' => 'rating_submitted_200',
            'message' => translate('Thank you for rating our support'),
        ]));
    }

    /**
     * Submit feedback
     * POST /api/driver/auth/support/feedback
     */
    public function submitFeedback(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:feature_request,bug_report,general_feedback,complaint',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
            'rating' => 'sometimes|integer|min:1|max:5',
            'screen_name' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        $feedback = AppFeedback::create([
            'driver_id' => $driver->id,
            'type' => $request->type,
            'subject' => $request->subject,
            'message' => $request->message,
            'rating' => $request->input('rating'),
            'screen_name' => $request->input('screen_name'),
            'metadata' => [
                'app_version' => $request->header('App-Version'),
                'device_type' => $request->header('Device-Type'),
                'os_version' => $request->header('OS-Version'),
            ],
            'status' => AppFeedback::STATUS_PENDING,
        ]);

        return response()->json(responseFormatter([
            'response_code' => 'feedback_submitted_201',
            'message' => translate('Thank you for your feedback'),
            'data' => ['feedback_id' => $feedback->id],
        ]), 201);
    }

    /**
     * Report an issue
     * POST /api/driver/auth/support/report-issue
     */
    public function reportIssue(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'issue_type' => 'required|in:customer_behavior,app_malfunction,payment_issue,safety_concern,other',
            'description' => 'required|string|min:10',
            'trip_id' => 'sometimes|exists:trip_requests,id',
            'severity' => 'sometimes|in:low,medium,high,critical',
            'attachments' => 'sometimes|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('issue-reports', $fileName, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'url' => Storage::url($path),
                ];
            }
        }

        $report = IssueReport::create([
            'driver_id' => $driver->id,
            'trip_id' => $request->input('trip_id'),
            'issue_type' => $request->issue_type,
            'description' => $request->description,
            'severity' => $request->input('severity', 'medium'),
            'attachments' => count($attachments) > 0 ? $attachments : null,
            'status' => IssueReport::STATUS_REPORTED,
        ]);

        // Notify admins for high/critical issues
        if (in_array($request->input('severity', 'medium'), ['high', 'critical'])) {
            \Modules\AdminModule\Entities\AdminNotification::create([
                'model' => 'issue_report',
                'model_id' => $report->id,
                'message' => 'critical_issue_reported',
            ]);
        }

        return response()->json(responseFormatter([
            'response_code' => 'issue_reported_201',
            'message' => translate('Issue reported successfully. We will investigate and get back to you.'),
            'data' => [
                'report_number' => $report->report_number,
                'report_id' => $report->id,
            ],
        ]), 201);
    }

    /**
     * Get app version and info
     * GET /api/driver/auth/support/app-info
     */
    public function appInfo(): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, [
            'app_name' => config('app.name'),
            'app_version' => '1.0.0', // You can make this dynamic
            'api_version' => '2.0',
            'minimum_supported_version' => '1.0.0',
            'latest_version' => '1.0.0',
            'force_update_required' => false,
            'support_email' => businessConfig('business_support_email')?->value ?? 'support@smartline-it.com',
            'support_phone' => businessConfig('business_support_phone')?->value ?? '+20 xxx xxx xxxx',
            'emergency_number' => '911', // Make this configurable
        ]));
    }
}

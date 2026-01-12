<?php

namespace Modules\UserManagement\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\SupportTicket;

class SupportTicketController extends Controller
{
    /**
     * Get all support tickets (admin view)
     * GET /api/admin/support/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'user_type' => 'sometimes|in:driver,customer',
            'category' => 'sometimes|string',
            'search' => 'sometimes|string|max:100',
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, errorProcessor($validator)), 400);
        }

        $query = SupportTicket::with(['user:id,first_name,last_name,phone,email', 'trip:id,ref_id']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%$search%")
                    ->orWhere('message', 'like', "%$search%")
                    ->orWhere('ticket_number', 'like', "%$search%");
            });
        }

        $total = $query->count();
        $limit = $request->integer('limit', 20);
        $offset = $request->integer('offset', 0);

        $tickets = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function ($ticket) {
                return $this->formatTicket($ticket);
            });

        return response()->json(responseFormatter(DEFAULT_200, [
            'tickets' => $tickets,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]));
    }

    /**
     * Get support ticket details (admin view)
     * GET /api/admin/support/tickets/{id}
     */
    public function show(string $id): JsonResponse
    {
        $ticket = SupportTicket::with(['user:id,first_name,last_name,phone,email', 'trip:id,ref_id,created_at', 'responder:id,first_name,last_name'])
            ->find($id);

        if (!$ticket) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        return response()->json(responseFormatter(DEFAULT_200, $this->formatTicketDetails($ticket)));
    }

    /**
     * Respond to a support ticket (admin)
     * POST /api/admin/support/tickets/{id}/respond
     */
    public function respond(string $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:3000',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, errorProcessor($validator)), 400);
        }

        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $admin = auth('api')->user();

        $ticket->update([
            'admin_response' => $request->message,
            'responded_at' => now(),
            'responded_by' => $admin->id,
        ]);

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, [
            'message' => 'Response sent successfully',
            'ticket_id' => $ticket->id,
        ]));
    }

    /**
     * Update ticket status
     * PATCH /api/admin/support/tickets/{id}/status
     */
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, errorProcessor($validator)), 400);
        }

        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $ticket->update([
            'status' => $request->status,
            'resolved_at' => $request->status === 'resolved' ? now() : $ticket->resolved_at,
            'closed_at' => $request->status === 'closed' ? now() : $ticket->closed_at,
        ]);

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, [
            'message' => 'Ticket status updated',
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
        ]));
    }

    /**
     * Format ticket data for list view
     */
    private function formatTicket($ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'user_type' => $ticket->user_type,
            'user' => $ticket->user ? [
                'id' => $ticket->user->id,
                'name' => $ticket->user->first_name . ' ' . $ticket->user->last_name,
                'phone' => $ticket->user->phone,
                'email' => $ticket->user->email,
            ] : null,
            'has_admin_response' => !is_null($ticket->admin_response),
            'has_driver_reply' => !is_null($ticket->driver_reply),
            'has_rating' => !is_null($ticket->rating),
            'created_at' => $ticket->created_at->toIso8601String(),
            'responded_at' => $ticket->responded_at?->toIso8601String(),
            'replied_at' => $ticket->replied_at?->toIso8601String(),
            'rated_at' => $ticket->rated_at?->toIso8601String(),
        ];
    }

    /**
     * Format ticket details for show view
     */
    private function formatTicketDetails($ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'user_type' => $ticket->user_type,
            'user' => $ticket->user ? [
                'id' => $ticket->user->id,
                'name' => $ticket->user->first_name . ' ' . $ticket->user->last_name,
                'phone' => $ticket->user->phone,
                'email' => $ticket->user->email,
            ] : null,
            'trip' => $ticket->trip ? [
                'id' => $ticket->trip->id,
                'ref_id' => $ticket->trip->ref_id,
                'created_at' => $ticket->trip->created_at->toIso8601String(),
            ] : null,
            'admin_response' => $ticket->admin_response,
            'responded_at' => $ticket->responded_at?->toIso8601String(),
            'responded_by' => $ticket->responder ? [
                'id' => $ticket->responder->id,
                'name' => $ticket->responder->first_name . ' ' . $ticket->responder->last_name,
            ] : null,
            'driver_reply' => $ticket->driver_reply,
            'replied_at' => $ticket->replied_at?->toIso8601String(),
            'rating' => $ticket->rating,
            'rating_feedback' => $ticket->rating_feedback,
            'rated_at' => $ticket->rated_at?->toIso8601String(),
            'created_at' => $ticket->created_at->toIso8601String(),
        ];
    }
}

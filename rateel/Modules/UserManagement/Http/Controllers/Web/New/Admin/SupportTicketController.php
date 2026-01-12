<?php

namespace Modules\UserManagement\Http\Controllers\Web\New\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\UserManagement\Entities\SupportTicket;

class SupportTicketController extends Controller
{
    /**
     * Display list of support tickets
     */
    public function index(Request $request)
    {
        $query = SupportTicket::with(['user:id,first_name,last_name,phone', 'trip:id,ref_id']);

        // Filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority') && $request->priority) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('user_type') && $request->user_type) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%$search%")
                    ->orWhere('message', 'like', "%$search%")
                    ->orWhere('ticket_number', 'like', "%$search%");
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('usermanagement::admin.support.index', [
            'tickets' => $tickets,
            'statuses' => ['open', 'in_progress', 'resolved', 'closed'],
            'priorities' => ['low', 'normal', 'high', 'urgent'],
            'categories' => ['payment', 'trip', 'account', 'other'],
        ]);
    }

    /**
     * Show ticket details
     */
    public function show(string $id)
    {
        $ticket = SupportTicket::with(['user:id,first_name,last_name,phone,email', 'trip:id,ref_id,created_at', 'responder:id,first_name,last_name'])
            ->findOrFail($id);

        return view('usermanagement::admin.support.show', [
            'ticket' => $ticket,
            'statuses' => ['open', 'in_progress', 'resolved', 'closed'],
        ]);
    }

    /**
     * Respond to ticket
     */
    public function respond(string $id, Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:3000',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $admin = auth()->user();

        $ticket->update([
            'admin_response' => $request->message,
            'responded_at' => now(),
            'responded_by' => $admin->id,
        ]);

        return back()->with('success', 'Response sent successfully!');
    }

    /**
     * Update ticket status
     */
    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);

        $ticket->update([
            'status' => $request->status,
            'resolved_at' => $request->status === 'resolved' ? now() : $ticket->resolved_at,
            'closed_at' => $request->status === 'closed' ? now() : $ticket->closed_at,
        ]);

        return back()->with('success', 'Ticket status updated!');
    }
}

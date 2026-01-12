@extends('layouts.admin')

@section('title', 'Support Tickets')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">
                <i class="bi bi-ticket"></i> Support Tickets
            </h1>
        </div>
        <div class="col-md-4 text-end">
            <span class="badge bg-primary">{{ $tickets->total() }} Total</span>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search tickets..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="priority" class="form-select">
                        <option value="">All Priority</option>
                        @foreach($priorities as $priority)
                            <option value="{{ $priority }}" {{ request('priority') == $priority ? 'selected' : '' }}>
                                {{ ucfirst($priority) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="user_type" class="form-select">
                        <option value="">All Users</option>
                        <option value="driver" {{ request('user_type') == 'driver' ? 'selected' : '' }}>Driver</option>
                        <option value="customer" {{ request('user_type') == 'customer' ? 'selected' : '' }}>Customer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tickets Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ticket #</th>
                        <th>Subject</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Replies</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                        <tr>
                            <td>
                                <strong>#{{ $ticket->ticket_number }}</strong>
                            </td>
                            <td>{{ Str::limit($ticket->subject, 40) }}</td>
                            <td>
                                <small>
                                    {{ $ticket->user->first_name }} {{ $ticket->user->last_name }}<br>
                                    <span class="text-muted">{{ $ticket->phone }}</span>
                                </small>
                            </td>
                            <td><span class="badge bg-info">{{ ucfirst($ticket->category) }}</span></td>
                            <td>
                                @php
                                    $priorityColors = [
                                        'low' => 'secondary',
                                        'normal' => 'primary',
                                        'high' => 'warning',
                                        'urgent' => 'danger',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $priorityColors[$ticket->priority] ?? 'primary' }}">
                                    {{ ucfirst($ticket->priority) }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $statusColors = [
                                        'open' => 'danger',
                                        'in_progress' => 'warning',
                                        'resolved' => 'info',
                                        'closed' => 'success',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$ticket->status] ?? 'primary' }}">
                                    {{ str_replace('_', ' ', ucfirst($ticket->status)) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    Admin: {{ $ticket->admin_response ? '✓' : '✗' }}
                                </span><br>
                                <span class="badge bg-light text-dark">
                                    Driver: {{ $ticket->driver_reply ? '✓' : '✗' }}
                                </span>
                            </td>
                            <td><small>{{ $ticket->created_at->format('M d, Y') }}</small></td>
                            <td>
                                <a href="{{ route('admin.support.show', $ticket->id) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <p class="text-muted mb-0">No support tickets found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        {{ $tickets->appends(request()->query())->links() }}
    </div>
</div>
@endsection

@section('styles')
<style>
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }
</style>
@endsection

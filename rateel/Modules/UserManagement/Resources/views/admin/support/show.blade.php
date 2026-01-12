@extends('layouts.admin')

@section('title', 'Ticket #' . $ticket->ticket_number)

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">
                <i class="bi bi-ticket"></i> Ticket #{{ $ticket->ticket_number }}
            </h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admin.support.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-md-8">
            <!-- Ticket Details -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">{{ $ticket->subject }}</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Category:</strong>
                            <span class="badge bg-info">{{ ucfirst($ticket->category) }}</span>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>Priority:</strong>
                            @php
                                $priorityColors = ['low' => 'secondary', 'normal' => 'primary', 'high' => 'warning', 'urgent' => 'danger'];
                            @endphp
                            <span class="badge bg-{{ $priorityColors[$ticket->priority] ?? 'primary' }}">
                                {{ ucfirst($ticket->priority) }}
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <strong>Message:</strong>
                        <p class="border rounded p-3 bg-light">{{ $ticket->message }}</p>
                    </div>

                    <div class="small text-muted">
                        Created: {{ $ticket->created_at->format('M d, Y H:i A') }}
                    </div>
                </div>
            </div>

            <!-- Admin Response -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Admin Response</h5>
                </div>
                <div class="card-body">
                    @if($ticket->admin_response)
                        <div class="alert alert-info mb-3">
                            <strong>Responded:</strong> {{ $ticket->responded_at->format('M d, Y H:i A') }}
                            @if($ticket->responder)
                                <br><strong>By:</strong> {{ $ticket->responder->first_name }} {{ $ticket->responder->last_name }}
                            @endif
                        </div>
                        <p class="border rounded p-3 bg-light">{{ $ticket->admin_response }}</p>
                    @else
                        <p class="text-muted mb-0">No response yet</p>
                    @endif
                </div>
            </div>

            <!-- Driver Reply -->
            @if($ticket->driver_reply)
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Driver's Reply</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success mb-3">
                            <strong>Replied:</strong> {{ $ticket->replied_at->format('M d, Y H:i A') }}
                        </div>
                        <p class="border rounded p-3 bg-light">{{ $ticket->driver_reply }}</p>
                    </div>
                </div>
            @endif

            <!-- Driver Rating -->
            @if($ticket->rating)
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Driver's Rating</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Rating:</strong>
                            <span class="text-warning">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="bi bi-star-fill"></i>
                                @endfor
                            </span>
                            <span>({{ $ticket->rating }}/5)</span>
                        </div>
                        @if($ticket->rating_feedback)
                            <div>
                                <strong>Feedback:</strong>
                                <p class="border rounded p-3 bg-light">{{ $ticket->rating_feedback }}</p>
                            </div>
                        @endif
                        <small class="text-muted">Rated: {{ $ticket->rated_at->format('M d, Y H:i A') }}</small>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Status & Actions -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Status</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.support.status.update', $ticket->id) }}" method="POST" class="mb-3">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="form-select mb-2" required>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" {{ $ticket->status == $status ? 'selected' : '' }}>
                                    {{ str_replace('_', ' ', ucfirst($status)) }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary w-100">Update Status</button>
                    </form>

                    <div class="small text-muted">
                        Current: <span class="badge bg-primary">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</span>
                    </div>
                </div>
            </div>

            <!-- User Info -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Name:</strong>
                        <p>{{ $ticket->user->first_name }} {{ $ticket->user->last_name }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>Type:</strong>
                        <span class="badge bg-info">{{ ucfirst($ticket->user_type) }}</span>
                    </div>
                    <div class="mb-3">
                        <strong>Phone:</strong>
                        <p><a href="tel:{{ $ticket->user->phone }}">{{ $ticket->user->phone }}</a></p>
                    </div>
                    <div>
                        <strong>Email:</strong>
                        <p><a href="mailto:{{ $ticket->user->email }}">{{ $ticket->user->email }}</a></p>
                    </div>
                </div>
            </div>

            <!-- Trip Info -->
            @if($ticket->trip)
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Related Trip</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Trip #:</strong>
                            <p>{{ $ticket->trip->ref_id }}</p>
                        </div>
                        <div>
                            <strong>Date:</strong>
                            <p>{{ $ticket->trip->created_at->format('M d, Y H:i A') }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Response Form -->
    @if(!$ticket->admin_response)
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Send Response</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.support.respond', $ticket->id) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea name="message" id="message" class="form-control" rows="5" required placeholder="Type your response here..."></textarea>
                                <small class="text-muted">Max 3000 characters</small>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-send"></i> Send Response
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

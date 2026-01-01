@extends('adminmodule::layouts.master')

@section('title', translate('Manage Target Users'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fs-22 text-capitalize">{{ translate('Target Users for') }}: <code>{{ $coupon->code }}</code></h2>
                <p class="text-muted mb-0">{{ translate('Only these users can use this coupon') }}</p>
            </div>
            <a href="{{ route('admin.coupon-management.show', $coupon->id) }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> {{ translate('Back to Details') }}
            </a>
        </div>

        <div class="row g-4">
            <!-- Add Users -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Add Users') }}</h5>
                    </div>
                    <div class="card-body">
                        <!-- Search Form -->
                        <form action="{{ route('admin.coupon-management.target-users', $coupon->id) }}" method="GET" class="mb-4">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="{{ translate('Search by name, phone, or email...') }}" value="{{ request('search') }}">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>

                        @if(!empty($searchResults))
                            <form action="{{ route('admin.coupon-management.add-target-users', $coupon->id) }}" method="POST">
                                @csrf
                                <div class="list-group mb-3">
                                    @foreach($searchResults as $user)
                                        <label class="list-group-item list-group-item-action">
                                            <div class="d-flex align-items-center">
                                                <input class="form-check-input me-3" type="checkbox" name="user_ids[]" value="{{ $user->id }}">
                                                <div>
                                                    <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>
                                                    <small class="text-muted d-block">{{ $user->phone }} | {{ $user->email }}</small>
                                                </div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-circle me-2"></i>{{ translate('Add Selected Users') }}
                                </button>
                            </form>
                        @elseif(request('search'))
                            <div class="text-center py-4">
                                <i class="bi bi-search fs-1 text-muted"></i>
                                <p class="text-muted mt-2">{{ translate('No users found matching your search') }}</p>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="bi bi-person-plus fs-1 text-muted"></i>
                                <p class="text-muted mt-2">{{ translate('Search for users to add them to this coupon') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Current Target Users -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Current Target Users') }} ({{ $targetUsers->total() }})</h5>
                    </div>
                    <div class="card-body">
                        @if($targetUsers->isEmpty())
                            <div class="text-center py-5">
                                <i class="bi bi-people fs-1 text-muted"></i>
                                <p class="text-muted mt-2">{{ translate('No target users added yet') }}</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>{{ translate('User') }}</th>
                                            <th>{{ translate('Contact') }}</th>
                                            <th>{{ translate('Added On') }}</th>
                                            <th class="text-center">{{ translate('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($targetUsers as $targetUser)
                                            <tr>
                                                <td>
                                                    <strong>{{ $targetUser->user?->first_name }} {{ $targetUser->user?->last_name }}</strong>
                                                </td>
                                                <td>
                                                    <small>
                                                        {{ $targetUser->user?->phone }}<br>
                                                        {{ $targetUser->user?->email }}
                                                    </small>
                                                </td>
                                                <td>
                                                    {{ $targetUser->created_at?->format('M d, Y') }}
                                                </td>
                                                <td class="text-center">
                                                    <form action="{{ route('admin.coupon-management.remove-target-user', [$coupon->id, $targetUser->user_id]) }}" method="POST" onsubmit="return confirm('{{ translate('Remove this user?') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="d-flex justify-content-end mt-3">
                                {{ $targetUsers->withQueryString()->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

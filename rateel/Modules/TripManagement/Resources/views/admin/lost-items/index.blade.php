@extends('adminmodule::layouts.master')

@section('title', translate('المفقودات'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h4 class="text-capitalize mb-4">{{ translate('المفقودات') }} - {{ translate('Lost & Found') }}</h4>
        </div>

        <!-- Status Filter Tabs -->
        <div class="row mb-4">
            <div class="col-12">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link {{ !request('status') || request('status') == 'all' ? 'active' : '' }}" 
                           href="{{ route('admin.lost-items.index') }}">
                            {{ translate('All') }} <span class="badge bg-secondary">{{ $statusCounts['all'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('status') == 'pending' ? 'active' : '' }}" 
                           href="{{ route('admin.lost-items.index', ['status' => 'pending']) }}">
                            {{ translate('Pending') }} <span class="badge bg-warning">{{ $statusCounts['pending'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('status') == 'found' ? 'active' : '' }}" 
                           href="{{ route('admin.lost-items.index', ['status' => 'found']) }}">
                            {{ translate('Found') }} <span class="badge bg-success">{{ $statusCounts['found'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('status') == 'returned' ? 'active' : '' }}" 
                           href="{{ route('admin.lost-items.index', ['status' => 'returned']) }}">
                            {{ translate('Returned') }} <span class="badge bg-info">{{ $statusCounts['returned'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('status') == 'closed' ? 'active' : '' }}" 
                           href="{{ route('admin.lost-items.index', ['status' => 'closed']) }}">
                            {{ translate('Closed') }} <span class="badge bg-dark">{{ $statusCounts['closed'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('status') == 'no_driver_response' ? 'active' : '' }}" 
                           href="{{ route('admin.lost-items.index', ['status' => 'no_driver_response']) }}">
                            {{ translate('No Response') }} <span class="badge bg-danger">{{ $statusCounts['no_driver_response'] ?? 0 }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                    <form action="{{ url()->current() }}" class="search-form search-form_style-two">
                        <div class="input-group search-form__input_group">
                            <span class="search-form__icon">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" name="search" value="{{ request()->search }}"
                                   class="theme-input-style search-form__input"
                                   placeholder="{{ translate('Search by ID or description') }}">
                        </div>
                        <button type="submit" class="btn btn-primary">{{ translate('search') }}</button>
                    </form>

                    <div class="d-flex flex-wrap gap-3">
                        @can('trip_export')
                            <div class="dropdown">
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="dropdown">
                                    <i class="bi bi-download"></i>
                                    {{ translate('download') }}
                                    <i class="bi bi-caret-down-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                    <li>
                                        <a class="dropdown-item"
                                           href="{{ route('admin.lost-items.export') }}?search={{ request()->get('search') }}&file=excel">{{ translate('excel') }}</a>
                                    </li>
                                </ul>
                            </div>
                        @endcan
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-borderless align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>{{ translate('Report ID') }}</th>
                                <th>{{ translate('Category') }}</th>
                                <th>{{ translate('Customer') }}</th>
                                <th>{{ translate('Driver') }}</th>
                                <th>{{ translate('Trip ID') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Driver Response') }}</th>
                                <th>{{ translate('Date') }}</th>
                                <th>{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lostItems as $key => $item)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>
                                        <a href="{{ route('admin.lost-items.show', $item->id) }}">
                                            {{ Str::limit($item->id, 8) }}...
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary text-capitalize">{{ $item->category }}</span>
                                    </td>
                                    <td>
                                        @if($item->customer)
                                            {{ $item->customer->first_name }} {{ $item->customer->last_name }}
                                            <br><small class="text-muted">{{ $item->customer->phone }}</small>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->driver)
                                            {{ $item->driver->first_name }} {{ $item->driver->last_name }}
                                            <br><small class="text-muted">{{ $item->driver->phone }}</small>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->trip)
                                            <a href="{{ route('admin.trip.show', ['type' => 'all', 'id' => $item->trip_request_id]) }}">
                                                {{ $item->trip->ref_id ?? Str::limit($item->trip_request_id, 8) }}
                                            </a>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'driver_contacted' => 'info',
                                                'found' => 'success',
                                                'returned' => 'primary',
                                                'closed' => 'dark',
                                                'no_driver_response' => 'danger'
                                            ];
                                        @endphp
                                        <span class="badge bg-{{ $statusColors[$item->status] ?? 'secondary' }} text-capitalize">
                                            {{ $item->status }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($item->driver_response)
                                            <span class="badge bg-{{ $item->driver_response == 'found' ? 'success' : 'danger' }} text-capitalize">
                                                {{ $item->driver_response }}
                                            </span>
                                        @else
                                            <span class="text-muted">{{ translate('Awaiting') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->created_at->format('d M Y, h:i A') }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('admin.lost-items.show', $item->id) }}" 
                                               class="btn btn-sm btn-outline-primary" title="{{ translate('View Details') }}">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-inbox fs-1"></i>
                                            <p class="mt-2">{{ translate('No lost item reports found') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($lostItems->count() > 0)
                    <div class="d-flex justify-content-end mt-3">
                        {{ $lostItems->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

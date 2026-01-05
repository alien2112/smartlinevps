@extends('adminmodule::layouts.master')

@section('title', translate('Travel_Requests'))

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h4 class="card-title">{{ translate('Travel_Requests_List') }}</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless" id="table1">
                        <thead>
                            <tr>
                                <th>{{ translate('Trip_ID') }}</th>
                                <th>{{ translate('Customer') }}</th>
                                <th>{{ translate('Date') }}</th>
                                <th>{{ translate('Pickup') }}</th>
                                <th>{{ translate('Dropoff') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingTravels as $trip)
                                <tr>
                                    <td>{{ $trip->ref_id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm me-2">
                                                <img src="{{ onErrorImage(
                                                    $trip->customer?->profile_image,
                                                    asset('storage/app/public/customer/profile') . '/' . $trip->customer?->profile_image,
                                                    asset('public/assets/admin-module/img/user.png'),
                                                    'customer/profile/'
                                                ) }}" alt="user">
                                            </div>
                                            <div>
                                                <h6 class="mb-0 text-capitalize">{{ $trip->customer?->first_name }} {{ $trip->customer?->last_name }}</h6>
                                                <small>{{ $trip->customer?->phone }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ date('d M Y h:i A', strtotime($trip->created_at)) }}</td>
                                    <td title="{{ $trip->coordinate?->pickup_address }}">{{ Str::limit($trip->coordinate?->pickup_address, 30) }}</td>
                                    <td title="{{ $trip->coordinate?->destination_address }}">{{ Str::limit($trip->coordinate?->destination_address, 30) }}</td>
                                    <td>
                                        <span class="badge badge-soft-warning">{{ translate($trip->current_status) }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.travel.show', $trip->id) }}" class="btn btn-primary btn-sm">
                                            <i class="bi bi-eye"></i> {{ translate('View') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">{{ translate('No_Data_Found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection

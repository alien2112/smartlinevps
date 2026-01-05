@extends('adminmodule::layouts.master')

@section('title', translate('Travel_Request_Details'))

@section('content')
    <section class="section">
        <div class="row match-height">
            <!-- Trip Details Column -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ translate('Trip_Details') }} #{{ $trip->ref_id }}</h4>
                    </div>
                    <div class="card-body">
                        <!-- Route Map Placeholder -->
                        <div id="map" style="height: 300px; width: 100%; border-radius: 10px;" class="mb-4 bg-light d-flex align-items-center justify-content-center">
                            <span class="text-muted">{{ translate('Map_View_Loading...') }}</span>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <h6>{{ translate('Pickup_Location') }}</h6>
                                <p>{{ $trip->coordinate?->pickup_address }}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>{{ translate('Dropoff_Location') }}</h6>
                                <p>{{ $trip->coordinate?->destination_address }}</p>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-4">
                                <h6>{{ translate('Estimated_Fare') }}</h6>
                                <p class="text-primary fw-bold">{{ getCurrencyFormat($trip->estimated_fare) }}</p>
                            </div>
                            <div class="col-md-4">
                                <h6>{{ translate('Distance') }}</h6>
                                <p>{{ $trip->estimated_distance }} km</p>
                            </div>
                            <div class="col-md-4">
                                <h6>{{ translate('Duration') }}</h6>
                                <p>{{ $trip->estimated_duration }} min</p>
                            </div>
                        </div>
                        
                        @if($trip->note)
                        <div class="mt-3">
                            <h6>{{ translate('Note') }}</h6>
                            <p class="text-muted">{{ $trip->note }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Action Column -->
            <div class="col-md-4">
                <!-- Customer Info -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ translate('Customer_Info') }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar avatar-md me-3">
                                <img src="{{ onErrorImage(
                                    $trip->customer?->profile_image,
                                    asset('storage/app/public/customer/profile') . '/' . $trip->customer?->profile_image,
                                    asset('public/assets/admin-module/img/user.png'),
                                    'customer/profile/'
                                ) }}" alt="user">
                            </div>
                            <div>
                                <h6 class="mb-0">{{ $trip->customer?->first_name }} {{ $trip->customer?->last_name }}</h6>
                                <small class="text-muted">{{ $trip->customer?->phone }}</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>{{ translate('Total_Trips') }}</span>
                            <span class="fw-bold">{{ $trip->customer?->customerTrips->count() ?? 0 }}</span>
                        </div>
                    </div>
                </div>

                <!-- Driver Assignment -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ translate('Assign_VIP_Driver') }}</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.travel.assign', $trip->id) }}" method="POST" id="assignFactory">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="driver_id" class="form-label">{{ translate('Select_Driver') }}</label>
                                <select name="driver_id" id="driver_id" class="form-select select2">
                                    <option value="" disabled selected>{{ translate('Select_VIP_Driver') }}</option>
                                    @foreach($vipDrivers as $driver)
                                        <option value="{{ $driver->id }}">
                                            {{ $driver->first_name }} {{ $driver->last_name }} 
                                            ({{ $driver->distance_km ?? 'N/A' }} km)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">{{ translate('Assign_Driver') }}</button>
                        </form>
                    </div>
                </div>

                <!-- Cancel Action -->
                <div class="card">
                    <div class="card-body">
                        <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            {{ translate('Cancel_Request') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Cancel_Travel_Request') }}</h5>
                    <button type="button" class="close btn btn-sm btn-light" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.travel.cancel', $trip->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>{{ translate('Reason_for_Cancellation') }}</label>
                            <textarea name="reason" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Close') }}</button>
                        <button type="submit" class="btn btn-danger">{{ translate('Confirm_Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <!-- Map Scripts can be added here -->
    <script>
        $(document).ready(function() {
            $('.select2').select2();
        });
    </script>
@endpush

@extends('adminmodule::layouts.master')

@section('title', translate('Trip Settings'))

@section('content')

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{translate('business_management')}}</h2>
            <div class="col-12 mb-3">
                <div class="">
                    @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
                </div>
            </div>
            <div class="card mb-3 text-capitalize">
                <form action="{{route('admin.business.setup.trip-fare.store')."?type=".TRIP_SETTINGS}}" id="trips_form"
                      method="POST">
                    @csrf

                    <div class="card-header">
                        <h5 class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-fill-gear"></i>
                            {{ translate('trips_settings') }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row gy-3 pt-3 align-items-end">
                            <div class="col-lg-4 col-sm-6">
                                <label class="mb-4 d-flex align-items-center fw-medium gap-2">
                                    {{ translate('add_route_between_pickup_&_destination') }}
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                       data-bs-toggle="tooltip"
                                       title="{{ translate('If Yes, customers can add routes between pickup and destination') }}">
                                    </i>
                                </label>
                                <div class="d-flex align-items-center form-control mb-4">
                                    <div class="flex-grow-1">
                                        <input required type="radio" id="add_intermediate_points1"
                                               name="add_intermediate_points"
                                               value="1" {{($settings->firstWhere('key_name', 'add_intermediate_points')->value?? 0) == 1 ? 'checked' : ''}}>
                                        <label for="add_intermediate_points1" class="media gap-2 align-items-center">
                                            <i class="tio-agenda-view-outlined text-muted"></i>
                                            <span class="media-body">{{ translate('yes') }}</span>
                                        </label>
                                    </div>

                                    <div class="flex-grow-1">
                                        <input required type="radio" id="add_intermediate_points"
                                               name="add_intermediate_points"
                                               value="0" {{($settings->firstWhere('key_name', 'add_intermediate_points')->value?? 0) == 0 ? 'checked' : ''}}>
                                        <label for="add_intermediate_points" class="media gap-2 align-items-center">
                                            <i class="tio-table text-muted"></i>
                                            <span class="media-body">{{ translate('no') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-sm-6">
                                <div class="mb-4 text">
                                    <label for="trip_request_active_time"
                                           class="mb-4">{{ translate('trip_request_active_time_for_customer') }}</label>
                                    <div class="floating-form-group ">
                                        <label for="" class="floating-form-label">
                                            {{ translate('searching_active__time_for_(Min)') }}
                                       </label>
                                        <div class="input-group_tooltip">
                                            <input required type="number" class="form-control" placeholder="Ex: 5"
                                                   id="trip_request_active_time" name="trip_request_active_time"
                                                   value="{{$settings->firstWhere('key_name', 'trip_request_active_time')?->value}}">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                               data-bs-title="{{translate('Customers’ trip requests will be visible to drivers for the time (in minutes) you have set here') . '. '. translate('When the time is over, the requests get removed automatically.')}}"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-sm-6">
                                <label class="mb-4 d-flex align-items-center fw-medium gap-2">
                                    {{ translate('Trip_OTP') }}
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                       data-bs-toggle="tooltip"
                                       title="{{ translate('When this option is enabled, for starting the trip, the driver will need to get an OTP from the customer') }}">
                                    </i>
                                </label>
                                <div class="form-control gap-2 align-items-center d-flex justify-content-between mb-4">
                                    <div class="d-flex align-items-center fw-medium gap-2 text-capitalize">
                                         {{ translate('Driver OTP Confirmation for Trip') }}
                                    </div>
                                    <div class="position-relative">
                                        <label class="switcher">
                                            <input type="checkbox" name="driver_otp_confirmation_for_trip"
                                                   class="switcher_input"
                                                {{ $settings->where('key_name', 'driver_otp_confirmation_for_trip')->first()->value ?? 0 == 1 ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-sm-6">
                                <div class="mb-4 text">
                                    <label for="lost_item_response_timeout_hours"
                                           class="mb-4 d-flex align-items-center fw-medium gap-2">
                                        {{ translate('lost_item_response_timeout') }}
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('lost_item_response_timeout_tooltip') }}">
                                        </i>
                                    </label>
                                    <div class="floating-form-group">
                                        <label for="" class="floating-form-label">
                                            {{ translate('duration_in_hours') }}
                                        </label>
                                        <div class="input-group_tooltip">
                                            <input type="number" class="form-control" placeholder="Ex: 24"
                                                   id="lost_item_response_timeout_hours" name="lost_item_response_timeout_hours"
                                                   value="{{$settings->firstWhere('key_name', 'lost_item_response_timeout_hours')?->value ?? 24}}"
                                                   min="1" max="168">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                               data-bs-title="{{translate('lost_item_response_timeout_description')}}"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-3 flex-wrap justify-content-end">
                            <button type="submit"
                                    class="btn btn-primary text-uppercase">{{ translate('submit') }}</button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Normal Trip Pricing Settings --}}
            <div class="card mb-3 text-capitalize">
                <form action="{{route('admin.business.setup.trip-fare.store')."?type=".TRIP_SETTINGS}}" id="normal_pricing_form" method="POST">
                    @csrf
                    <div class="card-header">
                        <h5 class="d-flex align-items-center gap-2">
                            <i class="bi bi-car-front-fill"></i>
                            {{ translate('Normal Trip Pricing (Override Default Fare Engine)') }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row gy-3 pt-3 align-items-end">
                            {{-- Normal Price Per KM --}}
                            <div class="col-lg-6 col-sm-6">
                                <div class="mb-4">
                                    <label class="mb-4 d-flex align-items-center fw-medium gap-2">
                                        {{ translate('Normal Trip Price Per KM') }}
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('Override the default fare calculation with a simple per-km rate. When enabled, fare = distance × price_per_km (ignoring time-based pricing, base fare, etc.)') }}">
                                        </i>
                                    </label>
                                    <div class="floating-form-group">
                                        <label for="normal_price_per_km_value" class="floating-form-label">
                                            {{ translate('Price per KM') }}
                                        </label>
                                        <div class="input-group_tooltip">
                                            <input type="number" step="0.01" class="form-control" placeholder="Ex: 5.00"
                                                   id="normal_price_per_km_value" name="normal_price_per_km"
                                                   value="{{$settings->firstWhere('key_name', 'normal_price_per_km')?->value['value'] ?? 5.0}}">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                               data-bs-title="{{translate('Example: 5 EGP/km × 10km = 50 EGP total fare. Simpler than default engine but less flexible.')}}"></i>
                                        </div>
                                    </div>
                                    <div class="form-control gap-2 align-items-center d-flex justify-content-between mt-2">
                                        <div class="text-capitalize">{{ translate('Enable Per-KM Pricing') }}</div>
                                        <label class="switcher">
                                            <input type="checkbox" name="normal_price_per_km_status"
                                                   class="switcher_input"
                                                   {{ $settings->where('key_name', 'normal_price_per_km')->first()->value['status'] ?? 0 == 1 ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {{-- Normal Minimum Price --}}
                            <div class="col-lg-6 col-sm-6">
                                <div class="mb-4">
                                    <label class="mb-4 d-flex align-items-center fw-medium gap-2">
                                        {{ translate('Minimum Trip Price') }}
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('Enforce a minimum price for all normal trips. Even if calculated fare is lower, this minimum will be charged.') }}">
                                        </i>
                                    </label>
                                    <div class="floating-form-group">
                                        <label for="normal_min_price_value" class="floating-form-label">
                                            {{ translate('Minimum Price') }}
                                        </label>
                                        <div class="input-group_tooltip">
                                            <input type="number" step="0.01" class="form-control" placeholder="Ex: 20.00"
                                                   id="normal_min_price_value" name="normal_min_price"
                                                   value="{{$settings->firstWhere('key_name', 'normal_min_price')?->value['value'] ?? 20.0}}"
                                                   min="0">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                               data-bs-title="{{translate('Useful for short trips. Example: Set 20 EGP minimum so even a 1km trip costs at least 20 EGP')}}"></i>
                                        </div>
                                    </div>
                                    <div class="form-control gap-2 align-items-center d-flex justify-content-between mt-2">
                                        <div class="text-capitalize">{{ translate('Enforce Minimum Price') }}</div>
                                        <label class="switcher">
                                            <input type="checkbox" name="normal_min_price_status"
                                                   class="switcher_input"
                                                   {{ $settings->where('key_name', 'normal_min_price')->first()->value['status'] ?? 0 == 1 ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div>
                                <strong>{{ translate('Note:') }}</strong>
                                {{ translate('When "Price Per KM" is enabled, it overrides your existing fare calculation engine (base fare, time charges, etc.). If disabled, the system uses your current fare settings. "Minimum Price" works with both methods.') }}
                            </div>
                        </div>

                        <div class="d-flex gap-3 flex-wrap justify-content-end">
                            <button type="submit"
                                    class="btn btn-primary text-uppercase">{{ translate('submit') }}</button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Travel Mode Settings --}}
            <div class="card mb-3 text-capitalize">
                <form action="{{route('admin.business.setup.trip-fare.store')."?type=".TRIP_SETTINGS}}" id="travel_mode_form" method="POST">
                    @csrf
                    <div class="card-header">
                        <h5 class="d-flex align-items-center gap-2">
                            <i class="bi bi-airplane-fill"></i>
                            {{ translate('Travel Mode Settings (VIP Scheduled Rides)') }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row gy-3 pt-3 align-items-end">
                            {{-- Travel Price Per KM --}}
                            <div class="col-lg-4 col-sm-6">
                                <div class="mb-4">
                                    <label class="mb-4 d-flex align-items-center fw-medium gap-2">
                                        {{ translate('Travel Price Per KM') }}
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('Minimum price per kilometer for travel trips. This is the primary pricing method.') }}">
                                        </i>
                                    </label>
                                    <div class="floating-form-group">
                                        <label for="travel_price_per_km_value" class="floating-form-label">
                                            {{ translate('Price per KM') }}
                                        </label>
                                        <div class="input-group_tooltip">
                                            <input type="number" step="0.01" class="form-control" placeholder="Ex: 10.00"
                                                   id="travel_price_per_km_value" name="travel_price_per_km"
                                                   value="{{$settings->firstWhere('key_name', 'travel_price_per_km')?->value['value'] ?? 10.0}}">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                               data-bs-title="{{translate('Example: If you set 10 EGP/km, a 120km trip will have minimum price of 1200 EGP')}}"></i>
                                        </div>
                                    </div>
                                    <div class="form-control gap-2 align-items-center d-flex justify-content-between mt-2">
                                        <div class="text-capitalize">{{ translate('Enable/Disable') }}</div>
                                        <label class="switcher">
                                            <input type="checkbox" name="travel_price_per_km_status"
                                                   class="switcher_input"
                                                   {{ $settings->where('key_name', 'travel_price_per_km')->first()->value['status'] ?? 1 == 1 ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {{-- Travel Price Multiplier (Fallback) --}}
                            <div class="col-lg-4 col-sm-6">
                                <div class="mb-4">
                                    <label class="mb-4 d-flex align-items-center fw-medium gap-2">
                                        {{ translate('Travel Price Multiplier (Fallback)') }}
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('Multiply base fare by this value if per-km pricing is disabled. Example: 1.2 = 20% markup') }}">
                                        </i>
                                    </label>
                                    <div class="floating-form-group">
                                        <label for="travel_price_multiplier_value" class="floating-form-label">
                                            {{ translate('Multiplier (1.0 = no markup)') }}
                                        </label>
                                        <div class="input-group_tooltip">
                                            <input type="number" step="0.01" class="form-control" placeholder="Ex: 1.2"
                                                   id="travel_price_multiplier_value" name="travel_price_multiplier"
                                                   value="{{$settings->firstWhere('key_name', 'travel_price_multiplier')?->value['value'] ?? 1.0}}"
                                                   min="1.0" max="3.0">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                               data-bs-title="{{translate('Only used if per-km pricing is disabled. 1.2 means 20% markup on normal fare')}}"></i>
                                        </div>
                                    </div>
                                    <div class="form-control gap-2 align-items-center d-flex justify-content-between mt-2">
                                        <div class="text-capitalize">{{ translate('Enable/Disable') }}</div>
                                        <label class="switcher">
                                            <input type="checkbox" name="travel_price_multiplier_status"
                                                   class="switcher_input"
                                                   {{ $settings->where('key_name', 'travel_price_multiplier')->first()->value['status'] ?? 0 == 1 ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {{-- Travel Recommended Multiplier --}}
                            <div class="col-lg-4 col-sm-6">
                                <div class="mb-4">
                                    <label class="mb-4 d-flex align-items-center fw-medium gap-2">
                                        {{ translate('Recommended Fare Multiplier') }}
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('Multiply minimum price to calculate recommended fare. Example: 1.2 = 20% above minimum price') }}">
                                        </i>
                                    </label>
                                    <div class="floating-form-group">
                                        <label for="travel_recommended_multiplier_value" class="floating-form-label">
                                            {{ translate('Multiplier (1.0 = same as min)') }}
                                        </label>
                                        <div class="input-group_tooltip">
                                            <input type="number" step="0.01" class="form-control" placeholder="Ex: 1.2"
                                                   id="travel_recommended_multiplier_value" name="travel_recommended_multiplier"
                                                   value="{{$settings->firstWhere('key_name', 'travel_recommended_multiplier')?->value['value'] ?? 1.2}}"
                                                   min="1.0" max="2.0">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                               data-bs-title="{{translate('Example: min_price=100, multiplier=1.2, recommended=120. Higher offers attract drivers faster.')}}"></i>
                                        </div>
                                    </div>
                                    <div class="form-control gap-2 align-items-center d-flex justify-content-between mt-2">
                                        <div class="text-capitalize">{{ translate('Enable/Disable') }}</div>
                                        <label class="switcher">
                                            <input type="checkbox" name="travel_recommended_multiplier_status"
                                                   class="switcher_input"
                                                   {{ $settings->where('key_name', 'travel_recommended_multiplier')->first()->value['status'] ?? 1 == 1 ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row gy-3 pt-3 align-items-end">
                            {{-- Travel Search Radius --}}
                            <div class="col-lg-4 col-sm-6">
                                <div class="mb-4">
                                    <label class="mb-4 d-flex align-items-center fw-medium gap-2">
                                        {{ translate('Travel Search Radius (VIP Drivers)') }}
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('How far (in km) VIP drivers can see travel trip requests') }}">
                                        </i>
                                    </label>
                                    <div class="floating-form-group">
                                        <label for="travel_search_radius_value" class="floating-form-label">
                                            {{ translate('Radius in KM') }}
                                        </label>
                                        <div class="input-group_tooltip">
                                            <input type="number" class="form-control" placeholder="Ex: 50"
                                                   id="travel_search_radius_value" name="travel_search_radius"
                                                   value="{{$settings->firstWhere('key_name', 'travel_search_radius')?->value['value'] ?? 50}}"
                                                   min="10" max="200">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                               data-bs-title="{{translate('Normal trips: 5-10km radius. Travel trips: 30-80km radius recommended')}}"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <div>
                                <strong>{{ translate('How it works:') }}</strong>
                                {{ translate('When "Price Per KM" is enabled, minimum price = distance × price_per_km. Otherwise, minimum price = base_fare × multiplier. Customers can offer higher prices to attract drivers faster.') }}
                            </div>
                        </div>

                        <div class="d-flex gap-3 flex-wrap justify-content-end">
                            <button type="submit"
                                    class="btn btn-primary text-uppercase">{{ translate('submit') }}</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card mb-3 text-capitalize">
                <div class="card-header">
                    <h5 class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-fill-gear"></i>
                        {{ translate('trips_cancellation_messages') }}
                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                           data-bs-toggle="tooltip"
                           title="{{ translate('changes_may_take_some_hours_in_app') }}"></i>
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.business.setup.trip-fare.cancellation_reason.store') }}"
                          method="post">
                        @csrf
                        <div class="row gy-3 pt-3 align-items-start">
                            <div class="col-sm-6 col-md-6">
                                <label for="title" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                    {{ translate('trip_cancellation_reason') }}
                                    <small>({{translate('Max 255 character')}})</small>
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                       data-bs-toggle="tooltip"
                                       title="{{ translate('Driver & Customer cancel trip confirmation reason') }}">
                                    </i>
                                </label>
                                <div class="character-count">
                                    <input id="title" name="title" type="text"
                                           placeholder="{{translate('Ex : vehicle problem')}}"
                                           class="form-control character-count-field"
                                           maxlength="255" data-max-character="255" required>
                                    <span>{{translate('0/255')}}</span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <label for="cancellationType" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                    {{ translate('cancellation_type') }}
                                </label>
                                <select class="js-select" id="cancellationType" name="cancellation_type"
                                        required>
                                    <option value="" disabled
                                            selected>{{translate('select_cancellation_type')}}</option>
                                    @foreach(CANCELLATION_TYPE as $key=> $item)
                                        <option value="{{$key}}">{{translate($item)}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <label for="userType" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                    {{ translate('user_type') }}
                                </label>
                                <select class="js-select" id="userType" name="user_type" required>
                                    <option value="" disabled selected>{{translate('select_user_type')}}</option>
                                    <option value="driver">{{translate('driver')}}</option>
                                    <option value="customer">{{translate('customer')}}</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex gap-3 flex-wrap justify-content-end">
                                    {{--                                    <button class="btn btn-secondary text-uppercase" type="reset">--}}
                                    {{--                                        {{ translate('reset') }}--}}
                                    {{--                                    </button>--}}
                                    <button type="submit"
                                            class="btn btn-primary text-uppercase">{{ translate('submit') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
            <div class="card">
                <div class="card-header border-0 d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    <h5 class="d-flex align-items-center gap-2 m-0">
                        <i class="bi bi-person-fill-gear"></i>
                        {{ translate('trip_cancellation_reason_list') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light align-middle">
                            <tr>
                                <th class="sl">{{translate('SL')}}</th>
                                <th class="text-capitalize">{{translate('Reason')}}</th>
                                <th class="text-capitalize">{{translate('cancellation_type')}}</th>
                                <th class="text-capitalize">{{translate('user_type')}}</th>
                                <th class="text-capitalize">{{translate('Status')}}</th>
                                <th class="text-center action">{{translate('Action')}}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($cancellationReasons as $key => $cancellationReason)
                                <tr>
                                    <td class="sl">{{ $key + $cancellationReasons->firstItem() }}</td>
                                    <td>
                                        {{$cancellationReason->title}}
                                    </td>
                                    <td>
                                        {{ translate(CANCELLATION_TYPE[$cancellationReason->cancellation_type]) }}
                                    </td>
                                    <td>
                                        {{ $cancellationReason->user_type == 'driver' ? translate('driver') : translate('customer') }}
                                    </td>
                                    <td class="text-center">
                                        <label class="switcher mx-auto">
                                            <input class="switcher_input status-change"
                                                   data-url="{{ route('admin.business.setup.trip-fare.cancellation_reason.status') }}"
                                                   id="{{ $cancellationReason->id }}"
                                                   type="checkbox"
                                                   name="status" {{ $cancellationReason->is_active == 1 ? "checked": ""  }} >
                                            <span class="switcher_control"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2 align-items-center">
                                            <button class="btn btn-outline-primary btn-action editData"
                                                    data-id="{{$cancellationReason->id}}">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <button data-id="delete-{{ $cancellationReason?->id }}"
                                                    data-message="{{ translate('want_to_delete_this_cancellation_reason?') }}"
                                                    type="button"
                                                    class="btn btn-outline-danger btn-action form-alert">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                            <form
                                                action="{{ route('admin.business.setup.trip-fare.cancellation_reason.delete', ['id' => $cancellationReason?->id]) }}"
                                                id="delete-{{ $cancellationReason?->id }}" method="post">
                                                @csrf
                                                @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div
                                            class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                            <img
                                                src="{{ asset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}"
                                                alt="" width="100">
                                            <p class="text-center">{{translate('no_data_available')}}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end mt-3">
        {{ $cancellationReasons->links() }}
    </div>

    <div class="modal fade" id="editDataModal">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <!-- End Main Content -->
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";



        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#trips_form, #normal_pricing_form, #travel_mode_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
        $(document).ready(function () {
            $('.editData').click(function () {
                let id = $(this).data('id');
                let url = "{{ route('admin.business.setup.trip-fare.cancellation_reason.edit', ':id') }}";
                url = url.replace(':id', id);
                $.get({
                    url: url,
                    success: function (data) {
                        $('#editDataModal .modal-content').html(data);
                        $('#updateForm').removeClass('d-none');
                        $('#editDataModal').modal('show');
                        $('.character-count-field').on('keyup change', function () {
                            initialCharacterCount($(this));
                        });
                        $('.character-count-field').each(function () {
                            initialCharacterCount($(this));
                        });
                    },
                    error: function (xhr, status, error) {
                        console.log(error);
                    }
                });
            });
        });

    </script>
@endpush

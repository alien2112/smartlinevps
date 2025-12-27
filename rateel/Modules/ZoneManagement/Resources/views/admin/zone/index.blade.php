@extends('adminmodule::layouts.master')

@section('title', translate('Zone_Setup'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            @can('zone_add')
                <div class="d-flex align-items-center gap-3 justify-content-between mb-4">
                    <h2 class="fs-22 text-capitalize">{{ translate('zone_setup') }}</h2>
                </div>
                <form id="zone_form" action="{{ route('admin.zone.store') }}" enctype="multipart/form-data"
                      method="POST">
                    @csrf
                    <div class="mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="row justify-content-between">
                                    <div class="col-lg-5 col-xl-4 mb-5 mb-lg-0">
                                        <h5 class="text-primary mb-4">{{ translate('instructions') }}</h5>
                                        <div class="d-flex flex-column">
                                            <p>{{ translate('create_zone_by_click_on_map_and_connect_the_dots_together') }}</p>

                                            <div class="media mb-2 gap-3 align-items-center">
                                                <img
                                                    src="{{asset('public/assets/admin-module/img/svg/map-drag.svg') }}"
                                                    class="svg"
                                                    alt="">
                                                <div class="media-body ">
                                                    <p>{{ translate('use_this_to_drag_map_to_find_proper_area') }}</p>
                                                </div>
                                            </div>

                                            <div class="media gap-3 align-items-center">
                                                <img
                                                    src="{{asset('public/assets/admin-module/img/svg/map-draw.svg') }}"
                                                    class="svg"
                                                    alt="">
                                                <div class="media-body ">
                                                    <p>{{ translate('click_this_icon_to_start_pin_points_in_the_map_and_connect_them_
                                                        to_draw_a_zone_._Minimum_3_points_required') }}</p>
                                                </div>
                                            </div>
                                            <div class="map-img mt-4">
                                                <img
                                                    src="{{ asset('public/assets/admin-module/img/instructions.gif') }}"
                                                    alt="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-7">
                                        <div class="mb-4">
                                            <label for="zone_name"
                                                   class="form-label text-capitalize ">{{ translate('zone_name') }}
                                                <span class="text-danger">*</span></label>
                                            <input required type="text" class="form-control"
                                                   value="{{old('zone_name') }}" name="name" id="zone_name"
                                                   placeholder="{{ translate('ex') }}: {{ translate('Dhanmondi') }}">
                                        </div>

                                        <div class="form-group mb-3 d-none">
                                            <label class="input-label"
                                                   for="coordinates">{{ translate('coordinates') }}
                                                <span
                                                    class="input-label-secondary">{{ translate('draw_your_zone_on_the_map') }}</span>
                                            </label>
                                            <textarea required type="text" rows="8" name="coordinates"
                                                      id="coordinates" class="form-control" readonly></textarea>
                                        </div>

                                        <!-- Start Map -->
                                        <div class="map-warper overflow-hidden rounded">
                                            <input id="pac-input" class="controls rounded map-search-box"
                                                   title="{{ translate('search_your_location_here') }}"
                                                   type="text"
                                                   placeholder="{{ translate('search_here') }}"/>
                                            <div id="map-canvas" class="map-height"></div>
                                        </div>
                                        <!-- End Map -->
                                    </div>

                                    <div class="d-flex justify-content-end gap-3 mt-3">
                                        <button class="btn btn-primary"
                                                type="submit">{{ translate('submit') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            @endcan
            <div class="col-12">
                <h2 class="fs-22 text-capitalize">{{ translate('zone_list') }}</h2>
                <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-3">
                    <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{!request()->has('status') || request()->get('status')==='all'?'active':''}}"
                               href="{{url()->current()}}?status=all">
                                {{ translate('all') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{request()->get('status')==='active'?'active':''}}"
                               href="{{url()->current()}}?status=active">
                                {{ translate('active') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{request()->get('status')==='inactive'?'active':''}}"
                               href="{{url()->current()}}?status=inactive">
                                {{ translate('inactive') }}
                            </a>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted text-capitalize">{{ translate('total_zones') }}:</span>
                        <span class="text-primary fs-16 fw-bold" id="total_record_count">{{ $zones->total() }}</span>
                    </div>
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade active show" id="all-tab-pane" role="tabpanel">
                        <div class="card overflow-visible">
                            <div class="card-body">
                                <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                                    <form action="javascript:;"
                                          class="search-form search-form_style-two" method="GET">
                                        <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                            <input type="search" class="theme-input-style search-form__input"
                                                   value="{{ request()->get('search') }}" name="search" id="search"
                                                   placeholder="{{ translate('search_here_by_zone_name') }}">
                                        </div>
                                        <button type="submit" class="btn btn-primary search-submit"
                                                data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                                    </form>

                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="dropdown">
                                            <label class="form-check form--check">
                                                <span
                                                    class="form-check-label fw-semibold">{{translate("Apply for All Zone Extra Fare")}} <i
                                                        class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                        data-bs-toggle="tooltip"
                                                        data-bs-title="{{ translate('Allow the option and setup the extra fare will be applicable for all the zones below in the list') }}"></i></span>
                                                <input type="checkbox"
                                                       class="form-check-input {{$allZoneExtraFareSetups->firstWhere('key_name', 'extra_fare_status')?->value == 1 ? 'update-business-setting' : ''}}"
                                                       id="parcelReturnTimeFeeStatus"
                                                       name="extra_fare_status"
                                                       data-name="extra_fare_status"
                                                       data-type="{{ALL_ZONE_EXTRA_FARE}}"
                                                       data-url="{{route('admin.business.setup.update-business-setting')}}"
                                                       data-icon=" {{asset('public/assets/admin-module/img/extra-fare.png')}}"
                                                       data-title="{{translate('Are you Sure to turn off Extra Fare for All Zones') .'?'}}"
                                                       data-sub-title="{{($allZoneExtraFareSetups->firstWhere('key_name', 'extra_fare_status')->value?? 0) == 1 ? translate('Once you turn off this option, Customers will not be required to pay any Extra Fares.') : ""}}"
                                                       data-confirm-btn="{{($allZoneExtraFareSetups->firstWhere('key_name', 'extra_fare_status')->value?? 0) == 1 ? translate('Turn Off') : ""}}"
                                                    {{$allZoneExtraFareSetups->firstWhere('key_name', 'extra_fare_status')?->value == 1 ? "checked" : 'disabled'}}
                                                >
                                            </label>
                                            <div class="dropdown-menu edit-fare-dropdown">
                                                <div class="mb-2">
                                                    <strong>{{translate("Extra Fare")}}: </strong>
                                                    <span>{{$allZoneExtraFareSetups->firstWhere('key_name', 'extra_fare_fee')?->value ?( $allZoneExtraFareSetups->firstWhere('key_name', 'extra_fare_fee')?->value. '%' ): "N/A" }}</span>
                                                </div>
                                                <div>
                                                    <strong>{{translate("Reasons")}}: </strong>
                                                </div>
                                                <span
                                                    class="fs-12">{{$allZoneExtraFareSetups->firstWhere('key_name', 'extra_fare_reason')?->value ?? "N/A"}}</span>
                                                <div class="d-flex justify-content-end">
                                                    <button class="btn btn-primary btn-sm py-1 px-3" type="button"
                                                            id="allZoneExtraFareSetup">{{translate("Edit")}}</button>
                                                </div>
                                            </div>
                                        </div>
                                        @can('super-admin')
                                            <a href="{{ route('admin.zone.index') }}"
                                               class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                                               data-bs-title="{{ translate('refresh') }}">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </a>

                                            <a href="{{ route('admin.zone.trashed') }}"
                                               class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                                               data-bs-title="{{ translate('manage_Trashed_Data') }}">
                                                <i class="bi bi-recycle"></i>
                                            </a>
                                        @endcan
                                        @can('zone_log')
                                            <a href="{{ route('admin.zone.log') }}"
                                               class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                                               data-bs-title="{{ translate('view_Log') }}">
                                                <i class="bi bi-clock-fill"></i>
                                            </a>
                                        @endcan

                                        @can('zone_export')
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-outline-primary"
                                                        data-bs-toggle="dropdown">
                                                    <i class="bi bi-download"></i>
                                                    {{ translate('download') }}
                                                    <i class="bi bi-caret-down-fill"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                                    <li><a class="dropdown-item"
                                                           href="{{route('admin.zone.export') }}?status={{request()->get('status') ?? "all"}}&&file=excel">{{ translate('excel') }}</a>
                                                    </li>
                                                </ul>
                                            </div>
                                        @endcan

                                    </div>
                                </div>

                                <div class="table-responsive mt-3">
                                    <table class="table table-borderless align-middle">
                                        <thead class="table-light align-middle">
                                        <tr>
                                            <th>{{ translate('SL') }}</th>
                                            <th class="text-capitalize name">{{ translate('zone_name') }}</th>
                                            <th class="text-center text-capitalize trip-request-volume">{{ translate('trip_request_volume') }}</th>
                                            <th class="text-center">{{ translate('Extra Fare Status') }}</th>
                                            <th class="text-center">
                                                {{ translate('Extra Fare') }} (%)
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-title="{{ translate('This percentage rate is applicable to zones with the extra fare feature enabled') }}"></i>
                                            </th>
                                            @can('zone_edit')
                                                <th class="status text-center">{{ translate('status') }}</th>
                                            @endcan
                                            <th class="text-center action">{{ translate('action') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @php($volumePercentage = 0)
                                        @forelse ($zones as $key => $zone)
                                            <tr id="hide-row-{{$zone->id}}" class="record-row">
                                                <td>{{ $zones->firstItem() + $key }}</td>
                                                <td class="name">{{ $zone->name }}</td>
                                                @php($volumePercentage = ($zone->tripRequest_count > 0) ? ($tripsCount/$zone->tripRequest_count) * 100 : 0)
                                                <td class="text-center total-vehicle">{{$volumePercentage < 33.33 ? translate('low') : ($volumePercentage == 66.66 ? translate('medium') : translate('high'))}}</td>
                                                <td>
                                                    <label class="switcher mx-auto">
                                                        <input
                                                            class="switcher_input {{$zone->extra_fare_status ==1 ?'update-extra-fare-setting':'extra-fare-setup'}}"
                                                            type="checkbox"
                                                            id="{{$zone->id}}"
                                                            data-name="{{$zone->name}}"
                                                            data-extra-fare-status="{{$zone->extra_fare_status == 1 ? 1:0}}"
                                                            data-extra-fare-fee="{{$zone->extra_fare_fee}}"
                                                            data-extra-fare-reason="{{$zone->extra_fare_reason}}"
                                                            data-url="{{route('admin.zone.extra-fare.status')}}"
                                                            data-icon=" {{asset('public/assets/admin-module/img/extra-fare.png')}}"
                                                            data-title="{{translate('Are you Sure to turn off Extra Fare for this Zones').' - '.$zone->name .'?'}}"
                                                            data-sub-title="{{$zone->extra_fare_status ? (translate('Once you turn off this option, Customers will not be required to pay any Extra Fares in this Zone')." - ".$zone->name) : ""}}"
                                                            data-confirm-btn="{{$zone->extra_fare_status ? translate('Turn Off') : ""}}"
                                                            {{$zone->extra_fare_status?'checked':''}}>
                                                        <span class="switcher_control"></span>
                                                    </label>
                                                </td>
                                                <td class="name text-center">{{ $zone->extra_fare_fee }}%</td>
                                                @can('zone_edit')
                                                    <td class="status">
                                                        <label class="switcher mx-auto">
                                                            <input class="switcher_input status-change"
                                                                   data-url={{ route('admin.zone.status') }} id="{{ $zone->id }}"
                                                                   type="checkbox" {{$zone->is_active?'checked':''}}>
                                                            <span class="switcher_control"></span>
                                                        </label>
                                                    </td>
                                                @endcan
                                                <td class="action">
                                                    <div class="d-flex justify-content-center gap-2 align-items-center">
                                                        @can('zone_log')
                                                            <a href="{{route('admin.zone.log') }}?id={{$zone->id}}"
                                                               class="btn btn-outline-primary btn-action">
                                                                <i class="bi bi-clock"></i>
                                                            </a>
                                                        @endcan
                                                        <div class="dropdown">
                                                            <a href=""
                                                               class="btn btn-outline-info focus-bg-transparent btn-action"
                                                               data-bs-toggle="dropdown">
                                                                <i class="bi bi-three-dots-vertical"></i>
                                                            </a>
                                                            <ul class="dropdown-menu zone-action-dropdown">
                                                                @can('zone_edit')
                                                                    <li>
                                                                        <a href="{{ route('admin.zone.edit', ['id'=>$zone->id]) }}"
                                                                           class="dropdown-item">
                                                                            <i class="bi bi-pencil-fill"></i> {{translate("Zone Edit")}}
                                                                        </a>
                                                                    </li>
                                                                @endcan
                                                                <li>
                                                                    <a href="{{ route('admin.zone.extra-fare.edit', ['id'=>$zone->id]) }}"
                                                                       class="dropdown-item">
                                                                        <i class="bi bi-gear-fill"></i> {{translate("Extra Fare Setup")}}
                                                                    </a>
                                                                </li>
                                                                @can('zone_delete')
                                                                    <li>
                                                                        <button
                                                                            data-id="delete-{{ $zone->id }}"
                                                                            data-message="{{ translate('want_to_delete_this_zone?') }}"
                                                                            type="button"
                                                                            class="dropdown-item form-alert">
                                                                            <i class="bi bi-trash3"></i> {{translate("Delete Zone")}}
                                                                        </button>
                                                                        <form
                                                                            action="{{ route('admin.zone.delete', ['id'=>$zone->id]) }}"
                                                                            id="delete-{{ $zone->id }}" method="post">
                                                                            @csrf
                                                                            @method('delete')
                                                                        </form>
                                                                    </li>
                                                                @endcan
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7">
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
                                <div class="d-flex justify-content-end">
                                    {{$zones->links()}}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal --}}
        <div class="modal fade" id="allZoneExtraFareSetupModal" aria-label="true">
            <div class="modal-dialog modal-xl extra-fare-setup-modal">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-0">
                        <form action="{{ route('admin.zone.extra-fare.store-all-zone') }}" enctype="multipart/form-data"
                              method="POST">
                            @csrf
                            <div class="bg-F6F6F6 rounded">
                                <div class="d-flex align-items-center border-bottom border-e2e2e2 p-3 p-sm-4">
                                    <div class="w-0 flex-grow-1">
                                        <h4 class="mb-2">{{translate("Extra Fare Setup - All Zone")}}</h4>
                                        <div class="fs-12">
                                            {{translate("Enabling this option will apply the extra fare to all rides and parcels across All Zones when the weather conditions change or heavy traffic.")}}
                                        </div>
                                    </div>
                                    <label class="switcher">
                                        <input class="switcher_input" name="all_zone_extra_fare_status"
                                               type="checkbox" {{$allZoneExtraFareSetups->firstWhere('key_name', 'extra_fare_status')?->value == 1 ? "checked" :""}}>
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                                <div class="p-3 p-sm-4">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div>
                                                <label class="form-label">{{translate("Extra Fare")}} (%) <i
                                                        class="bi bi-info-circle-fill text-primary tooltip-icon"
                                                        data-bs-toggle="tooltip"
                                                        data-bs-title="{{translate("Set the percentage of extra fare to be added to the total fare")}}"></i></label>
                                                <input type="number" max="100" min="0" step="{{stepValue()}}"
                                                       class="form-control" name="all_zone_extra_fare_fee"
                                                       value="{{$allZoneExtraFareSetups->firstWhere('key_name', 'extra_fare_fee')?->value}}"
                                                       placeholder="Ex : 100" required>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div>
                                                <label
                                                    class="form-label">{{translate("Reasons for Extra Fare")}}</label>
                                                <input type="text" class="form-control"
                                                       name="all_zone_extra_fare_reason"
                                                       value="{{$allZoneExtraFareSetups->firstWhere('key_name', 'extra_fare_reason')?->value}}"
                                                       placeholder="Ex : Heavy Rain" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h5 class="mb-3">{{translate("Instructions")}}</h5>
                                <ol class="instructions-list instruction-info">
                                    <li>{{translate("When Allow  Extra Fare Setup - All Zone, the Extra Fare(%) will be applicable for all the active zones.")}}</li>
                                    <li>
                                        {{translate("If want to set up separately for each zone, then follow the instructions")}}
                                        <ul class="list-lower-alpha mt-1">

                                            <li>{{translate("You will get a Popup for Setting up Extra Fare & Reason.")}}</li>
                                            <li>{{translate("You will get a popup & setup the extra fare with reason.")}}</li>
                                            <li>{{translate("If want to Update, Go to the Zone Settings")}} <span
                                                    class="fw-bold text-primary">{{translate("settings page")}}</span>
                                            </li>
                                        </ul>
                                    </li>
                                </ol>
                                <div class="d-flex justify-content-end gap-3 mt-3 pt-sm-3">
                                    <button class="btn btn-secondary" type="reset"
                                            data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                                    <button class="btn btn-primary" type="submit">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{--Single Zone Modal --}}
        <div class="modal fade" id="zoneExtraFareSetupModal">
            <div class="modal-dialog modal-xl extra-fare-setup-modal">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-0">
                        <form action="{{ route('admin.zone.extra-fare.store') }}" enctype="multipart/form-data"
                              method="POST">
                            @csrf
                            <input type="hidden" name="id" id="zoneId">
                            <div class="bg-F6F6F6 rounded">
                                <div class="d-flex align-items-center border-bottom border-e2e2e2 p-3 p-sm-4">
                                    <div class="w-0 flex-grow-1">
                                        <h4 class="mb-2">{{translate("Extra Fare Setup - ")}} <span
                                                id="zoneName"></span></h4>
                                        <div class="fs-12">
                                            {{translate("Enabling this option will apply the extra fare to all rides and parcels across this specific zone when weather conditions change or there is heavy traffic.")}}
                                        </div>
                                    </div>
                                    <label class="switcher">
                                        <input class="switcher_input" name="extra_fare_status" id="extraFareStatus"
                                               type="checkbox">
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                                <div class="p-3 p-sm-4">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div>
                                                <label class="form-label">{{translate("Extra Fare")}} (%) <i
                                                        class="bi bi-info-circle-fill text-primary tooltip-icon"
                                                        data-bs-toggle="tooltip"
                                                        data-bs-title="{{translate("Set the percentage of extra fare to be added to the total fare")}}"></i></label>
                                                <input type="number" max="100" min="0" step="{{stepValue()}}"
                                                       class="form-control" name="extra_fare_fee"
                                                       id="extraFareFee"
                                                       placeholder="Ex : 100" required>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div>
                                                <label
                                                    class="form-label">{{translate("Reasons for Extra Fare")}} </label>
                                                <input type="text" class="form-control"
                                                       name="extra_fare_reason"
                                                       id="extraFareReason"
                                                       placeholder="Ex : Heavy Rain" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h5 class="mb-3">{{translate("Instructions")}}</h5>
                                <ol class="instructions-list instruction-info">
                                    <li>{{translate("When set up, this zone will have extra fare added to all trip types.")}}</li>
                                    <li>
                                        {{translate("If you want to setup a same extra fee for all zone then follow the following instruction")}}
                                        <ul class="list-lower-alpha mt-1">
                                            <li>{{translate("Go to zone")}} <span
                                                    class="fw-bold text-primary">{{translate("setup page")}}</span> .
                                            </li>
                                            <li>{{translate("Then check the ‘All Zone Extra Fare’ from the zone list.")}}</li>
                                            <li>{{translate("You will get a popup & setup the extra fare with reason.")}}</li>
                                        </ul>
                                    </li>
                                </ol>
                                <div class="d-flex justify-content-end gap-3 mt-3 pt-sm-3">
                                    <button class="btn btn-secondary" type="reset"
                                            data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                                    <button class="btn btn-primary" type="submit">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <!-- Leaflet CSS and JS - OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet Draw Plugin for drawing polygons -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    <!-- Leaflet Control Geocoder for search -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.css" />
    <script src="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.js"></script>
    
    <style>
        #map-canvas { z-index: 1; }
        .leaflet-control-geocoder { width: 300px; }
        .leaflet-control-geocoder-form input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .reset-map-btn { background: #fff; border: 2px solid #fff; border-radius: 3px; box-shadow: 0 2px 6px rgba(0,0,0,.3); cursor: pointer; padding: 2px 8px; font-size: 12px; margin: 10px; }
        .reset-map-btn:hover { background: #f4f4f4; }
    </style>
    
    <script src="{{asset('public/assets/admin-module/js/zone-management/zone/index.js') }}"></script>
    <script>
        "use strict";
        
        // Zone form submit validation
        $('#zone_form').on('submit', function (e) {
            if ($('#coordinates').val() === '') {
                toastr.error('{{ translate('please_define_zone') }}')
                e.preventDefault();
            }
        });
        
        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        let map; // Global Leaflet map
        let drawnItems; // Layer group for drawn polygons
        let lastPolygon = null;
        let existingZonesLayer; // Layer group for existing zones

        function initialize() {
            // Default center (Egypt - Cairo area)
            let defaultLat = 30.0444;
            let defaultLng = 31.2357;

            // Initialize Leaflet map with OpenStreetMap tiles
            map = L.map('map-canvas').setView([defaultLat, defaultLng], 10);

            // Add OpenStreetMap tile layer (FREE - no API key needed)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // Initialize layer groups
            drawnItems = new L.FeatureGroup();
            map.addLayer(drawnItems);
            
            existingZonesLayer = new L.FeatureGroup();
            map.addLayer(existingZonesLayer);

            // Initialize Leaflet Draw control
            let drawControl = new L.Control.Draw({
                position: 'topright',
                draw: {
                    polygon: {
                        allowIntersection: false,
                        drawError: {
                            color: '#e1e100',
                            message: '<strong>Error:</strong> Shape edges cannot cross!'
                        },
                        shapeOptions: {
                            color: '#000000',
                            fillColor: '#000000',
                            fillOpacity: 0.1,
                            weight: 2
                        }
                    },
                    polyline: false,
                    circle: false,
                    rectangle: false,
                    marker: false,
                    circlemarker: false
                },
                edit: {
                    featureGroup: drawnItems,
                    remove: true
                }
            });
            map.addControl(drawControl);

            // Add geocoder search control (uses Nominatim - FREE)
            let geocoder = L.Control.geocoder({
                defaultMarkGeocode: false,
                placeholder: '{{ translate("search_here") }}',
                geocoder: L.Control.Geocoder.nominatim({
                    geocodingQueryParams: {
                        countrycodes: 'eg', // Prioritize Egypt
                        limit: 5
                    }
                })
            }).on('markgeocode', function(e) {
                let bbox = e.geocode.bbox;
                let poly = L.polygon([
                    bbox.getSouthEast(),
                    bbox.getNorthEast(),
                    bbox.getNorthWest(),
                    bbox.getSouthWest()
                ]);
                map.fitBounds(poly.getBounds());
                
                // Add a temporary marker
                L.marker(e.geocode.center).addTo(map)
                    .bindPopup(e.geocode.name)
                    .openPopup();
            }).addTo(map);

            // Handle polygon creation
            map.on(L.Draw.Event.CREATED, function (event) {
                let layer = event.layer;

                // Remove previous polygon if exists
                if (lastPolygon) {
                    drawnItems.removeLayer(lastPolygon);
                }

                drawnItems.addLayer(layer);
                lastPolygon = layer;

                // Convert to coordinate string format expected by backend
                let coords = layer.getLatLngs()[0].map(function(latlng) {
                    return '(' + latlng.lat + ', ' + latlng.lng + ')';
                }).join(',');
                
                $('#coordinates').val(coords);
                auto_grow();
            });

            // Handle polygon edit
            map.on(L.Draw.Event.EDITED, function (event) {
                let layers = event.layers;
                layers.eachLayer(function (layer) {
                    let coords = layer.getLatLngs()[0].map(function(latlng) {
                        return '(' + latlng.lat + ', ' + latlng.lng + ')';
                    }).join(',');
                    $('#coordinates').val(coords);
                });
            });

            // Handle polygon delete
            map.on(L.Draw.Event.DELETED, function (event) {
                $('#coordinates').val('');
                lastPolygon = null;
            });

            // Try HTML5 geolocation
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        map.setView([position.coords.latitude, position.coords.longitude], 12);
                    },
                    function(error) {
                        console.log('Geolocation error:', error.message);
                    }
                );
            }

            // Load existing zones
            set_all_zones();
        }

        function auto_grow() {
            let element = document.getElementById("coordinates");
            if (element) {
                element.style.height = "5px";
                element.style.height = (element.scrollHeight) + "px";
            }
        }

        function set_all_zones() {
            $.get({
                url: '{{route('admin.zone.get-zones',['status'=> request()->get('status')=='active'?'active':(request()->get('status')=='inactive'?'inactive':'all')])}}',
                dataType: 'json',
                success: function (data) {
                    // Clear existing zones layer
                    existingZonesLayer.clearLayers();
                    
                    for (let i = 0; i < data.length; i++) {
                        // Convert Google Maps format to Leaflet format
                        let coords = data[i].map(function(point) {
                            return [point.lat, point.lng];
                        });
                        
                        let polygon = L.polygon(coords, {
                            color: '#FF0000',
                            weight: 2,
                            opacity: 0.8,
                            fillColor: '#FF0000',
                            fillOpacity: 0.1
                        });
                        
                        existingZonesLayer.addLayer(polygon);
                    }
                    
                    // Fit map to show all zones if any exist
                    if (existingZonesLayer.getLayers().length > 0) {
                        map.fitBounds(existingZonesLayer.getBounds(), { padding: [20, 20] });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading zones:', error);
                }
            });
        }

        // Initialize map when DOM is ready
        $(document).ready(function() {
            initialize();
        });

        // Extra fare setup handlers
        $("#allZoneExtraFareSetup").on('click', function () {
            $('#allZoneExtraFareSetupModal').modal('show');
        });

        $(".extra-fare-setup").on('change', function () {
            extraFareSetupAlert(this);
            $("#zoneExtraFareSetupModal").modal('show');
        });

        function extraFareSetupAlert(obj) {
            let zoneId = obj.id;
            $("#zoneId").val('');
            $("#zoneId").val(zoneId);
            let zoneName = $(obj).data('name');
            $("#zoneName").val('');
            $("#zoneName").html(zoneName);
            let extraFareStatus = $(obj).data('extra-fare-status');
            if (extraFareStatus == 0) {
                $('#extraFareStatus').prop('checked', false)
            } else if (extraFareStatus === 1) {
                $('#extraFareStatus').prop('checked', true)
            }
            $("#extraFareStatus").val('');
            $("#extraFareStatus").val(extraFareStatus);
            let extraFareFee = $(obj).data('extra-fare-fee');
            $("#extraFareFee").val('');
            $("#extraFareFee").val(extraFareFee);
            let extraFareReason = $(obj).data('extra-fare-reason');
            $("#extraFareReason").val('');
            $("#extraFareReason").val(extraFareReason);
            let checked = $(obj).prop("checked");
            let status = checked === true ? 1 : 0;

            if (status === 1) {
                $('#' + obj.id + '.extra-fare-setup').prop('checked', false)
            } else if (status === 0) {
                $('#' + obj.id + '.extra-fare-setup').prop('checked', true)
            }
        }
    </script>
@endpush

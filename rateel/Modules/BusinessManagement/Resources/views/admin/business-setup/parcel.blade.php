@extends('adminmodule::layouts.master')

@section('title', translate('Parcel Settings'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('business_management') }}</h2>

            <div class="mb-3">
                <div class="">
                    @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
                </div>
            </div>

            <div class="card mb-3 text-capitalize">
                <form action="{{ route('admin.business.setup.parcel.tracking-store') . '?type=' . PARCEL_SETTINGS }}"
                      id="parcel_form" method="POST">
                    @csrf
                    <div class="collapsible-card-body">
                        <div class="card-header d-flex align-items-center justify-content-between gap-2">
                            <div class="w-0 flex-grow-1">
                                <h5 class="mb-2">{{ translate('Sent Parcel tracking Link to Customer') }}</h5>
                                <div class="fs-12">
                                    {{ translate('Enabling this option will send the parcel tracking link to the customer via SMS when they place any parcel booking') }}
                                </div>
                            </div>
                            <label class="switcher">
                                <input class="switcher_input collapsible-card-switcher update-business-setting"
                                       id="parcelTrackingStatus" type="checkbox" name="parcel_tracking_status"
                                       data-name="parcel_tracking_status" data-type="{{ PARCEL_SETTINGS }}"
                                       data-url="{{ route('admin.business.setup.update-business-setting') }}"
                                       data-icon=" {{ asset('public/assets/admin-module/img/parcel_tracking.png') }}"
                                       data-title="{{ translate('Are you sure?') }}"
                                       data-sub-title="{{ ($settings->firstWhere('key_name', 'parcel_tracking_status')->value ?? 0) == 1 ? translate('Do you want to turn OFF Parcel Tracking Link for customer? When it’s off the customer don’t received any parcel tracking link message.') : translate('Do you want to turn ON Parcel Tracking Link for customer? When turned ON, customers will receive a tracking link once the parcel is confirmed.') }}"
                                       data-confirm-btn="{{ ($settings->firstWhere('key_name', 'parcel_tracking_status')->value ?? 0) == 1 ? translate('Turn Off') : translate('Turn On') }}"
                                    {{ ($settings->firstWhere('key_name', 'parcel_tracking_status')->value ?? 0) == 1 ? 'checked' : '' }}>
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                        <div class="card-body collapsible-card-content">
                            <div>
                                <div class="mb-4">
                                    <h6 class="mb-2">{{ translate('Tracking Link Template Setup') }}</h6>
                                    <div class="fs-12">
                                        {{ translate('Hear you can write a massage body. Customer Name, Parcel ID & Tracking link will generate automatically for each individual parcel.') }}
                                    </div>
                                </div>
                                <div class="row g-4 align-items-end">
                                    <div class="col-md-7">
                                        <div class="bg-F6F6F6 rounded p-4 p-md-30px">
                                            <div class="mb-3">
                                                <label for="trackingLinkTemplate"
                                                       class="form-label">{{ translate('Message') }}</label>
                                                <div class="character-count">
                                                    <textarea class="form-control character-count-field" maxlength="200"
                                                              data-max-character="200" id="trackingLinkTemplate"
                                                              name="parcel_tracking_message" rows="4"
                                                              placeholder="{{ translate('Write your message here') }}">{{ $settings->firstWhere('key_name', 'parcel_tracking_message')->value ?? old('parcel_tracking_message') }}</textarea>
                                                    <span class="text-end">{{ translate('0/200') }}</span>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-3 justify-content-end">
                                                <button
                                                    class="btn btn-secondary h-40px min-w-100px justify-content-center"
                                                    type="reset">{{ translate('Reset') }}</button>
                                                <button
                                                    class="btn btn-primary h-40px min-w-100px justify-content-center">{{ translate('Save') }}</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="order-track-mocup">
                                            <div class="text-center">
                                                <img
                                                    src="{{ asset('/public/assets/admin-module/img/order-track-mocup.png') }}"
                                                    alt="">
                                            </div>
                                            <div class="order-track-mocup-text bg-F6F6F6">
                                                {{ translate('Dear Receiver') }}
                                                <br>
                                                <br>
                                                {{ translate('Parcel ID is') }}
                                                #123456. {{ translate('You can track this parcel from this link') }}
                                                <br>
                                                <a href="" class="text-0177CD">
                                                    {{ url('/') }}/track_order/123456876548
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="card bg-primary-light border-0">
                                            <div class="card-body">
                                                <span class="title-color d-flex align-items-center gap-2">
                                                    <i class="bi bi-lightbulb fs-18"></i>
                                                    <span class="fs-12 lh-base">
                                                        {{ translate('In Message field you can’t change the') }}
                                                        {CustomerName}, {ParcelId} & {TrackingLink}.
                                                        {{ translate('They will automatically generate. You can only edit other text.') }}
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card mb-3 text-capitalize">
                <form action="{{ route('admin.business.setup.parcel.store') . '?type=' . PARCEL_SETTINGS }}"
                      id="parcel_form" method="POST">
                    @csrf
                    <div class="collapsible-card-body">
                        <div class="card-header d-flex align-items-center justify-content-between gap-2">
                            <div class="w-0 flex-grow-1">
                                <h5 class="mb-2">{{ translate('Parcel Return Time & Fee') }}</h5>
                                <div class="fs-12">
                                    {{ translate('When the toggle is turned ON, the parcel return time and fee are activated; when turned OFF, they are deactivated.') }}
                                </div>
                            </div>
                            <label class="switcher">
                                <input class="switcher_input collapsible-card-switcher update-business-setting"
                                       id="parcelReturnTimeFeeStatus" type="checkbox"
                                       name="parcel_return_time_fee_status"
                                       data-name="parcel_return_time_fee_status" data-type="{{ PARCEL_SETTINGS }}"
                                       data-url="{{ route('admin.business.setup.update-business-setting') }}"
                                       data-icon=" {{ asset('public/assets/admin-module/img/parcel_return.png') }}"
                                       data-title="{{ translate('Are you sure?') }}"
                                       data-sub-title="{{ ($settings->firstWhere('key_name', 'parcel_return_time_fee_status')->value ?? 0) == 1 ? translate('Do you want to turn OFF Parcel Return Time & Fee for driver? When it’s off the driver don’t need to pay return fee for delay. ') : translate('Do you want to turn ON Parcel Return Time & Fee for driver? When it’s ON, the driver need to pay parcel return delay fee. ') }}"
                                       data-confirm-btn="{{ ($settings->firstWhere('key_name', 'parcel_return_time_fee_status')->value ?? 0) == 1 ? translate('Turn Off') : translate('Turn On') }}"
                                    {{ ($settings->firstWhere('key_name', 'parcel_return_time_fee_status')->value ?? 0) == 1 ? 'checked' : '' }}>
                                <span class="switcher_control"></span>
                            </label>
                        </div>


                        <div class="card-body collapsible-card-content">
                            <div>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="returnTimeForDriver"
                                               class="form-label">{{ translate('Return Time for Driver') }} <i
                                                class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="{{ translate('Set the maximum time after a parcel delivery is canceled that the driver must return the parcel to the customer.') }}"></i></label>
                                        <div class="input-group input--group">
                                            <input type="number" name="return_time_for_driver" id="returnTimeForDriver"
                                                   step="1" min="1" max="99999999" class="form-control"
                                                   value="{{ $settings->firstWhere('key_name', 'return_time_for_driver')?->value }}"
                                                   placeholder="Ex : 5">
                                            <select class="form-select" name="return_time_type_for_driver">
                                                <option value="day"
                                                    {{ $settings->firstWhere('key_name', 'return_time_type_for_driver')?->value == 'day' ? 'selected' : '' }}>
                                                    {{ translate('Day') }}</option>
                                                <option value="hour"
                                                    {{ $settings->firstWhere('key_name', 'return_time_type_for_driver')?->value == 'hour' ? 'selected' : '' }}>
                                                    {{ translate('Hour') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="returnFeeForDriverTimeExceed"
                                               class="form-label">{{ translate('Return Fee for Driver if Time Exceeds') }}
                                            ({{ getSession('currency_symbol') }}) <i
                                                class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="{{ translate('Set the charge that drivers will pay if they fail to return a canceled parcel within the specified time limit.') }}"></i></label>
                                        <input type="number" name="return_fee_for_driver_time_exceed" min="0"
                                               max="99999999" step="{{ stepValue() }}" id="returnFeeForDriverTimeExceed"
                                               class="form-control" placeholder="Ex : 2.50"
                                               value="{{ $settings->firstWhere('key_name', 'return_fee_for_driver_time_exceed')?->value }}">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit"
                                            class="btn btn-primary text-uppercase">{{ translate('submit') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-2">
                                {{ translate('Parcel weight Unit') }}
                            </h5>
                            <div class="fs-12">
                                {{ translate('Choose the Weight unit from the dropdown list. This selected Unit will be applicable for all weight measurements') }}
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.business.setup.parcel.store-parcel-weight-unit') }}"
                                  method="post">
                                @csrf
                                <div class="input-group input--group">
                                    <label for="" class="form-label">
                                        {{ translate('Set Unit') }}
                                    </label>
                                    <select class="js-select" id="" name="parcel_weight_unit">
                                        @foreach(WEIGHT_UNIT as $key => $value)
                                            <option value="{{ $key }}"
                                                {{ $settings->firstWhere('key_name', 'parcel_weight_unit')?->value == $key ? 'selected' : '' }}>
                                                {{ translate($key) }} ({{ translate($value) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="d-flex gap-3 flex-wrap justify-content-end mt-4">
                                    <button type="submit"
                                            class="btn btn-primary text-uppercase">{{ translate('submit') }}</button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card mb-3">
                        <form action="{{ route('admin.business.setup.parcel.store-maximum-parcel-weight') }}"
                              id="parcel_form" method="POST">
                            @csrf
                            <div class="collapsible-card-body">
                                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                                    <div class="w-0 flex-grow-1">
                                        <h5 class="mb-2">{{ translate('Parcel weight limit') }}</h5>
                                        <div class="fs-12">
                                            {{ translate('If turned ON, customer will notify if their requested weight exceeds the capacity.') }}
                                        </div>
                                    </div>
                                    <a href="javascript:"
                                       class="text-info fw-semibold fs-12 text-nowrap d-flex view-btn">
                                        <span class="text-underline">{{ translate('View') }}</span>
                                        @if(($settings->firstWhere('key_name', 'max_parcel_weight_status')->value ?? 0) == 1 )
                                            <span><i class="tio-arrow-upward"></i> </span>
                                        @else
                                            <span><i class="tio-arrow-downward"></i> </span>
                                        @endif
                                    </a>
                                    <label class="switcher">
                                        <input class="switcher_input collapsible-card-switcher update-business-setting"
                                               id="maxParcelWeightStatus" type="checkbox"
                                               name="max_parcel_weight_status"
                                               data-name="max_parcel_weight_status" data-type="{{ PARCEL_SETTINGS }}"
                                               data-url="{{ route('admin.business.setup.update-business-setting') }}"
                                               data-icon=" {{ asset('public/assets/admin-module/img/parcel_return.png') }}"
                                               data-title="{{ translate('Are you sure?') }}"
                                               data-sub-title="{{ ($settings->firstWhere('key_name', 'max_parcel_weight_status')->value ?? 0) == 1 ? translate('If the toggle is turned OFF, Customer will not notify if their requested weight exceeds the capacity.') : translate('If the toggle is turned ON, Customer will notify if their requested weight exceeds the capacity.') }}"
                                               data-confirm-btn="{{ ($settings->firstWhere('key_name', 'max_parcel_weight_status')->value ?? 0) == 1 ? translate('Turn Off') : translate('Turn On') }}"
                                            {{ ($settings->firstWhere('key_name', 'max_parcel_weight_status')->value ?? 0) == 1 ? 'checked' : '' }}>
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>

                                <div class="card-body collapsible-card-content">
                                    <div>
                                        <div class="row g-3">
                                            <div class="col-sm-12">
                                                <label for=""
                                                       class="form-label">{{ translate('Max Parcel weight Limit') .' ('.$settings->firstWhere('key_name', 'parcel_weight_unit')?->value.')' }}
                                                </label>
                                                <div class="input-group input--group">
                                                    <input type="number" name="max_parcel_weight" id="maxParcelWeight"
                                                           step="0.01" min="0.01" max="99999999"
                                                           class="form-control"
                                                           value="{{ $settings->firstWhere('key_name', 'max_parcel_weight')?->value }}"
                                                           {{ ($settings->firstWhere('key_name', 'max_parcel_weight_status')->value ?? 0) == 1 ? '' : 'disabled' }}
                                                           placeholder="{{translate("Ex: 15 ").$settings->firstWhere('key_name', 'parcel_weight_unit')?->value}}">

                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end mt-4">
                                            <button type="submit"
                                                    class="btn btn-primary text-uppercase" {{ ($settings->firstWhere('key_name', 'max_parcel_weight_status')->value ?? 0) == 1 ? '' : 'disabled' }}>{{ translate('submit') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card mb-3 text-capitalize">
                <div class="card-header">
                    <h5 class="mb-2">
                        <i class="bi bi-person-fill-gear"></i>
                        {{ translate('Parcel cancellation Reason') }}
                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                           title="{{ translate('changes_may_take_some_hours_in_app') }}"></i>
                    </h5>
                    <div class="fs-12">
                        {{ translate('Here you can add the reasons that customer & user will select for cancel parcel') }}
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.business.setup.parcel.cancellation_reason.store') }}" method="post">
                        @csrf
                        <div class="row gy-3 pt-3 align-items-start">
                            <div class="col-sm-6 col-md-6">
                                <label for="title" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                    {{ translate('parcel_cancellation_reason') }}
                                    <small>({{ translate('Max 255 character') }})</small>
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                       data-bs-toggle="tooltip"
                                       title="{{ translate('Include the proper reasons for parcel delivery cancellation') }}">
                                    </i>
                                </label>
                                <div class="character-count">
                                    <input id="title" name="title" type="text"
                                           placeholder="{{ translate('Ex : vehicle problem') }}"
                                           class="form-control character-count-field" maxlength="255"
                                           data-max-character="255" required>
                                    <span class="d-flex justify-content-end">{{ translate('0/255') }}</span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <label for="cancellationType" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                    {{ translate('cancellation_type') }}
                                </label>
                                <select class="js-select" id="cancellationType" name="cancellation_type" required>
                                    <option value="" disabled selected>{{ translate('select_cancellation_type') }}
                                    </option>
                                    @foreach (CANCELLATION_TYPE as $key => $item)
                                        <option value="{{ $key }}">{{ translate($item) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <label for="userType" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                    {{ translate('user_type') }}
                                </label>
                                <select class="js-select" id="userType" name="user_type" required>
                                    <option value="" disabled selected>{{ translate('select_user_type') }}</option>
                                    <option value="driver">{{ translate('driver') }}</option>
                                    <option value="customer">{{ translate('customer') }}</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex gap-3 flex-wrap justify-content-end">
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
                        {{ translate('Parcel Cancellation Reason List') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light align-middle">
                            <tr>
                                <th class="sl">{{ translate('SL') }}</th>
                                <th class="text-capitalize">{{ translate('Reason') }}</th>
                                <th class="text-capitalize">{{ translate('cancellation_type') }}</th>
                                <th class="text-capitalize">{{ translate('user_type') }}</th>
                                <th class="text-capitalize">{{ translate('Status') }}</th>
                                <th class="text-center action">{{ translate('Action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($cancellationReasons as $key => $cancellationReason)
                                <tr>
                                    <td class="sl">{{ $key + $cancellationReasons->firstItem() }}</td>
                                    <td>
                                        {{ $cancellationReason->title }}
                                    </td>
                                    <td>
                                        {{ CANCELLATION_TYPE[$cancellationReason->cancellation_type] }}
                                    </td>
                                    <td>
                                        {{ $cancellationReason->user_type == 'driver' ? translate('driver') : translate('customer') }}
                                        {{ $cancellationReason->status }}
                                    </td>
                                    <td class="text-center">
                                        <label class="switcher mx-auto">
                                            <input class="switcher_input status-change"
                                                   data-url="{{ route('admin.business.setup.parcel.cancellation_reason.status') }}"
                                                   id="{{ $cancellationReason->id }}" type="checkbox" name="status"
                                                {{ $cancellationReason->is_active == 1 ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2 align-items-center">
                                            <button class="btn btn-outline-primary btn-action editData"
                                                    data-id="{{ $cancellationReason->id }}">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <button data-id="delete-{{ $cancellationReason?->id }}"
                                                    data-message="{{ translate('want_to_delete_this_cancellation_reason?') }}"
                                                    type="button" class="btn btn-outline-danger btn-action form-alert">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                            <form
                                                action="{{ route('admin.business.setup.parcel.cancellation_reason.delete', ['id' => $cancellationReason?->id]) }}"
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
                                            <p class="text-center">{{ translate('no_data_available') }}</p>
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
    <!-- End Main Content -->
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
        if ({{ $smsConfiguration }}) {
            let bootstrapModal = new bootstrap.Modal(document.getElementById("smsGatewayWarningModal"));
            bootstrapModal.show();
            let url = "{{ route('admin.business.configuration.third-party.sms-gateway.index') }}"
            $("#smsGatewayWarningModalConfirmBtn").attr('href', url)
        }

        // Collapse card with switcher
        function collapsibleCard(thisInput) {
            let $card = thisInput.closest('.collapsible-card-body');
            let $content = $card.children('.collapsible-card-content');
            if (thisInput.prop('checked')) {
                $content.slideDown();
            } else {
                $content.slideUp();
            }
        }

        $('.collapsible-card-switcher').each(function () {
            collapsibleCard($(this))
        });
        // Collapse card with switcher ends

        // Toggle card with view button
        function toggleCard($viewBtn) {
            const $cardBody = $viewBtn.closest('.collapsible-card-body');
            const $content = $cardBody.find('.collapsible-card-content');
            const $arrow = $viewBtn.find('i');

            $content.slideToggle(300);
            $arrow.toggleClass('tio-arrow-upward tio-arrow-downward');
        }

        $('.view-btn').on('click', function () {
            toggleCard($(this));
        });
        // Toggle card with view button Ends


        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#trips_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
        $(document).ready(function () {
            $('.editData').click(function () {
                let id = $(this).data('id');
                let url = "{{ route('admin.business.setup.parcel.cancellation_reason.edit', ':id') }}";
                url = url.replace(':id', id);
                $.get({
                    url: url,
                    success: function(data) {
                        $('#editDataModal .modal-content').html(data);
                        $('#updateForm').removeClass('d-none');
                        $('#editDataModal').modal('show');
                        $('.character-count-field').on('keyup change', function() {
                            initialCharacterCount($(this));
                        });
                        $('.character-count-field').each(function() {
                            initialCharacterCount($(this));
                        });
                    },
                    error: function(xhr, status, error) {
                        console.log(error);
                    }
                });
            });
        });
    </script>
@endpush

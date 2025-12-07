@extends('adminmodule::layouts.master')

@section('title', translate('referral_earning_setting'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{translate('business_management')}}</h2>

            <div class="mb-3">
                @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
            </div>

            <form action="{{ route('admin.business.setup.referral-earning.store') }}" id="business_form" method="post"
                  enctype="multipart/form-data">
                @csrf
                <div class="d-flex flex-column gap-3">
                    <div class="card">
                        <div class="card-body collapsible-card-body">
                            <div class="p-20 rounded border d-flex align-items-center justify-content-between gap-2">
                                <div class="w-0 flex-grow-1">
                                    <h5 class="mb-2">{{translate('Setup Customer Referral Earning')}}</h5>
                                    <div class="fs-12">
                                        {{ translate("Allow customers to refer your app to friends and family using a unique code and earn rewards.") }}
                                    </div>
                                </div>
                                <label class="switcher">
                                    <input class="switcher_input collapsible-card-switcher" type="checkbox"
                                           name="customer_referral_earning_status" {{ $customerSettings->firstWhere('key_name','referral_earning_status')?->value == 1 ? "checked" : "" }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                            <div class="collapsible-card-content">
                                <div class="pt-4">
                                    <div class="p-20 rounded border">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <h6 class="mb-2">{{translate("Who Share the code")}}</h6>
                                                <div class="fs-12">
                                                    {{translate("Set the reward for the customer who is sharing the code with friends & family to refer the app.")}}
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="p-30 rounded bg-F6F6F6">
                                                    <div>
                                                        <label
                                                            class="form-label">{{translate("Earnings to Each Referral")}}
                                                            ({{session()->get('currency_symbol') ?? '$'}})</label>
                                                        <input type="number" name="customer_share_code_earning"
                                                               step="{{stepValue()}}"
                                                               value="{{ $customerSettings->firstWhere('key_name','share_code_earning')?->value ?? old('customer_share_code_earning') }}"
                                                               class="form-control" placeholder="Ex : 2.50"
                                                               min="0" max="9999999">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-bottom my-3"></div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <h6 class="mb-2">{{translate("Who Use the code")}}</h6>
                                                <div class="fs-12">
                                                    {{translate("Set up the discount that the customer will receive when using the refer code In signup and taking their first ride")}}
                                                </div>
                                            </div>
                                            @php($useCodeEarning = $customerSettings->firstWhere('key_name','use_code_earning')?->value)
                                            <div class="col-md-8">
                                                <div class="p-30 rounded bg-F6F6F6">
                                                    <div class="collapsible-card-body">
                                                        <div
                                                            class="form-control gap-2 align-items-center d-flex justify-content-between">
                                                            <div
                                                                class="d-flex align-items-center fw-medium gap-2 text-capitalize">
                                                                Discount on First Ride
                                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                   data-bs-toggle="tooltip"
                                                                   data-bs-title="{{translate("Allow customers to receive discounts who sign up using a referral code.")}}">
                                                                </i>
                                                            </div>
                                                            <div class="position-relative">
                                                                <label class="switcher">
                                                                    <input type="checkbox"
                                                                           name="customer_first_ride_discount_status"
                                                                           class="switcher_input collapsible-card-switcher" {{$useCodeEarning && $useCodeEarning['first_ride_discount_status'] == 1 ? "checked" : "" }}>
                                                                    <span class="switcher_control"></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="collapsible-card-content">
                                                            <div class="pt-3">
                                                                <div class="row g-3">
                                                                    <div class="col-sm-6">
                                                                        <label for="discount_amount" class="form-label">
                                                                            <span id="discount_amount_label">{{ translate('discount_amount') }}
                                                                            ({{session()->get('currency_symbol') ?? '$'}}
                                                                            )</span>
                                                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                               data-bs-toggle="tooltip"
                                                                               data-bs-title="{{translate("Set the discount value which will be applicable on the total booking amount of ride booking")}}">
                                                                            </i></label>
                                                                        <div class="input-group input--group">
                                                                            <input type="number" name="customer_discount_amount"
                                                                                   class="form-control" id="discount" step="{{stepValue()}}" min="0"
                                                                                   value="{{ $useCodeEarning && $useCodeEarning['discount_amount'] ?$useCodeEarning['discount_amount'] : old('customer_discount_amount') }}"
                                                                                   placeholder="Ex : 5">
                                                                            <select
                                                                                class="form-select currency-type-select"
                                                                                id="amount_type" name="customer_discount_amount_type">
                                                                                <option
                                                                                    value="amount" {{$useCodeEarning && $useCodeEarning['discount_amount_type'] =="amount" ? "selected" : ""}}>{{session()->get('currency_symbol') ?? '$'}}</option>
                                                                                <option
                                                                                    value="percentage" {{$useCodeEarning && $useCodeEarning['discount_amount_type'] =="percentage" ? "selected" : ""}}>
                                                                                    %
                                                                                </option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <label class="form-label">{{translate("Validity")}} <i
                                                                                class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                                data-bs-toggle="tooltip"
                                                                                data-bs-title="{{translate("Set the value of the day, after that day is over can’t get the referral discount.")}}">
                                                                            </i></label>
                                                                        <div class="input-group input--group">
                                                                            <input type="number" min="0" max="9999999"
                                                                                   class="form-control" name="customer_discount_validity"
                                                                                   value="{{$useCodeEarning && array_key_exists('discount_validity',$useCodeEarning) ? $useCodeEarning['discount_validity'] : old("customer_discount_validity")}}"
                                                                                   placeholder="Ex : 5">
                                                                            <select class="form-select"
                                                                                    name="customer_discount_validity_type">
                                                                                <option
                                                                                    value="day" {{$useCodeEarning && $useCodeEarning['discount_validity_type'] == 'day' ? "selected" : "" }}>
                                                                                    Day
                                                                                </option>
                                                                                <option
                                                                                    value="week" {{$useCodeEarning && $useCodeEarning['discount_validity_type'] == 'week' ? "selected" : "" }}>
                                                                                    Week
                                                                                </option>
                                                                                <option
                                                                                    value="month" {{$useCodeEarning && $useCodeEarning['discount_validity_type'] == 'month' ? "selected" : "" }}>
                                                                                    Month
                                                                                </option>
                                                                                <option
                                                                                    value="year" {{$useCodeEarning && $useCodeEarning['discount_validity_type'] == 'year' ? "selected" : "" }}>
                                                                                    Year
                                                                                </option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body collapsible-card-body">
                            <div class="p-20 rounded border d-flex align-items-center justify-content-between gap-2">
                                <div class="w-0 flex-grow-1">
                                    <h5 class="mb-2">{{translate('Setup Driver Referral Earning')}}</h5>
                                    <div class="fs-12">
                                        {{translate("Allow Drivers to refer your app to friends and family using a unique code and earn rewards.")}}
                                    </div>
                                </div>
                                <label class="switcher">
                                    <input class="switcher_input collapsible-card-switcher" type="checkbox"
                                           name="driver_referral_earning_status" {{ $driverSettings->firstWhere('key_name','referral_earning_status')?->value == 1 ? "checked" : "" }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                            <div class="collapsible-card-content">
                                <div class="pt-4">
                                    <div class="p-20 rounded border">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <h6 class="mb-2">{{translate("Who Share the code")}}</h6>
                                                <div class="fs-12">
                                                    {{translate("Set the reward for the driver who is sharing the code with friends & family to refer the app.")}}
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="p-30 rounded bg-F6F6F6">
                                                    <div>
                                                        <label
                                                            class="form-label">{{ translate("Earnings to Each Referral") }}
                                                            ({{session()->get('currency_symbol') ?? '$'}})</label>
                                                        <input type="text" name="driver_share_code_earning" step="{{stepValue()}}"
                                                               value="{{ $driverSettings->firstWhere('key_name','share_code_earning')?->value ?? old('driver_share_code_earning') }}"
                                                               class="form-control" placeholder="Ex : 2.50"
                                                               min="0" max="9999999">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-bottom my-3"></div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <h6 class="mb-2">{{translate("Who Use the code")}}</h6>
                                                <div class="fs-12">
                                                    {{translate("Set up the reward that the driver will receive when using the refer code in signup.")}}
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="p-30 rounded bg-F6F6F6">
                                                    <div>
                                                        <label class="form-label">{{translate("Bonus in Wallet")}}
                                                            ({{session()->get('currency_symbol') ?? '$'}})</label>
                                                        <input type="text" name="driver_use_code_earning" step="{{stepValue()}}"
                                                               value="{{ $driverSettings->firstWhere('key_name','use_code_earning')?->value ?? old('driver_use_code_earning') }}"
                                                               class="form-control" placeholder="Ex : 2.50"
                                                               min="0" max="9999999">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary text-uppercase">{{translate('submit')}}</button>
                </div>
            </form>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')

    <script>
        $(document).ready(function () {

            const amountType = $('#amount_type');
            amountTypeCheck();
            amountType.on('change', function () {
                amountTypeCheck();
            });

            function amountTypeCheck() {
                if (amountType.val() == 'amount') {
                    $("#discount_amount_label").text("{{translate('Discount Amount')}} ({{session()->get('currency_symbol') ?? '$'}})");
                    $("#discount").attr("placeholder", "Ex: 500")
                    $("#discount").attr("max", "999999999")
                } else {
                    $("#discount_amount_label").text("{{translate('Discount Percent ')}}(%)")
                    $("#discount").attr("placeholder", "Ex: 50%")
                    $("#discount").attr("max", "100")
                }
            }


            function collapsibleCard(thisInput) {
                let $card = thisInput.closest('.collapsible-card-body');
                let $content = $card.children('.collapsible-card-content');
                if (thisInput.prop('checked')) {
                    $content.slideDown();
                } else {
                    $content.slideUp();
                }
            }

            $('.collapsible-card-switcher').on('change', function () {
                collapsibleCard($(this))
            });
            $('.collapsible-card-switcher').each(function () {
                collapsibleCard($(this))
            });
        });
    </script>

    <script src="{{ asset('public/assets/admin-module/js/business-management/business-setup/driver.js') }}"></script>

    <script>
        "use strict";
        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#driverReview').on('change', function () {
            let url = '{{route('admin.business.setup.update-business-setting')}}';
            updateBusinessSetting(this, url)
        })

        function updateBusinessSetting(obj, url) {
            if (!permission) {
                toastr.error('{{ translate('you_donot_have_enough_permission_to_update_this_settings') }}');

                let checked = $(obj).prop("checked");
                let status = checked === true ? 1 : 0;
                if (status === 1) {
                    $('#' + obj.id).prop('checked', false)

                } else if (status === 0) {
                    $('#' + obj.id).prop('checked', true)
                }
                return;
            }

            let value = $(obj).prop('checked') === true ? 1 : 0;
            let name = $(obj).attr('name');
            let type = $(obj).data('type');
            let checked = $(obj).prop("checked");
            let status = checked === true ? 1 : 0;


            Swal.fire({
                title: '{{translate('are_you_sure')}}?',
                text: '{{translate('want_to_change_status')}}',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--bs-primary)',
                cancelButtonColor: 'default',
                cancelButtonText: '{{ translate("no")}}',
                confirmButtonText: '{{ translate("yes")}}',
                reverseButtons: true
            }).then((result) => {
                    if (result.value) {
                        $.ajax({
                            url: url,
                            data: {value: value, name: name, type: type},
                            success: function () {
                                toastr.success("{{translate('status_changed_successfully')}}");
                            },
                            error: function () {
                                if (status === 1) {
                                    $('#' + obj.id).prop('checked', false)
                                } else if (status === 0) {
                                    $('#' + obj.id).prop('checked', true)
                                }
                                toastr.error("{{translate('status_change_failed')}}");
                            }
                        });
                    } else {

                        if (status === 1) {
                            $('#' + obj.id).prop('checked', false)
                        } else if (status === 0) {
                            $('#' + obj.id).prop('checked', true)
                        }
                    }
                }
            )
        }
    </script>

    <script>
        $('#loyalty_point_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_donot_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
    </script>
@endpush

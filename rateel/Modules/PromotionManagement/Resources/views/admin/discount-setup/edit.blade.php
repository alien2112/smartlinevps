@section('title', translate('edit_discount'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">

            <form action="{{ route('admin.promotion.discount-setup.update', ['id'=>$discount->id]) }}" method="POST"
                  id="discountForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <h4 class="text-capitalize mb-4">{{ translate('edit_discount') }}</h4>
                <div class="card">
                    <div class="card-body">
                        <h5 class="text-primary fw-medium text-uppercase mb-3">{{ translate('discount_information') }}</h5>
                        <div class="row align-items-start g-4 mb-4">
                            <div class="col-md-6">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="title" class="mb-2">{{ translate('Title') }} <span
                                                class="text-danger">*</span>
                                        </label>
                                        <input type="text" id="title" value="{{$discount->title}}"
                                               maxlength="100"
                                               name="title" class="form-control" placeholder="Ex: 20% discount"
                                               required>
                                    </div>
                                    <div class="col-12">
                                        <label for="shortDescription"
                                               class="mb-2">{{ translate('short_description') }}
                                            <small>({{translate('Max 800 character')}}) <span
                                                    class="text-danger">*</span></small>
                                        </label>
                                        <div class="character-count">
                                                    <textarea id="shortDescription" name="short_description" cols="30"
                                                              rows="4" class="form-control character-count-field"
                                                              maxlength="800" data-max-character="800"
                                                              required>{{$discount->short_description}}</textarea>
                                            <span>{{translate('0/800')}}</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label for="termsConditions"
                                               class="mb-2">{{ translate('Terms & Conditions') }}
                                            <small>({{translate('Max 1500 character')}})</small> <span
                                                class="text-danger">*</span>
                                        </label>
                                        <div class="character-count">
                                                    <textarea id="termsConditions" name="terms_conditions" cols="30"
                                                              rows="5" class="form-control character-count-field"
                                                              maxlength="1500" data-max-character="1500"
                                                              required>{{$discount->terms_conditions}}</textarea>
                                            <span>{{translate('0/1500')}}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div
                                    class="bg-input h-100 rounded d-flex flex-column justify-content-center py-3">
                                    <div
                                        class="d-flex flex-column justify-content-around align-items-center gap-3 mb-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <h5 class="text-capitalize">{{ translate('discount_image') }} <span
                                                    class="text-danger">*</span></h5>
                                        </div>

                                        <div class="d-flex">
                                            <div class="upload-file">
                                                <input type="file" class="upload-file__input" name="image"
                                                       accept=".jpg, .jpeg, .png, .webp">
                                                <span class="edit-btn">
                                                <i class="bi bi-pencil-square text-primary"></i>
                                            </span>
                                                <div class="upload-file__img upload-file__img_banner">
                                                    <img src="{{ onErrorImage(
                                                    $discount?->image,
                                                    asset('storage/app/public/promotion/discount') . '/' . $discount?->image,
                                                    asset('public/assets/admin-module/img/media/banner-upload-file.png'),
                                                    'promotion/discount/',
                                                ) }}"
                                                         alt="">
                                                </div>
                                            </div>
                                        </div>
                                        <p class="opacity-75 mx-auto max-w220 text-center">
                                            {{ translate('File Format - .jpg, .jpeg, .png, .webp. Image Size - Maximum Size 5 MB. Image Ratio - 3:1') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h5 class="text-primary fw-medium text-uppercase mb-3">{{ translate('discount_logics') }}</h5>
                        <div class="row align-items-start">
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="limitPerUser" class="mb-2">
                                        {{ translate('Limit for Same User') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" id="limitPerUser" name="limit_per_user"
                                           value="{{$discount->limit_per_user}}" min="1"
                                           placeholder="{{translate('Ex : 10')}}" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="discountAmount"
                                           class="mb-2"><span
                                            id="discountAmountLabel">{{ translate('Discount Amount') }}</span> <span
                                            class="text-danger">*</span></label>
                                    <div class="position-relative text-center">
                                        <input type="number" id="discountAmount"
                                               value="{{$discount->discount_amount}}"
                                               name="discount_amount" class="form-control" placeholder="Ex: 5"
                                               step="any"
                                               required>
                                        <select class="js-select currency-type-select" id="discountAmountType"
                                                name="discount_amount_type" required>
                                            <option
                                                value="amount" {{ $discount->discount_amount_type == AMOUNT ? 'selected' : '' }}>{{session()->get('currency_symbol') ?? '$'}}</option>
                                            <option
                                                value="percentage" {{ $discount->discount_amount_type == PERCENTAGE ? 'selected' : '' }}>
                                                %
                                            </option>
                                        </select>
                                    </div>

                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <div class="mb-4">
                                    <label for="maxDiscountAmount"
                                           class="mb-2">{{ translate('Max Amount') }}
                                        ({{session()->get('currency_symbol') ?? '$'}})
                                    </label>
                                    <input type="number" id="maxDiscountAmount" name="max_discount_amount"
                                           class="form-control"
                                           placeholder="Ex: 100" step="any" min="1"
                                           value="{{$discount->max_discount_amount}}" {{ $discount->discount_amount_type == AMOUNT ? 'readonly' : '' }} >
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="minTripAmount"
                                           class="mb-2">{{ translate('Min Trip Amount') }}
                                        ({{session()->get('currency_symbol') ?? '$'}}) <span
                                            class="text-danger">*</span>
                                    </label>
                                    <input type="number" id="minTripAmount" name="min_trip_amount"
                                           class="form-control" min="1"
                                           placeholder="Ex: 60" value="{{$discount->min_trip_amount}}"
                                           step="any" required>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="start_date"
                                           class="mb-2">{{ translate('start_date') }} <span class="text-danger">*</span></label>
                                    <input type="date"
                                           value="{{date('Y-m-d',strtotime($discount->start_date))}}"
                                           min="{{date('Y-m-d',strtotime(now()))}}"
                                           id="start_date"
                                           name="start_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="end_date" class="mb-2">{{ translate('end_date') }} <span
                                            class="text-danger">*</span></label>
                                    <input type="date" id="end_date"
                                           value="{{date('Y-m-d',strtotime($discount->end_date))}}"
                                           min="{{date('Y-m-d',strtotime(now()))}}"
                                           name="end_date"
                                           class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <h5 class="text-primary fw-medium text-uppercase mb-3">{{ translate('discount_availability') }}</h5>
                        <div class="row align-items-start">
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="customerLevelDiscountType"
                                           class="mb-2">{{ translate('Select Customer Level') }} <span
                                            class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('customer_level_select_first_otherwise_customer_not_found_in_select_customer_dropdown') }}"></i>
                                    </label>
                                    <select class="js-select-2" id="customerLevelDiscountType"
                                            name="customer_level_discount_type[]" multiple="multiple" required>
                                        <option
                                            value="{{ALL}}" {{ $discount->customer_level_discount_type == ALL ? 'selected' : '' }}>
                                            All
                                        </option>
                                        @foreach($levels as $level)
                                            <option
                                                value="{{$level->id}}" {{ in_array($level->id,$discount->customerLevels->pluck('id')->toArray()) ? 'selected' : '' }}>{{$level->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="customerDiscountType"
                                           class="mb-2">{{ translate('Select Customer') }} <span
                                            class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('customer_show_when_you_select_customer_level') }}"></i>
                                    </label>
                                    <select class="js-select-2" id="customerDiscountType"
                                            data-placeholder="{{translate('select_customer')}}"
                                            name="customer_discount_type[]" multiple="multiple" required>
                                        <option
                                            value="{{ALL}}" {{ $discount->customer_discount_type == ALL ? 'selected' : '' }}>
                                            All
                                        </option>
                                        @foreach($customers as $customer)
                                            <option
                                                value="{{$customer->id}}" {{ in_array($customer->id,$discount->customers->pluck('id')->toArray()) ? 'selected' : '' }}>{{$customer->first_name}} {{$customer->last_name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="moduleDiscountType" class="mb-2">
                                        {{ translate('select_category') }} <span class="text-danger">*</span>
                                    </label>
                                    <select class="js-select-2" id="moduleDiscountType"
                                            name="module_discount_type[]" multiple="multiple" required>
                                        <option
                                            value="{{ALL}}" {{ in_array(ALL,$discount->module_discount_type) ? 'selected' : '' }}>
                                            All
                                        </option>
                                        @foreach($vehicleCategories as $vehicleCategory)
                                            <option
                                                value="{{$vehicleCategory->id}}" {{ in_array($vehicleCategory->id,$discount->vehicleCategories->pluck('id')->toArray()) ? 'selected' : '' }}>{{ $vehicleCategory->name }}</option>
                                        @endforeach
                                        <option
                                            value="{{PARCEL}}" {{ in_array(PARCEL,$discount->module_discount_type) ? 'selected' : '' }}>{{ translate(PARCEL) }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="mb-4">
                                    <label for="zoneDiscountType" class="mb-2">
                                        {{ translate('Select Zone') }} <span class="text-danger">*</span>
                                    </label>
                                    <select class="js-select-2" id="zoneDiscountType"
                                            name="zone_discount_type[]"
                                            multiple="multiple" required>
                                        <option
                                            value="{{ALL}}" {{ $discount->zone_discount_type == ALL ? 'selected' : '' }}>
                                            All
                                        </option>
                                        @foreach($zones as $zone)
                                            <option
                                                value="{{$zone->id}}" {{ in_array($zone->id,$discount->zones->pluck('id')->toArray()) ? 'selected' : '' }}>{{$zone->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-3 mt-3">
                            <button class="btn btn-primary"
                                    type="submit">{{ translate('update') }}</button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script src="{{asset('public/assets/admin-module/js/promotion-management/discount-setup/edit.js')}}"></script>

    <script>
        "use strict";

        $(document).ready(function () {

            const amountType = $('#discountAmountType');
            const maxDiscountAmount = $('#maxDiscountAmount');
            amountType.on('change', function () {
                if (amountType.val() == 'amount') {

                    maxDiscountAmount.attr("readonly", "true");
                    document.getElementById('maxDiscountAmount').setAttribute("title", "{{translate('Max discount amount field not editable for discount amount type Fixed amount')}}");
                    maxDiscountAmount.val(0);

                    $("#discountAmountLabel").text("{{translate('Discount Amount')}} ({{session()->get('currency_symbol') ?? '$'}})");
                    $("#discountAmount").attr("placeholder", "Ex: 500")
                } else {
                    maxDiscountAmount.removeAttr("readonly");
                    maxDiscountAmount.removeAttr("title");
                    $("#discountAmountLabel").text("{{translate('Discount Percent ')}}(%)")
                    $("#discountAmount").attr("placeholder", "Ex: 50%")
                }
            });

        });


        $('#customerLevelDiscountType').on('change', function () {
            let selectElement = document.getElementById('customerDiscountType');
            selectElement.removeAttribute('disabled');
            let selectedValues = $(this).val();
            $.ajax({
                url: '{{route('admin.customer.get-level-wise-customer')}}',
                type: 'GET',
                data: {
                    levels: selectedValues
                },
                success: function (response) {
                    $('#customerDiscountType').empty();
                    if (response.length > 0) {
                        $('#customerDiscountType').append('<option value="{{ALL}}">All</option>');
                        $.each(response, function (index, value) {
                            $('#customerDiscountType').append('<option value="' + value.id + '">' + value.first_name + ' ' + value.last_name + '</option>');
                        });
                    } else {
                        let selectElement = document.getElementById('customerDiscountType');
                        selectElement.setAttribute('disabled', 'disabled');
                    }
                }
            });
        });
    </script>
@endpush

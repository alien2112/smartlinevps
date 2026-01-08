@section('title', 'Banner Setup')

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                        <h5 class="text-primary text-uppercase">{{ translate('edit_banner') }}</h5>
                    </div>

                    <form action="{{ route('admin.promotion.banner-setup.update', ['id' => $banner->id]) }}"
                          id="banner_form"
                          enctype="multipart/form-data" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="banner_title" class="mb-2">{{ translate('banner_title') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="banner_title" name="banner_title"
                                           value="{{ $banner->name }}" placeholder="Ex: 50% Off" required>
                                </div>
                                <div class="mb-4">
                                    <label for="sort_description"
                                           class="mb-2">{{ translate('short_description') }} <span class="text-danger">*</span></label>

                                    <div class="character-count">
                                        <textarea name="short_desc" id="sort_description" placeholder="Type Here..."
                                                  class="form-control character-count-field" cols="30"
                                                  rows="6" maxlength="800" data-max-character="800"
                                                  required>{{ $banner->description }}</textarea>
                                        <span>{{translate('0/800')}}</span>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="banner_type" class="mb-2">{{ translate('banner_type') }} <span class="text-danger">*</span></label>
                                    <select name="banner_type" class="form-control js-select" id="banner_type"
                                            aria-label="{{ translate('banner_type') }}" required onchange="toggleBannerTypeFields()">
                                        <option value="" disabled>{{ translate('select_banner_type') }}</option>
                                        <option value="ad" {{ ($banner->banner_type ?? 'ad') == 'ad' ? 'selected' : '' }}>{{ translate('advertisement') }}</option>
                                        <option value="coupon" {{ ($banner->banner_type ?? '') == 'coupon' ? 'selected' : '' }}>{{ translate('coupon') }}</option>
                                        <option value="discount" {{ ($banner->banner_type ?? '') == 'discount' ? 'selected' : '' }}>{{ translate('discount') }}</option>
                                        <option value="promotion" {{ ($banner->banner_type ?? '') == 'promotion' ? 'selected' : '' }}>{{ translate('promotion') }}</option>
                                    </select>
                                </div>

                                <div class="mb-4" id="redirect_link_container">
                                    <label for="redirect_link" class="mb-2">{{ translate('redirect_link') }} <span class="text-danger" id="redirect_required">*</span></label>
                                    <input type="text" class="form-control" id="redirect_link" name="redirect_link"
                                           value="{{ $banner->redirect_link }}" placeholder="Ex: www.google.com">
                                </div>

                                <div class="mb-4" id="coupon_code_container" style="display: none;">
                                    <label for="coupon_code" class="mb-2">{{ translate('coupon_code') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" value="{{ $banner->coupon_code }}"
                                           id="coupon_code" name="coupon_code" placeholder="Ex: SAVE50">
                                </div>

                                <div class="mb-4" id="discount_code_container" style="display: none;">
                                    <label for="discount_code" class="mb-2">{{ translate('discount_code') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" value="{{ $banner->discount_code }}"
                                           id="discount_code" name="discount_code" placeholder="Ex: DISCOUNT20">
                                </div>

                                <div class="mb-4" id="coupon_id_container" style="display: none;">
                                    <label for="coupon_id" class="mb-2">{{ translate('link_to_coupon') }} ({{ translate('optional') }})</label>
                                    <select name="coupon_id" class="form-control js-select" id="coupon_id">
                                        <option value="">{{ translate('select_coupon') }}</option>
                                        @php
                                            $coupons = \Modules\PromotionManagement\Entities\CouponSetup::where('is_active', 1)->get();
                                        @endphp
                                        @foreach($coupons as $coupon)
                                            <option value="{{ $coupon->id }}" {{ ($banner->coupon_id ?? '') == $coupon->id ? 'selected' : '' }}>
                                                {{ $coupon->name }} ({{ $coupon->coupon_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-4" id="is_promotion_container">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" id="is_promotion"
                                               name="is_promotion" {{ ($banner->is_promotion ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_promotion">
                                            {{ translate('mark_as_promotion') }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex flex-column justify-content-around align-items-center gap-3 mb-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <h5 class="text-capitalize">{{ translate('banner_image') }} <span class="text-danger">*</span>
                                        </h5>
                                    </div>
                                    <div class="d-flex">
                                        <div class="upload-file">
                                            <input type="file" class="upload-file__input" name="banner_image">
                                            <span class="edit-btn">
                                                <i class="bi bi-pencil-square text-primary"></i>
                                            </span>
                                            <div class="upload-file__img upload-file__img_banner">
                                                <img src="{{ onErrorImage(
                                                    $banner?->image,
                                                    asset('storage/app/public/promotion/banner') . '/' . $banner?->image,
                                                    asset('public/assets/admin-module/img/media/banner-upload-file.png'),
                                                    'promotion/banner/',
                                                ) }}"
                                                     alt="">
                                            </div>
                                        </div>
                                    </div>
                                    <p class="opacity-75 mx-auto max-w220">
                                        {{ translate('File Format - .jpg, .jpeg, .png, .webp. Image Size - Maximum Size 5 MB. Image Ratio - 3:1') }}
                                    </p>
                                </div>
                                <div class="mb-4 text-capitalize">
                                    <label for="time_period" class="mb-2">{{ translate('time_period') }} <span class="text-danger">*</span></label>
                                    <select name="time_period" class="js-select" id="time_period"
                                            aria-label="{{ translate('time_period') }}">
                                        <option disabled selected>{{ translate('select_time_period') }}</option>
                                        <option
                                            value="{{ALL_TIME}}" {{ $banner->time_period == ALL_TIME ? 'selected' : '' }}>
                                            {{ translate(ALL_TIME) }}</option>
                                        <option value="period" {{ $banner->time_period == 'period' ? 'selected' : '' }}>
                                            {{ translate('period') }}</option>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label for="target_audience" class="mb-2">{{ translate('target_audience') }} <span class="text-danger">*</span></label>
                                    <select name="target_audience" class="form-control js-select" id="target_audience"
                                            aria-label="{{ translate('target_audience') }}" required>
                                        <option value="" disabled>{{ translate('select_target_audience') }}</option>
                                        <option value="driver" {{ $banner->target_audience == 'driver' ? 'selected' : '' }}>{{ translate('driver') }}</option>
                                        <option value="customer" {{ $banner->target_audience == 'customer' ? 'selected' : '' }}>{{ translate('customer') }}</option>
                                    </select>
                                </div>

                                <div
                                    class="date-pick {{ $banner->start_date && $banner->end_date != null ? 'd-block' : 'd-none' }}">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="mb-4">
                                                <label for="start_date"
                                                       class="mb-2">{{ translate('start_date') }}</label>
                                                <input type="date" name="start_date" id="start_date"
                                                       min="{{date('Y-m-d',strtotime(now()))}}"
                                                       value="{{ $banner->start_date }}" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="mb-4">
                                                <label for="end_date"
                                                       class="mb-2">{{ translate('end_date') }}</label>
                                                <input type="date" name="end_date" id="end_date"
                                                       min="{{date('Y-m-d',strtotime(now()))}}"
                                                       value="{{ $banner->end_date }}" class="form-control">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-3">
                                    <button class="btn btn-primary text-uppercase"
                                            type="submit">{{ translate('submit') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script src="{{ asset('public/assets/admin-module/js/promotion-management/banner-setup/edit.js') }}"></script>
    <script>
        "use strict";

        function toggleBannerTypeFields() {
            var bannerType = document.getElementById('banner_type').value;
            var redirectLinkContainer = document.getElementById('redirect_link_container');
            var couponCodeContainer = document.getElementById('coupon_code_container');
            var discountCodeContainer = document.getElementById('discount_code_container');
            var couponIdContainer = document.getElementById('coupon_id_container');
            var redirectLink = document.getElementById('redirect_link');
            var couponCode = document.getElementById('coupon_code');
            var discountCode = document.getElementById('discount_code');

            // Hide all conditional fields
            redirectLinkContainer.style.display = 'none';
            couponCodeContainer.style.display = 'none';
            discountCodeContainer.style.display = 'none';
            couponIdContainer.style.display = 'none';

            // Remove required attribute from all
            redirectLink.removeAttribute('required');
            couponCode.removeAttribute('required');
            discountCode.removeAttribute('required');

            // Show fields based on banner type
            if (bannerType === 'ad') {
                redirectLinkContainer.style.display = 'block';
                redirectLink.setAttribute('required', 'required');
            } else if (bannerType === 'coupon') {
                couponCodeContainer.style.display = 'block';
                couponIdContainer.style.display = 'block';
                couponCode.setAttribute('required', 'required');
            } else if (bannerType === 'discount') {
                discountCodeContainer.style.display = 'block';
                discountCode.setAttribute('required', 'required');
            } else if (bannerType === 'promotion') {
                redirectLinkContainer.style.display = 'block';
            }
        }

        // Initialize on page load
        $(document).ready(function() {
            toggleBannerTypeFields();
        });

        $('#banner_form').submit(function (e) {
            let timePeriod = $('#time_period').val();
            var bannerType = $('#banner_type').val();

            if (timePeriod === 'period' && $('#start_date').val() === '') {
                toastr.error('{{ translate('please_select_start_date') }}');
                e.preventDefault();
            }

            if (timePeriod === 'period' && $('#end_date').val() === '') {
                toastr.error('{{ translate('please_select_end_date') }}');
                e.preventDefault();
            }

            if (!timePeriod) {
                toastr.error('{{ translate('please_select_time_period') }}');
                e.preventDefault();
            }

            if (!bannerType) {
                toastr.error('{{ translate('please_select_banner_type') }}');
                e.preventDefault();
            }

            // Validate based on banner type
            if (bannerType === 'ad' && !$('#redirect_link').val()) {
                toastr.error('{{ translate('please_enter_redirect_link') }}');
                e.preventDefault();
            }

            if (bannerType === 'coupon' && !$('#coupon_code').val()) {
                toastr.error('{{ translate('please_enter_coupon_code') }}');
                e.preventDefault();
            }

            if (bannerType === 'discount' && !$('#discount_code').val()) {
                toastr.error('{{ translate('please_enter_discount_code') }}');
                e.preventDefault();
            }

        });
    </script>
@endpush

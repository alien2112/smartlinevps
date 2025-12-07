@section('title', translate('edit_vehicle'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3 justify-content-between mb-4">
                <h2 class="fs-22 text-capitalize">{{ translate('edit_vehicle') }}</h2>
            </div>

            <form action="{{ route('admin.vehicle.update', ['id' => $vehicle->id]) }}" enctype="multipart/form-data"
                method="POST">
                @csrf
                @method('PUT')
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="text-primary text-uppercase mb-4">{{ translate('vehicle_information') }}</h5>
                                <div class="row align-items-end">
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4 text-capitalize">
                                            <label for="brand_id" class="mb-2">{{ translate('vehicle_brand') }} <span
                                                    class="text-danger">*</span></label>
                                            <select class="js-select-ajax" name="brand_id" id="brand_id"
                                                onchange="ajax_models('{{ url('/') }}/admin/vehicle/attribute-setup/model/ajax-models/'+this.value)"
                                                required>
                                                @if (isset($vehicle->brand))
                                                    <option value="{{ $vehicle->brand->id }}" selected="selected">
                                                        {{ $vehicle->brand->name }}</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4" id="model-selector">
                                            <label for="model_id" class="mb-2">{{ translate('vehicle_model') }}
                                                <span class="text-danger">*</span></label>
                                            <select class="js-select-ajax theme-input-style w-100 form-control"
                                                name="model_id" id="model_id"
                                                data-placeholder="{{ translate('please_select_vehicle_model') }}" required>
                                                @if (isset($vehicle->model))
                                                    <option value="{{ $vehicle->model->id }}" selected="selected">
                                                        {{ $vehicle->model->name }}</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4" id="model-selector">
                                            <label for="year_id" class="mb-2">{{ translate('vehicle_year') }}
                                                <span class="text-danger">*</span></label>
                                            <select class="theme-input-style w-100 form-control"
                                                    name="year_id"
                                                    id="year_id"
                                                    required>
                                                <option value="">{{ translate('please_select_vehicle_year') }}</option>
                                                @foreach ($years as $year)
                                                    <option value="{{ $year->id }}" {{ $vehicle->vehicleYear && $year->id == $vehicle->vehicleYear->id ? 'selected' : '' }}>
                                                        {{ $year->year }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!--<div class="col-sm-6 col-xl-4">-->
                                    <!--    <div class="mb-4 text-capitalize">-->
                                    <!--        <label for="vehicle_category"-->
                                    <!--            class="mb-2">{{ translate('vehicle_category') }} <span-->
                                    <!--                class="text-danger">*</span></label>-->
                                    <!--        <select class="js-select-ajax" id="vehicle_category" name="category_id"-->
                                    <!--            required>-->
                                    <!--            <option value="0" selected disabled>-->
                                    <!--                {{ translate('select_vehicle_category') }}</option>-->
                                    <!--            @if (isset($vehicle->category))-->
                                    <!--                <option value="{{ $vehicle->category->id }}" selected="selected">-->
                                    <!--                    {{ $vehicle->category->name }}</option>-->
                                    <!--            @endif-->
                                    <!--        </select>-->
                                    <!--    </div>-->
                                    <!--</div>-->
                                    @php
                                        // دعم للحالة القديمة (string) والحالة الجديدة (json array)
                                        $categoryValue = $vehicle->category_id;
                                        $selectedCategories = is_array(json_decode($categoryValue, true)) 
                                            ? json_decode($categoryValue, true)
                                            : [$categoryValue];
                                    @endphp
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4 text-capitalize">
                                            <label for="vehicle_category"
                                                class="mb-2">{{ translate('vehicle_category') }} <span
                                                    class="text-danger">*</span></label>
                                            <select class="form-control" name="category_id[]" id="vehicle_category" multiple required>
                                                @foreach($categories as $category)
                                                    <option value="{{ $category->id }}"
                                                        {{ in_array($category->id, $selectedCategories) ? 'selected' : '' }}>
                                                        {{ $category->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4">
                                            <label for="licence_plate_num"
                                                class="mb-2">{{ translate('licence_plate_number') }} <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" id="licence_plate_number" class="form-control"
                                                name="licence_plate_number" value="{{ $vehicle->licence_plate_number }}"
                                                placeholder="Ex: 3212 " required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4">
                                            <label for="licence_plate_char"
                                                class="mb-2">{{ translate('licence_plate_char') }} <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" id="licence_plate_char" class="form-control"
                                                name="licence_plate_char" value="{{ $vehicle->licence_plate_char }}"
                                                placeholder="Ex: DBVR " required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4">
                                            <label for="licence_expire_date"
                                                class="mb-2">{{ translate('licence_expire_date') }} <span
                                                    class="text-danger">*</span></label>
                                            <input type="date" id="licence_expire_date" name="licence_expire_date"
                                                value="{{ $vehicle->licence_expire_date->format('Y-m-d') }}"
                                                class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4">
                                            <label for="vin_number" class="mb-2">{{ strtoupper(translate('vin')) }}
                                                {{ translate('number') }}
                                            </label>
                                            <input type="text" id="vin_number" class="form-control" name="vin_number"
                                                value="{{ $vehicle->vin_number }}" placeholder="Ex: 1HGBH41JXMN109186">
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4">
                                            <label for="transmission" class="mb-2">{{ translate('transmission') }}
                                            </label>
                                            <input type="text" id="transmission" class="form-control"
                                                value="{{ $vehicle->transmission }}" name="transmission"
                                                placeholder="Ex: AMT">
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4">
                                            <label for="parcel_weight_capacity"
                                                class="mb-2">{{ translate('parcel_weight_capacity') }}
                                                ({{businessConfig(key: 'parcel_weight_unit')?->value ?? 'kg'}})
                                            </label>
                                            <input type="number" maxlength="999999999"
                                                value="{{ $vehicle?->parcel_weight_capacity ?? '' }}"
                                                id="parcel_weight_capacity" class="form-control"
                                                name="parcel_weight_capacity" placeholder="Ex: 10">
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4 text-capitalize">
                                            <label for="fuel_type" class="mb-2">{{ translate('fuel_type') }} <span
                                                    class="text-danger">*</span></label>
                                            <select class="js-select" id="fuel_type" name="fuel_type" required>
                                                @foreach(FUEL_TYPES as $key => $value)
                                                    <option value="{{$key}}" {{ $key == $vehicle->fuel_type ? 'selected' : '' }}>{{ translate($key) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4 text-capitalize">
                                            <label for="ownership" class="mb-2">{{ translate('ownership') }} <span
                                                    class="text-danger">*</span></label>
                                            <select class="js-select" id="ownership" name="ownership" required>
                                                <option value="0" selected disabled>{{ translate('select_owner') }}
                                                </option>
                                                <option value="admin"
                                                    {{ 'admin' == $vehicle->ownership ? 'selected' : '' }}>
                                                    {{ translate('admin') }}</option>
                                                <option value="driver"
                                                    {{ 'driver' == $vehicle->ownership ? 'selected' : '' }}>
                                                    {{ translate('driver') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <div class="mb-4 text-capitalize">
                                            <label for="driver" class="mb-2">{{ translate('driver') }} <span
                                                    class="text-danger">*</span></label>
                                            <select class="js-select" id="driver" name="driver_id" required>
                                                <option value="0" selected disabled>{{ translate('select_driver') }}
                                                </option>
                                                @if (isset($vehicle->driver))
                                                    <option value="{{ $vehicle->driver->id }}" selected="selected">
                                                        {{ $vehicle->driver?->first_name }}
                                                        {{ $vehicle->driver?->last_name }}</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="mb-4 text-primary text-uppercase">{{ translate('Upload Documents') }}</h5>
                                <div class="d-flex flex-wrap gap-3">
                                    <!-- Display Existing Documents -->
                                    @if (!empty($vehicle->documents))
                                        @foreach ($vehicle->documents as $document)
                                            <div class="show-image">
                                                <div class="file__value bg-transparent border border-C5D2D2 remove_outside"
                                                     data-document="{{ $document }}">
                                                    <img class="file__value--icon"
                                                         src="{{ getExtensionIcon($document) }}"
                                                         alt="">
                                                    <div class="file__value--text">{{ $document }}</div>
                                                    <div class="file__value--remove fw-bold"
                                                         data-id="{{$document}}">
                                                        <img
                                                            src="{{ asset('public/assets/admin-module/img/icons/close-circle.svg') }}"
                                                            alt="">
                                                    </div>
                                                    <input type="hidden" name="existing_documents[]"
                                                           value="{{ $document }}">
                                                </div>
                                            </div>

                                        @endforeach
                                    @endif



                                    <!-- Dynamic Container for Uploaded Files -->
                                    <div class="d-flex flex-wrap gap-3" id="selected-files-container1"></div>
                                    <div class="d-flex flex-wrap gap-3" id="input-data"></div>

                                    <!-- Upload New Documents -->
                                    <div class="upload-file file__input" id="file__input">
                                        <input type="file" class="upload-file__input2" multiple/>
                                        <div class="upload-file__img2">
                                            <div
                                                class="upload-box rounded media gap-4 align-items-center px-3 py-2 bg-fcfcfc">
                                                <i class="bi bi-cloud-arrow-up-fill fs-20 opacity-50"></i>
                                                <div class="media-body">
                                                    <p class="text-muted mb-2 fs-12">Upload</p>
                                                    <h6 class="fs-12">File or image</h6>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-3">
                    <button class="btn btn-primary" type="submit">{{ translate('update') }}</button>
                </div>
            </form>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script src="{{ asset('public/assets/admin-module/js/vehicle-management/vehicle/create.js') }}"></script>

    <script>
        "use strict";
        "use strict";

        function ajax_models(route) {
            $.get({
                url: route,
                dataType: 'json',
                data: {},
                beforeSend: function() {},
                success: function(response) {
                    $('#model-selector').html(response.template);
                },
                complete: function() {

                },
            });
        }

        $('#brand_id').select2({
            ajax: {
                url: '{{ route('admin.vehicle.attribute-setup.brand.all-brands', parameters: ['status' => 'active']) }}',
                data: function(params) {
                    return {
                        q: params.term, // search term
                        page: params.page,
                    };
                },
                processResults: function(data) {
                    //
                    return {
                        results: data
                    };
                },
                __port: function(params, success, failure) {
                    var $request = $.ajax(params);
                    $request.then(success);
                    $request.fail(failure);
                    return $request;
                }
            }
        });

        $('#vehicle_category').select2({
            ajax: {
                url: '{{ route('admin.vehicle.attribute-setup.category.all-categories', parameters: ['status' => 'active']) }}',
                data: function(params) {
                    return {
                        q: params.term, // search term
                        page: params.page
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                __port: function(params, success, failure) {
                    let $request = $.ajax(params);
                    $request.then(success);
                    $request.fail(failure);
                    return $request;
                }
            }
        });

        let all_driver = 0;

        $('.js-select-driver').select2({
            ajax: {
                url: '{{ route('admin.driver.get-all-ajax-vehicle') }}',
                data: function(params) {
                    return {
                        search: params.term, // search term
                        all_driver: all_driver,
                        page: params.page
                    };
                },
                processResults: function(data) {

                    return {
                        results: data
                    };
                },
                __port: function(params, success, failure) {
                    var $request = $.ajax(params);
                    $request.then(success);
                    $request.fail(failure);
                    return $request;
                }
            }
        });
    </script>
@endpush

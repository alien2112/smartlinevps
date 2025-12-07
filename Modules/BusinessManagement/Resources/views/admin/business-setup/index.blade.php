@extends('adminmodule::layouts.master')

@section('title', translate('Business_Info'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <form action="{{ route('admin.business.setup.info.store') }}" id="business_form" method="post"
              enctype="multipart/form-data">
            @csrf
            <div class="container-fluid">
                <h2 class="fs-22 mb-4 text-capitalize">{{ translate('business_management') }}</h2>

                <div class="row g-3 pb-4">
                    <div class="col-12">
                        <div class="">
                            @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
                        </div>
                    </div>
                    <div class="col-12">
                        @can('business_edit')
                            <div class="card mb-3">
                                    <?php
                                    $config = businessConfig('maintenance_mode')?->value == 1 ? 1 : 0;
                                    $selectedMaintenanceSystem = businessConfig('maintenance_system_setup')?->value ?? [];
                                    if ($config && count($selectedMaintenanceSystem) > 0) {
                                        $selectedMaintenanceDuration = businessConfig('maintenance_duration_setup')?->value;
                                        $startDate = new DateTime($selectedMaintenanceDuration['start_date']);
                                        $endDate = new DateTime($selectedMaintenanceDuration['end_date']);
                                    }
                                    ?>
                                <div class="card-body p-0">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-4">
                                        <div class="w-0 flex-grow-1">
                                            <div class="d-flex align-items-baseline gap-2">
                                                <h5>
                                                    <img
                                                        src="{{ asset('public/assets/admin-module/img/svg/notification.svg') }}"
                                                        alt="" class="svg">
                                                </h5>
                                                <div>
                                                    <h5 class="fw-semibold text-capitalize mb-2">

                                                        {{ translate('system_maintenance_mode') }}
                                                    </h5>
                                                    <div class="fs-12">
                                                        {{ translate('Use the System Maintenance feature to work on the system and disable user access.') }}
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                        <label class="switcher switcher-lg">
                                            <input data-url="{{ route('admin.business.setup.info.maintenance') }}"
                                                   type="checkbox" id="maintenance-mode-input"
                                                   class="switcher_input toggle-switch-input collapsible-card-switcher {{ $config == 0 ? 'maintenance-mode-show' : 'maintenance-off' }}"
                                                {{ $config ? 'checked' : '' }}
                                                {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}>
                                            <span id="switcher-text" data-state="off"
                                                  class="switcher_control fs-12 fw-semibold text-capitalize"></span>
                                        </label>
                                    </div>
                                    <div class="collapsible-card-content pt-0">
                                        @if ($config && count($selectedMaintenanceSystem) > 0)
                                            <hr class="m-0">
                                            <div class="d-flex flex-wrap gap-3 align-items-center p-4 pb-0">
                                                <p class="mb-0">
                                                    @if ($selectedMaintenanceDuration['maintenance_duration'] == 'until_change')
                                                        {{ translate('Your maintenance mode is activated.') }}
                                                    @else
                                                        {{ translate('Your maintenance mode is activated from') }}
                                                        <span
                                                            class="text-body fw-semibold">{{ $startDate->format('m/d/Y, h:i A') }}</span>
                                                        {{ translate('to') }}
                                                        <span
                                                            class="text-body fw-semibold">{{ $endDate->format('m/d/Y, h:i A') }}</span>
                                                        .
                                                    @endif

                                                </p>
                                                <a class="c1 edit maintenance-mode-show" href="#"><i
                                                        class="tio-edit"></i></a>
                                            </div>
                                        @else
                                            {{-- <p>
                                            *{{ translate('By turning on maintenance mode Control your all system & function') }}
                                        </p> --}}
                                        @endif

                                        @if ($config && count($selectedMaintenanceSystem) > 0)
                                            <div class="d-flex flex-wrap gap-3 mt-3 align-items-center p-4 pt-0">
                                                <h6 class="mb-0">
                                                    {{ translate('Selected Systems') }}
                                                </h6>
                                                <div class="bg-F6F6F6 px-4 py-2 mb-0 rounded">
                                                    <ul class="selected-systems d-flex gap-4 flex-wrap m-0 p-0 ps-3">
                                                        @foreach ($selectedMaintenanceSystem as $system)
                                                            <li>{{ ucwords(str_replace('_', ' ', $system)) }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            </div>
                                        @endif

                                    </div>
                                </div>
                            </div>
                        @endcan
                        <div class="card mb-3">
                            <div class="card-header b-dark">
                                <div class="d-flex align-items-baseline gap-2">
                                    <h5>
                                        <img src="{{ asset('public/assets/admin-module/img/svg/briefcase.svg') }}"
                                             alt="" class="svg">
                                    </h5>
                                    <div>
                                        <h5 class="fw-semibold text-capitalize mb-2">

                                            {{ translate('company_information') }}
                                        </h5>
                                        <div class="fs-12">
                                            {{ translate('Configure Essential Company Details') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            <label for="business_name"
                                                   class="mb-2 form-label">{{ translate('business_name') }}</label>
                                            <input type="text" name="business_name"
                                                   value="{{ $settings->firstWhere('key_name', 'business_name')?->value ?? old('business_name') }}"
                                                   id="business_name"
                                                   class="form-control {{ $settings->firstWhere('key_name', 'business_name')?->value ?? old('business_name') ? 'dark-border' : '' }}"
                                                   placeholder="{{ translate('Ex: ABC Company') }}">
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            <label for="business_contact_num"
                                                   class="mb-2 form-label">{{ translate('business_contact_number') }}</label>
                                            <input type="text" name="business_contact_phone"
                                                   value="{{ $settings->firstWhere('key_name', 'business_contact_phone')?->value ?? old('business_contact_phone') }}"
                                                   id="business_contact_num"
                                                   class="form-control {{ $settings->firstWhere('key_name', 'business_contact_phone')?->value ?? old('business_contact_phone') ? 'dark-border' : '' }}"
                                                   placeholder="{{ translate('Ex: +9XXX-XXX-XXXX') }}">
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            <label for="business_email"
                                                   class="mb-2 form-label">{{ translate('business_contact_email') }}</label>
                                            <input type="email" name="business_contact_email"
                                                   value="{{ $settings->firstWhere('key_name', 'business_contact_email')->value ?? old('business_contact_email') }}"
                                                   id="business_email"
                                                   class="form-control {{ $settings->firstWhere('key_name', 'business_contact_email')?->value ?? old('business_contact_email') ? 'dark-border' : '' }}"
                                                   placeholder="{{ translate('Ex: company@email.com') }}">
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30 character-count">
                                            <label for="business_address"
                                                   class="mb-2 form-label">{{ translate('business_address') }}</label>
                                            <textarea name="business_address" id="business_address" cols="30" rows="5"
                                                      class="form-control character-count-field {{ $settings->firstWhere('key_name', 'business_address')?->value ?? old('business_address') ? 'dark-border' : '' }}"
                                                      placeholder="{{ translate('Type Here ...') }}" maxlength="100" data-max-character="100">{{ $settings->firstWhere('key_name', 'business_address')?->value ?? old('business_address') }}</textarea>
                                            <span
                                                class="d-flex justify-content-end">{{ translate('0/100') }}</span>
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="mb-30">
                                                    <label for="business_support_number"
                                                           class="mb-2 form-label">{{ translate('business_support_number') }}</label>
                                                    <input type="text" name="business_support_phone"
                                                           value="{{ $settings->firstWhere('key_name', 'business_support_phone')?->value ?? old('business_support_phone') }}"
                                                           id="business_support_number"
                                                           class="form-control {{ $settings->firstWhere('key_name', 'business_contact_phone')?->value ?? old('business_contact_phone') ? 'dark-border' : '' }}"
                                                           placeholder="{{ translate('Ex: 9XXX-XXX-XXXX') }}">
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-30">
                                                    <label for="business_support_email"
                                                           class="mb-2 form-label">{{ translate('business_support_email') }}</label>
                                                    <input type="text" name="business_support_email"
                                                           value="{{ $settings->firstWhere('key_name', 'business_support_email')?->value ?? old('business_support_email') }}"
                                                           id="business_support_email"
                                                           class="form-control {{ $settings->firstWhere('key_name', 'business_support_email')?->value ?? old('business_support_email') ? 'dark-border' : '' }}"
                                                           placeholder="{{ translate('Ex: support@email.com') }}">
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-30">
                                                    <label for="trade_licence_number"
                                                           class="mb-2 form-label">{{ translate('trade_licence_number') }}</label>
                                                    <input type="text" name="trade_licence_number"
                                                           value="{{ $settings->firstWhere('key_name', 'trade_licence_number')?->value ?? old('trade_licence_number') }}"
                                                           id="trade_licence_number"
                                                           class="form-control {{ $settings->firstWhere('key_name', 'trade_licence_number')?->value ?? old('trade_licence_number') ? 'dark-border' : '' }}"
                                                           placeholder="{{ translate('Ex: 9.43896534') }}">
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-30">
                                                    <label for="copyright_text"
                                                           class="mb-2 form-label">{{ translate('company_copyright_text') }}</label>
                                                    <input type="text" name="copyright_text"
                                                           value="{{ $settings->firstWhere('key_name', 'copyright_text')?->value ?? old('copyright_text') }}"
                                                           id="copyright_text"
                                                           class="form-control {{ $settings->firstWhere('key_name', 'copyright_text')?->value ?? old('copyright_text') ? 'dark-border' : '' }}"
                                                           placeholder="{{ translate('Copyright@email.com') }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header b-dark">
                                <div class="d-flex align-items-baseline gap-2">
                                    <h5>
                                        <img src="{{ asset('public/assets/admin-module/img/svg/briefcase.svg') }}"
                                             alt="" class="svg">
                                    </h5>
                                    <div>
                                        <h5 class="fw-semibold text-capitalize mb-2">

                                            {{ translate('business_information') }}
                                        </h5>
                                        <div class="fs-12">
                                            {{ translate('Configure Essential Business Details') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-end">
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            <label for="country" class="form-label">{{ translate('country') }}
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-title="{{ translate('choose_your_business_location') }}"></i>
                                            </label>
                                            <select name="country_code" id="country" class="js-select" required>
                                                <option value="" disabled selected>
                                                    {{ translate('select_country') }}</option>
                                                @foreach (COUNTRIES as $country)
                                                    <option value="{{ $country['code'] }}"
                                                        {{ ($settings->where('key_name', 'country_code')->first()->value ?? '') == $country['code'] ? 'selected' : '' }}>
                                                        {{ $country['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            @php($cc = $settings->where('key_name', 'currency_code')->first()?->value)
                                            <label for="currency" class="form-label">{{ translate('currency') }}
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-title="{{ translate('choose_the_currency_of_your_business') }}"></i>
                                            </label>
                                            <select name="currency_code" id="currency" class="js-select">
                                                <option disabled selected>{{ translate('select_currency') }}</option>
                                                @foreach (CURRENCIES as $currency)
                                                    <option value="{{ $currency['code'] }}"
                                                        {{ $cc == $currency['code'] ? 'selected' : '' }}>
                                                        {{ $currency['name'] }}
                                                        ({{ $currency['symbol'] }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            <label class="form-label">{{ translate('currency_position') }}
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-title="{{ translate('Left: $99; Right: 99$') }}"></i>
                                            </label>
                                            <div class="d-flex align-items-center form-control">
                                                <div class="flex-grow-1">
                                                    <input type="radio" name="currency_symbol_position" value="left"
                                                           id="currency_position_left"
                                                        {{ ($settings->firstWhere('key_name', 'currency_symbol_position')?->value ?? '') == 'left' ? 'checked' : '' }}>
                                                    <label for="currency_position_left"
                                                           class="media gap-2 align-items-center">
                                                        <i class="tio-agenda-view-outlined text-muted"></i>
                                                        <span class="media-body">
                                                            ($) {{ translate('left') }}
                                                        </span>
                                                    </label>
                                                </div>

                                                <div class="flex-grow-1">
                                                    <input type="radio" name="currency_symbol_position" value="right"
                                                           id="currency_position_right"
                                                        {{ ($settings->where('key_name', 'currency_symbol_position')->first()->value ?? '') == 'right' ? 'checked' : '' }}>
                                                    <label for="currency_position_right"
                                                           class="media gap-2 align-items-center">
                                                        <i class="tio-table text-muted"></i>
                                                        <span class="media-body">{{ translate('right') }} ($)</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            <label for="time_zone"
                                                   class="form-label">{{ translate('time_zone') }}</label>
                                            <select name="time_zone" id="time_zone" class="js-select">
                                                <option value="" disabled selected>
                                                    {{ translate('select_time_zone') }}</option>
                                                @foreach (TIME_ZONES as $zone)
                                                    <option value="{{ $zone['tzCode'] }}"
                                                        {{ ($settings->where('key_name', 'time_zone')->first()->value ?? '') == $zone['tzCode'] ? 'selected' : '' }}>
                                                        (GMT{{ $zone['utc'] }})
                                                        {{ $zone['tzCode'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            <label for="time_format" class="form-label">Time
                                                Format</label>
                                            <select name="time_format" id="time_format" class="js-select">
                                                <option value="" disabled selected>Select Time Format</option>
                                                <option value="h:i:s A"
                                                    {{ ($settings->where('key_name', 'time_format')->first()->value ?? '') == 'h:i:s A' ? 'selected' : '' }}>
                                                    {{ translate('12_hour') }}</option>
                                                <option value="H:i:s"
                                                    {{ ($settings->where('key_name', 'time_format')->first()->value ?? '') == 'H:i:s' ? 'selected' : '' }}>
                                                    {{ translate('24_hour') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            <label for="currency_decimal"
                                                   class="form-label">{{ translate('decimal_after_point') }}</label>
                                            <input type="number" name="currency_decimal_point"
                                                   value="{{ $settings->firstWhere('key_name', 'currency_decimal_point')?->value ?? old('currency_decimal_point') }}"
                                                   id="currency_decimal" class="form-control"
                                                   placeholder="{{ translate('Ex: 2') }}">
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            <div
                                                class="form-control gap-2 align-items-center d-flex justify-content-between">
                                                <div class="d-flex align-items-center fw-medium gap-2 text-capitalize">
                                                    {{ translate('driver_self_registration') }}
                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                       data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('if_enabled,_drivers_can_register_themselves_from_the_driver_app') }}">
                                                    </i>
                                                </div>
                                                <div class="position-relative">
                                                    <label class="switcher">
                                                        <input type="checkbox" name="driver_self_registration"
                                                               class="switcher_input"
                                                            {{ $settings->where('key_name', 'driver_self_registration')->first()->value ?? 0 == 1 ? 'checked' : '' }}>
                                                        <span class="switcher_control"></span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            <div
                                                class="form-control gap-2 align-items-center d-flex justify-content-between">
                                                <div class="d-flex align-items-center fw-medium gap-2 text-capitalize">
                                                    {{ translate('customer_verification') }}
                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                       data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('if_enabled,_customers_need_to_verify_their_registration') }}">
                                                    </i>
                                                </div>
                                                <div class="position-relative">
                                                    <label class="switcher">
                                                        <input type="checkbox" name="customer_verification"
                                                               class="switcher_input"
                                                            {{ $settings->firstWhere('key_name', 'customer_verification')->value ?? 0 == 1 ? 'checked' : '' }}>
                                                        <span class="switcher_control"></span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="mb-30">
                                            <div
                                                class="form-control gap-2 align-items-center d-flex justify-content-between">
                                                <div class="d-flex align-items-center fw-medium gap-2 text-capitalize">
                                                    {{ translate('driver_verification') }}
                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                       data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('if_enabled,_drivers_need_to_verify_their_registration') }}">
                                                    </i>
                                                </div>
                                                <div class="position-relative">
                                                    <label class="switcher">
                                                        <input type="checkbox" name="driver_verification"
                                                               class="switcher_input"
                                                            {{ $settings->where('key_name', 'driver_verification')->first()->value ?? 0 == 1 ? 'checked' : '' }}>
                                                        <span class="switcher_control"></span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card h-100">
                            <div class="card-header b-dark">
                                <div class="d-flex align-items-baseline gap-2">
                                    <h5>
                                        <img src="{{ asset('public/assets/admin-module/img/svg/palette.svg') }}"
                                             alt="" class="svg">
                                    </h5>
                                    <div>
                                        <h5 class="fw-semibold text-capitalize mb-2">

                                            {{ translate('website_color') }}
                                        </h5>
                                        <div class="fs-12">
                                            {{ translate('Use the System Maintenance feature to work on the system and disable user access.') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-4">
                                <div class="form-group">
                                    <label
                                        class="title-color text-capitalize mb-2 fw-medium">{{ translate('primary_color') }}</label>
                                    <input type="color" name="website_color[primary]"
                                           class="form-control form-control_color"
                                           value="{{ $settings->firstWhere('key_name', 'website_color')->value['primary'] ?? null }}">
                                    <div class="">
                                        <div class="fs-12 fw-semibold text-dark color_code mt-2 mb-1 text-uppercase">
                                            {{ $settings->firstWhere('key_name', 'website_color')->value['primary'] ?? null }}
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="title-color text-capitalize mb-2 fw-medium">
                                        {{ translate('secondary_color') }}
                                    </label>
                                    <input type="color" name="website_color[secondary]"
                                           class="form-control form-control_color"
                                           value="{{ $settings->firstWhere('key_name', 'website_color')->value['secondary'] ?? null }}">
                                    <div class="">
                                        <div class="fs-12 fw-semibold text-dark color_code mt-2 mb-1 text-uppercase">
                                            {{ $settings->firstWhere('key_name', 'website_color')->value['secondary'] ?? null }}
                                        </div>

                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="title-color text-capitalize mb-2 fw-medium">
                                        {{ translate('background') }}
                                    </label>
                                    <input type="color" name="website_color[background]"
                                           class="form-control form-control_color"
                                           value="{{ $settings->firstWhere('key_name', 'website_color')->value['background'] ?? null }}">
                                    <div class="">
                                        <div class="fs-12 fw-semibold text-dark color_code mt-2 mb-1 text-uppercase">
                                            {{ $settings->firstWhere('key_name', 'website_color')->value['background'] ?? null }}
                                        </div>

                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="title-color text-capitalize mb-2 fw-medium">
                                        {{ translate('secondary_text') }}
                                    </label>
                                    <input type="color" name="text_color[secondary]"
                                           class="form-control form-control_color"
                                           value="{{ $settings->firstWhere('key_name', 'text_color')->value['secondary'] ?? null }}">
                                    <div class="">
                                        <div class="fs-12 fw-semibold text-dark color_code mt-2 mb-1 text-uppercase">
                                            {{ $settings->firstWhere('key_name', 'text_color')->value['secondary'] ?? null }}
                                        </div>

                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="title-color text-capitalize mb-2 fw-medium">
                                        {{ translate('light_text') }}
                                    </label>
                                    <input type="color" name="text_color[light]"
                                           value="{{ $settings->firstWhere('key_name', 'text_color')->value['light'] ?? null }}"
                                           class="form-control form-control_color">
                                    <div class="">
                                        <div class="fs-12 fw-semibold text-dark color_code mt-2 mb-1 text-uppercase">
                                            {{ $settings->firstWhere('key_name', 'text_color')->value['light'] ?? null }}
                                        </div>

                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="title-color text-capitalize mb-2 fw-medium">
                                        {{ translate('primary_text') }}
                                    </label>
                                    <input type="color" name="text_color[primary]"
                                           value="{{ $settings->firstWhere('key_name', 'text_color')->value['primary'] ?? null }}"
                                           class="form-control form-control_color">
                                    <div class="">
                                        <div class="fs-12 fw-semibold text-dark color_code mt-2 mb-1 text-uppercase">
                                            {{ $settings->firstWhere('key_name', 'text_color')->value['primary'] ?? null }}
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header b-dark">
                                <div class="d-flex align-items-baseline gap-2">
                                    <h5>
                                        <img src="{{ asset('public/assets/admin-module/img/svg/grid.svg') }}"
                                             alt="" class="svg">
                                    </h5>
                                    <div>
                                        <h5 class="fw-semibold text-capitalize mb-2">

                                            {{ translate('Logos_&_icons') }}
                                        </h5>
                                        <div class="fs-12">
                                            {{ translate('Use the System Maintenance feature to work on the system and disable user access.') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row gy-4">
                                    <div class="col-md-6">
                                        <div class="image-upload-card card h-100" id="header-logo-card">
                                            <div class="card-body d-flex flex-column justify-content-around">
                                                <div class="d-flex flex-column justify-content-around gap-4">
                                                    <h5
                                                        class="mb-0 text-capitalize d-flex align-items-center justify-content-center gap-2">
                                                        {{ translate('website_header_logo') }}
                                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                           data-bs-toggle="tooltip"
                                                           data-bs-title="{{ translate('website_header_logo') }}"></i>
                                                    </h5>
                                                    <div class="d-flex justify-content-center">
                                                        <div class="upload-file auto">
                                                            <input type="file" name="header_logo"
                                                                   class="upload-file__input"
                                                                   accept=".jpg, .jpeg, .png, .webp">
                                                            <span
                                                                class="edit-btn {{ $settings?->firstWhere('key_name', 'header_logo')?->value ? 'show' : '' }}">
                                                                <img
                                                                    src="{{ asset('public/assets/admin-module/img/svg/edit-circle.svg') }}"
                                                                    alt="" class="svg">
                                                            </span>
                                                            <div
                                                                class="upload-file__img lg d-flex justify-content-center align-items-center">
                                                                <div class="upload-file__textbox text-center">
                                                                    <img width="34" height="34"
                                                                         src="{{ onErrorImage(
                                                        $settings?->firstWhere('key_name', 'header_logo')?->value,
                                                        asset('storage/app/public/business') . '/' . $settings?->firstWhere('key_name', 'header_logo')?->value,
                                                        asset('public/assets/admin-module/img/svg/document-upload.svg'),
                                                        'business/',
                                                    ) }}"
                                                                         alt="" class="svg">
                                                                    <h6 class="mt-2 fw-semibold {{ $settings?->firstWhere('key_name', 'header_logo')?->value ? 'd-none' : '' }}">
                                                                        <span
                                                                            class="text-info">{{ translate('Click to upload') }}</span>
                                                                        {{ translate('or drag and drop') }}
                                                                    </h6>
                                                                </div>
                                                                <img class="upload-file__img__img" loading="lazy"
                                                                     style="display: none;" alt="">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <p class="mx-auto">{{ translate('JPG, JPEG, PNG, WEBP Less Than 1MB') }}
                                                        <strong>({{ translate('Ratio 3:1') }})</strong>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="col-md-6">
                                        <div class="image-upload-card card h-100" id="footer-logo-card">
                                            <div class="card-body d-flex flex-column justify-content-around">
                                                <div class="d-flex flex-column justify-content-around gap-4">
                                                    <h5
                                                        class="mb-0 text-capitalize d-flex align-items-center justify-content-center gap-2">
                                                        {{ translate('website_footer_logo') }}
                                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                           data-bs-toggle="tooltip"
                                                           data-bs-title="{{ translate('website_footer_logo') }}"></i>
                                                    </h5>

                                                    <div class="d-flex justify-content-center">
                                                        <div class="upload-file auto">
                                                            <input type="file" name="footer_logo"
                                                                   class="upload-file__input"
                                                                   accept=".jpg, .jpeg, .png, .webp">
                                                            <span
                                                                class="edit-btn {{ $settings?->firstWhere('key_name', 'footer_logo')?->value ? 'show' : '' }}">
                                                                <img
                                                                    src="{{ asset('public/assets/admin-module/img/svg/edit-circle.svg') }}"
                                                                    alt="" class="svg">
                                                            </span>

                                                            <div
                                                                class="upload-file__img lg d-flex justify-content-center align-items-center">
                                                                <div class="upload-file__textbox text-center">
                                                                    <img width="34" height="34"
                                                                         src="{{ onErrorImage(
                                                        $settings?->firstWhere('key_name', 'footer_logo')?->value,
                                                        asset('storage/app/public/business') . '/' . $settings?->firstWhere('key_name', 'footer_logo')?->value,
                                                        asset('public/assets/admin-module/img/svg/document-upload.svg'),
                                                        'business/',
                                                    ) }}"
                                                                         alt="" class="svg">
                                                                    <h6 class="mt-2 fw-semibold {{ $settings?->firstWhere('key_name', 'footer_logo')?->value ? 'd-none' : '' }}">
                                                                        <span
                                                                            class="text-info">{{ translate('Click to upload') }}</span>
                                                                        {{ translate('or drag and drop') }}
                                                                    </h6>
                                                                </div>

                                                                <img class="upload-file__img__img" loading="lazy"
                                                                     style="display: none;" alt="">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <p class="mx-auto">
                                                        {{ translate('JPG, JPEG, PNG, WEBP. Less Than 1MB') }}
                                                        <strong>({{ translate('Ratio 3:1') }})</strong>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="image-upload-card card h-100" id="favicon-card">
                                            <div class="card-body d-flex flex-column justify-content-around">
                                                <div class="d-flex flex-column justify-content-around gap-4">
                                                    <h5
                                                        class="mb-0 text-capitalize d-flex align-items-center justify-content-center gap-2">
                                                        {{ translate('website_favicon') }}
                                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                           data-bs-toggle="tooltip"
                                                           data-bs-title="{{ translate('website_favicon') }}"></i>
                                                    </h5>
                                                    <div class="d-flex justify-content-center">
                                                        <div class="upload-file auto">
                                                            <input type="file" name="favicon"
                                                                   class="upload-file__input"
                                                                   accept=".jpg, .jpeg, .png, .webp">
                                                            <span
                                                                class="edit-btn {{ $settings?->firstWhere('key_name', 'favicon')?->value ? 'show' : '' }}">
                                                                <img
                                                                    src="{{ asset('public/assets/admin-module/img/svg/edit-circle.svg') }}"
                                                                    alt="" class="svg">
                                                            </span>
                                                            <div
                                                                class="upload-file__img d-flex justify-content-center align-items-center">
                                                                <div class="upload-file__textbox text-center">
                                                                    <img width="34" height="34"
                                                                         src="{{ onErrorImage(
                                                        $settings?->firstWhere('key_name', 'favicon')?->value,
                                                        asset('storage/app/public/business') . '/' . $settings?->firstWhere('key_name', 'favicon')?->value,
                                                        asset('public/assets/admin-module/img/svg/document-upload.svg'),
                                                        'business/',
                                                    ) }}"
                                                                         alt="" class="svg">
                                                                    <h6 class="mt-2 fw-semibold {{ $settings?->firstWhere('key_name', 'favicon')?->value ? 'd-none' : '' }}">
                                                                        <span
                                                                            class="text-info">{{ translate('Click to upload') }}</span>
                                                                        <br>
                                                                        {{ translate('or drag and drop') }}
                                                                    </h6>
                                                                </div>
                                                                <img class="upload-file__img__img" loading="lazy"
                                                                     style="display: none;" alt="">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <p class="mx-auto">
                                                        {{ translate('JPG, JPEG, PNG, WEBP Less Than 1MB') }}
                                                        <strong>({{ translate('Ratio 1:1') }})</strong>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="image-upload-card card h-100" id="preloader-card">
                                            <div class="card-body d-flex flex-column justify-content-around">
                                                <div class="d-flex flex-column justify-content-around gap-4">
                                                    <h5
                                                        class="mb-0 text-capitalize d-flex align-items-center justify-content-center gap-2">
                                                        {{ translate('loading_gif') }}
                                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                           data-bs-toggle="tooltip"
                                                           data-bs-title="{{ translate('loading_gif') }}"></i>
                                                    </h5>
                                                    <div class="d-flex justify-content-center">
                                                        <div class="upload-file auto">
                                                            <input type="file" name="preloader"
                                                                   class="upload-file__input" accept=".gif, .json">
                                                            <span
                                                                class="edit-btn {{ $settings?->firstWhere('key_name', 'preloader')?->value ? 'show' : '' }}">
                                                                <img
                                                                    src="{{ asset('public/assets/admin-module/img/svg/edit-circle.svg') }}"
                                                                    alt="" class="svg">
                                                            </span>
                                                            <div
                                                                class="upload-file__img d-flex justify-content-center align-items-center">
                                                                <div class="upload-file__textbox text-center">
                                                                    <img width="34" height="34"
                                                                         src="{{ onErrorImage(
                                                        $settings?->firstWhere('key_name', 'preloader')?->value,
                                                        asset('storage/app/public/business') . '/' . $settings?->firstWhere('key_name', 'preloader')?->value,
                                                        asset('public/assets/admin-module/img/svg/document-upload.svg'),
                                                        'business/',
                                                    ) }}"
                                                                         alt="" class="svg">
                                                                    <h6 class="mt-2 fw-semibold {{ $settings?->firstWhere('key_name', 'preloader')?->value ? 'd-none' : '' }}" >
                                                                        <span
                                                                            class="text-info">{{ translate('Click to upload') }}</span>
                                                                        <br>
                                                                        {{ translate('or drag and drop') }}
                                                                    </h6>
                                                                </div>
                                                                <img class="upload-file__img__img" loading="lazy"
                                                                     style="display: none;" alt="">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <p class="mx-auto">
                                                        {{ translate('GIF, Jason Less Than 1MB') }}
                                                        <strong>({{ translate('Ratio 1:1') }})</strong>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- old image upload cards --}}
                    {{-- <div class="col-md-6 col-xl-6">
                        <div class="card h-100">
                            <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                                <h5 class="mb-0 text-capitalize d-flex align-items-center gap-2">
                                    <i class="bi bi-image"></i>
                                    {{ translate('website_header_logo') }}
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                        data-bs-title="{{ translate('website_header_logo') }}"></i>
                                </h5>
                                <span class="badge badge-primary">{{ translate('3:1') }}</span>
                            </div>
                            <div class="card-body d-flex flex-column justify-content-around">
                                <div class="d-flex flex-column justify-content-around gap-4">
                                    <div class="d-flex justify-content-center">
                                        <div class="upload-file auto">
                                            <input type="file" name="footer_logo" class="upload-file__input"
                                                accept=".png">
                                            <span class="edit-btn">
                                                <i class="bi bi-pencil-square text-primary"></i>
                                            </span>
                                            <div class="upload-file__img">
                                                <img width="250" height="60" loading="lazy"
                                                    src="{{ onErrorImage(
                                                        $settings?->firstWhere('key_name', 'footer_logo')?->value,
                                                        asset('storage/app/public/business') . '/' . $settings?->firstWhere('key_name', 'footer_logo')?->value,
                                                        asset('public/assets/admin-module/img/media/banner-upload-file.png'),
                                                        'business/',
                                                    ) }}"
                                                    alt="">
                                            </div>
                                        </div>
                                    </div>

                                    <p class="opacity-75 mx-auto max-w220">
                                        {{ translate('File Format - png Image Size - Maximum Size 5 MB.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-6">
                        <div class="card h-100">
                            <div class="card-header flex-wrap d-flex justify-content-between gap-2">
                                <h5 class="mb-0 text-capitalize d-flex align-items-center gap-2">
                                    <i class="bi bi-image"></i>
                                    {{ translate('website_favicon') }}
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                        data-bs-title="{{ translate('website_favicon') }}"></i>
                                </h5>
                                <span class="badge badge-primary">1:1</span>
                            </div>
                            <div class="card-body d-flex flex-column justify-content-around">
                                <div class="d-flex flex-column justify-content-around gap-4">
                                    <div class="d-flex justify-content-center">
                                        <div class="upload-file auto">
                                            <input type="file" name="favicon" class="upload-file__input"
                                                accept=".png">
                                            <span class="edit-btn">
                                                <i class="bi bi-pencil-square text-primary"></i>
                                            </span>
                                            <div class="upload-file__img">
                                                <img width="64" height="64" loading="lazy"
                                                    src="{{ onErrorImage(
                                                        $settings?->firstWhere('key_name', 'favicon')?->value,
                                                        asset('storage/app/public/business') . '/' . $settings?->firstWhere('key_name', 'favicon')?->value,
                                                        asset('public/assets/admin-module/img/media/upload-file.png'),
                                                        'business/',
                                                    ) }}"
                                                    alt="">
                                            </div>
                                        </div>
                                    </div>

                                    <p class="opacity-75 mx-auto max-w220">
                                        {{ translate('File Format - png Image Size - Maximum Size 5 MB.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-6">
                        <div class="card h-100">
                            <div class="card-header flex-wrap d-flex justify-content-between gap-2">
                                <h5 class="mb-0 text-capitalize d-flex align-items-center gap-2">
                                    <i class="bi bi-image"></i>
                                    {{ translate('loading_gif') }}
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                        data-bs-title="{{ translate('loading_gif') }}"></i>
                                </h5>
                                <span class="badge badge-primary">1:1</span>
                            </div>
                            <div class="card-body d-flex flex-column justify-content-around">
                                <div class="d-flex flex-column justify-content-around gap-4">
                                    <div class="d-flex justify-content-center">
                                        <div class="upload-file auto">
                                            <input type="file" name="preloader" class="upload-file__input"
                                                accept=".gif">
                                            <span class="edit-btn">
                                                <i class="bi bi-pencil-square text-primary"></i>
                                            </span>
                                            <div class="upload-file__img">
                                                <img width="180" height="180" loading="lazy"
                                                    src="{{ onErrorImage(
                                                        $settings?->firstWhere('key_name', 'preloader')?->value,
                                                        asset('storage/app/public/business') . '/' . $settings?->firstWhere('key_name', 'preloader')?->value,
                                                        asset('public/assets/admin-module/img/media/upload-file.png'),
                                                        'business/',
                                                    ) }}"
                                                    alt="">
                                            </div>
                                        </div>
                                    </div>

                                    <p class="opacity-75 mx-auto max-w220">
                                        {{ translate('File Format - gif Image Size - Maximum Size 5 MB.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div> --}}
                    {{-- old image upload cards end --}}
                </div>

            </div>
            <div class="footer-sticky">
                <div class="container-fluid">
                    <div class="d-flex justify-content-end py-4">
                        <button type="submit"
                                class="btn btn-primary text-capitalize">{{ translate('save_information') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- End Main Content -->


    {{--    Maintencemode modal --}}
    <div class="modal fade" id="maintenance-mode-modal" tabindex="-1" aria-labelledby="exampleModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="mb-0">
                        <i class="tio-notifications-alert mr-1"></i>
                        {{ translate('System Maintenance') }}
                    </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="{{ route('admin.business.setup.info.maintenance') }}">
                        <?php
                        $maintenanceMode = businessConfig('maintenance_mode')?->value == 1 ? 1 : 0;
                        $selectedMaintenanceSystem = businessConfig('maintenance_system_setup')?->value ?? [];
                        $selectedMaintenanceDuration = businessConfig('maintenance_duration_setup')?->value;
                        $selectedMaintenanceMessage = businessConfig('maintenance_message_setup')?->value;
                        ?>
                    <div class="modal-body">
                        @csrf
                        <div class="d-flex flex-column gap-4">
                            <div class="border-bottom px-4 py-3">
                                <div class="row g-3 align-items-center">
                                    <div class="col-sm-6 col-md-8">
                                        *{{ translate('By turning on maintenance mode Control your all system & function') }}
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <div
                                            class="d-flex justify-content-between align-items-center border rounded px-3 py-2">
                                            <div class="text-body">{{ translate('Maintenance Mode') }}</div>
                                            <label class="switcher ml-auto mb-0">
                                                <input type="checkbox" class="switcher_input" name="maintenance_mode"
                                                       id="maintenance-mode-checkbox"
                                                    {{ $maintenanceMode ? 'checked' : '' }}>
                                                <span class="switcher_control"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="px-4">
                                <div class="row mb-4">
                                    <div class="d-flex align-items-center">
                                        <div class="col-xl-4">
                                            <h5 class="mb-2">{{ translate('Select System') }}</h5>
                                            <p>{{ translate('Select the systems you want to temporarily deactivate for maintenance') }}
                                            </p>
                                        </div>
                                        <div class="col-xl-8">
                                            <div class="border p-3">
                                                <div class="d-flex flex-wrap gap-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input system-checkbox"
                                                               name="all_system"
                                                               type="checkbox"
                                                               {{ in_array('user_app', $selectedMaintenanceSystem) && in_array('driver_app', $selectedMaintenanceSystem)
                                                                   ? 'checked'
                                                                   : '' }}
                                                               id="allSystem">
                                                        <label class="form-check-label"
                                                               for="allSystem">{{ translate('All System') }}</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input system-checkbox" name="user_app"
                                                               type="checkbox"
                                                               {{ in_array('user_app', $selectedMaintenanceSystem) ? 'checked' : '' }}
                                                               id="userApp">
                                                        <label class="form-check-label"
                                                               for="userApp">{{ translate('User App') }}</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input system-checkbox"
                                                               name="driver_app"
                                                               type="checkbox"
                                                               {{ in_array('driver_app', $selectedMaintenanceSystem) ? 'checked' : '' }}
                                                               id="driverApp">
                                                        <label class="form-check-label"
                                                               for="driverApp">{{ translate('Driver App') }}</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="d-flex align-items-center">
                                        <div class="col-xl-4">
                                            <h5 class="mb-2">{{ translate('Maintenance Date') }}
                                                & {{ translate('Time') }}</h5>
                                            <p>{{ translate('Choose the maintenance mode duration for your selected system.') }}
                                            </p>
                                        </div>
                                        <div class="col-xl-8">
                                            <div class="border p-3">
                                                <div class="d-flex flex-wrap gap-5 mb-3">
                                                    <div>
                                                        <input type="radio" name="maintenance_duration"
                                                               {{ $selectedMaintenanceDuration == '' || (isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'one_day') ? 'checked' : '' }}
                                                               value="one_day" id="one_day">
                                                        <label class="form-check-label"
                                                               for="one_day">{{ translate('For 24 Hours') }}</label>
                                                    </div>
                                                    <div>
                                                        <input type="radio" name="maintenance_duration"
                                                               {{ isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'one_week' ? 'checked' : '' }}
                                                               value="one_week" id="one_week">
                                                        <label class="form-check-label"
                                                               for="one_week">{{ translate('For 1 Week') }}</label>
                                                    </div>
                                                    <div>
                                                        <input type="radio" name="maintenance_duration"
                                                               {{ isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'until_change' ? 'checked' : '' }}
                                                               value="until_change" id="until_change">
                                                        <label class="form-check-label"
                                                               for="until_change">{{ translate('Until I change') }}</label>
                                                    </div>
                                                    <div>
                                                        <input type="radio" name="maintenance_duration"
                                                               {{ isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'customize' ? 'checked' : '' }}
                                                               value="customize" id="customize">
                                                        <label class="form-check-label"
                                                               for="customize">{{ translate('Customize') }}</label>
                                                    </div>
                                                </div>
                                                <div class="row start-and-end-date g-3 mt-0">
                                                    <div class="col-md-6">
                                                        <label class="form-label">{{ translate('Start Date') }}</label>
                                                        <input type="datetime-local" class="form-control"
                                                               name="start_date" id="startDate"
                                                               value="{{ old('start_date', $selectedMaintenanceDuration['start_date'] ?? '') }}"
                                                               required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">{{ translate('End Date') }}</label>
                                                        <input type="datetime-local" class="form-control"
                                                               name="end_date"
                                                               id="endDate"
                                                               value="{{ old('end_date', $selectedMaintenanceDuration['end_date'] ?? '') }}"
                                                               required>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <small id="dateError" class="form-text text-danger"
                                                               style="display: none;">{{ translate('Start date cannot be greater than end date.') }}</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="px-4">
                            <div id="advanceFeatureButtonDiv">
                                <div class="d-flex justify-content-center">
                                    <a href="#" id="advanceFeatureToggle"
                                       class="d-block mb-3 maintenance-advance-feature-button text-primary text-underline fw-bold">{{ translate('Advance Feature') }}</a>
                                </div>
                            </div>

                            <div class="row" id="advanceFeatureSection" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <div class="col-xl-4">
                                        <h5 class="mb-2">{{ translate('Maintenance Massage') }}</h5>
                                        <p>{{ translate('Select & type what massage you want to see your selected system when maintenance mode is active.') }}
                                        </p>
                                    </div>
                                    <div class="col-xl-8">
                                        <div class="border p-3">
                                            <div class="mb-4">
                                                <label class="mb-2">{{ translate('Show Contact Info') }}</label>
                                                <div class="d-flex flex-wrap gap-5 mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="business_number"
                                                               {{ isset($selectedMaintenanceMessage) && $selectedMaintenanceMessage['business_number'] == 1 ? 'checked' : '' }}
                                                               id="businessNumber">
                                                        <label class="form-check-label"
                                                               for="businessNumber">{{ translate('Business Number') }}</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="business_email"
                                                               {{ isset($selectedMaintenanceMessage) && $selectedMaintenanceMessage['business_email'] == 1 ? 'checked' : '' }}
                                                               id="businessEmail">
                                                        <label class="form-check-label"
                                                               for="businessEmail">{{ translate('Business Email') }}</label>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="mb-4">
                                                <label class="mb-2">{{ translate('Maintenance Message') }}
                                                    <i class="tio-info-outined" data-bs-toggle="tooltip"
                                                       title="{{ translate('The maximum character limit is 100') }}">
                                                    </i>
                                                </label>
                                                <input type="text" class="form-control" name="maintenance_message"
                                                       placeholder="We're Working On Something Special!" maxlength="100"
                                                       value="{{ $selectedMaintenanceMessage['maintenance_message'] ?? '' }}">
                                            </div>
                                            <div>
                                                <label class="mb-2">{{ translate('Message Body') }}
                                                    <i class="tio-info-outined" data-bs-toggle="tooltip"
                                                       title="{{ translate('The maximum character limit is 255') }}">
                                                    </i>
                                                </label>
                                                <textarea class="form-control" name="message_body" maxlength="255"
                                                          rows="3"
                                                          placeholder="{{ translate('Our system is currently undergoing maintenance to bring you an even tastier experience.') }}">{{ isset($selectedMaintenanceMessage) && $selectedMaintenanceMessage['message_body'] ? $selectedMaintenanceMessage['message_body'] : '' }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="showLessButton" style="display: none;">
                                <div class="d-flex justify-content-center mt-4">
                                    <a href="#" id="seeLessToggle"
                                       class="d-block mb-3 maintenance-advance-feature-button text-primary text-underline fw-bold">{{ translate('See Less') }}</a>
                                </div>
                            </div>
                        </div>
                        <div class="px-4">
                            <div class="btn--container justify-content-end">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal"
                                        id="cancelButton">{{ translate('Cancel') }}</button>
                                <button type="{{ env('APP_MODE') != 'demo' ? 'submit' : 'button' }}"
                                        class="btn btn-primary call-demo">{{ translate('Save') }}</button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>

@endsection

@push('script')
    <script src="{{ asset('public/assets/admin-module/js/business-management/business-setup/index.js') }}"></script>

    <script>
        "use strict";

        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#business_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_donot_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
    </script>
    <script>
        $('.maintenance-mode-show').click(function () {
            $('#maintenance-mode-modal').modal('show');
        });

        $(document).ready(function () {
            var initialMaintenanceMode = $('#maintenance-mode-input').is(':checked');

            $('#maintenance-mode-modal').on('show.bs.modal', function () {
                var initialMaintenanceModeModel = $('#maintenance-mode-input').is(':checked');
                $('#maintenance-mode-checkbox').prop('checked', initialMaintenanceModeModel);
            });

            $('#maintenance-mode-modal').on('hidden.bs.modal', function () {
                $('#maintenance-mode-input').prop('checked', initialMaintenanceMode);
                updateDataState();
            });

            $('#cancelButton').click(function () {
                $('#maintenance-mode-input').prop('checked', initialMaintenanceMode);
                $('#maintenance-mode-modal').modal('hide');
                updateDataState();
            });

            $('#maintenance-mode-checkbox').change(function () {
                $('#maintenance-mode-input').prop('checked', $(this).is(':checked'));
                updateDataState();
            });

            // Update the data-state attribute for the maintenance mode text
            function updateDataState() {
                const isChecked = $('#maintenance-mode-input').is(':checked');
                $('#switcher-text').attr('data-state', isChecked ? 'On' : 'Off');
            }

            // Call this function on page load to set the initial state
            updateDataState();
        });

        $(document).ready(function () {
            $('#advanceFeatureToggle').click(function (event) {
                event.preventDefault();
                $('#advanceFeatureSection').show();
                $('#showLessButton').show();
                $('#advanceFeatureButtonDiv').hide();
            });

            $('#seeLessToggle').click(function (event) {
                event.preventDefault();
                $('#advanceFeatureSection').hide();
                $('#showLessButton').hide();
                $('#advanceFeatureButtonDiv').show();
            });

            $('#allSystem').change(function () {
                var isChecked = $(this).is(':checked');
                $('.system-checkbox').prop('checked', isChecked);
            });

            // If any other checkbox is unchecked, also uncheck "All System"
            $('.system-checkbox').not('#allSystem').change(function () {
                if (!$(this).is(':checked')) {
                    $('#allSystem').prop('checked', false);
                } else {
                    // Check if all system-related checkboxes are checked
                    if ($('.system-checkbox').not('#allSystem').length === $('.system-checkbox:checked')
                        .not('#allSystem').length) {
                        $('#allSystem').prop('checked', true);
                    }
                }
            });

            $(document).ready(function () {
                var startDate = $('#startDate');
                var endDate = $('#endDate');
                var dateError = $('#dateError');

                function updateDatesBasedOnDuration(selectedOption) {
                    if (selectedOption === 'one_day' || selectedOption === 'one_week') {
                        var now = new Date();
                        var timezoneOffset = now.getTimezoneOffset() * 60000;
                        var formattedNow = new Date(now.getTime() - timezoneOffset).toISOString().slice(0,
                            16);

                        if (selectedOption === 'one_day') {
                            var end = new Date(now);
                            end.setDate(end.getDate() + 1);
                        } else if (selectedOption === 'one_week') {
                            var end = new Date(now);
                            end.setDate(end.getDate() + 7);
                        }

                        var formattedEnd = new Date(end.getTime() - timezoneOffset).toISOString().slice(0,
                            16);

                        startDate.val(formattedNow).prop('readonly', false).prop('required', true);
                        endDate.val(formattedEnd).prop('readonly', false).prop('required', true);
                        $('.start-and-end-date').removeClass('opacity');
                        dateError.hide();
                    } else if (selectedOption === 'until_change') {
                        startDate.val('').prop('readonly', true).prop('required', false);
                        endDate.val('').prop('readonly', true).prop('required', false);
                        $('.start-and-end-date').addClass('opacity');
                        dateError.hide();
                    } else if (selectedOption === 'customize') {
                        startDate.prop('readonly', false).prop('required', true);
                        endDate.prop('readonly', false).prop('required', true);
                        $('.start-and-end-date').removeClass('opacity');
                        dateError.hide();
                    }
                }

                function validateDates() {
                    var start = new Date(startDate.val());
                    var end = new Date(endDate.val());
                    if (start > end) {
                        dateError.show();
                        startDate.val('');
                        endDate.val('');
                    } else {
                        dateError.hide();
                    }
                }

                // Initial load
                var selectedOption = $('input[name="maintenance_duration"]:checked').val();
                updateDatesBasedOnDuration(selectedOption);

                // When maintenance duration changes
                $('input[name="maintenance_duration"]').change(function () {
                    var selectedOption = $(this).val();
                    updateDatesBasedOnDuration(selectedOption);
                });

                // When start date or end date changes
                $('#startDate, #endDate').change(function () {
                    $('input[name="maintenance_duration"][value="customize"]').prop('checked',
                        true);
                    startDate.prop('readonly', false).prop('required', true);
                    endDate.prop('readonly', false).prop('required', true);
                    validateDates();
                });
            });

        });
    </script>
    <script>
        // Get all upload-file input elements
        document.querySelectorAll('.upload-file__input').forEach(function (input) {
            input.addEventListener('change', function (event) {
                var file = event.target.files[0];
                var card = event.target.closest('.upload-file');
                var textbox = card.querySelector('.upload-file__textbox');
                var imgElement = card.querySelector('.upload-file__img__img');

                if (file) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        textbox.style.display = 'none';
                        imgElement.src = e.target.result;
                        imgElement.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
    <script>
        "use strict";
        $(document).ready(function () {
            //----- sticky footer
            $(window).on('scroll', function () {
                const $footer = $('.footer-sticky');
                const scrollPosition = $(window).scrollTop() + $(window).height();
                const documentHeight = $(document).height();

                if (scrollPosition >= documentHeight - 5) {
                    $footer.addClass('no-shadow');
                } else {
                    $footer.removeClass('no-shadow');
                }
            });
        });
    </script>
@endpush

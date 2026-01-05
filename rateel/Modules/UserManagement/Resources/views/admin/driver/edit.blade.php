@extends('adminmodule::layouts.master')

@section('title', translate('Update_Driver'))

@section('content')

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                <h2 class="fs-22">{{ translate('update_Driver') }}</h2>
            </div>

            <form action="{{ route('admin.driver.update', ['id' => $driver->id]) }}" method="post"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-body">
                        <div class="row gy-4">
                            <div class="col-lg-8">
                                <h5 class="text-primary text-uppercase mb-4">{{ translate('general_info') }}</h5>

                                <div class="row align-items-end">
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="f_name"
                                                   class="mb-2 text-capitalize">{{ translate('first_name') }} <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" required value="{{ $driver?->first_name }}"
                                                   name="first_name" id="f_name" class="form-control"
                                                   placeholder="{{ translate('ex') }}: {{ translate('Maximilian') }}">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="l_name" class="mb-2">{{ translate('last_name') }} <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" required value="{{ $driver?->last_name }}"
                                                   name="last_name" id="l_name" class="form-control"
                                                   placeholder="{{ translate('ex') }}: {{ translate('Schwarzmüller') }}">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="p_email" class="mb-2">{{ translate('email') }} <span
                                                    class="text-danger">*</span></label>
                                            <input type="email" required value="{{ $driver->email }}" name="email"
                                                   id="p_email" class="form-control"
                                                   placeholder="{{ translate('ex') }}: {{ translate('company@company.com') }}">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="identity_type" class="mb-2">{{ translate('identity_type') }}
                                                <span class="text-danger">*</span></label>
                                            <select name="identification_type" class="js-select text-capitalize"
                                                    id="identity_type" required>
                                                <option value="passport"
                                                    {{ $driver->identification_type == 'passport' ? 'selected' : '' }}>
                                                    {{ translate('passport') }}</option>
                                                <option value="nid"
                                                    {{ $driver->identification_type == 'nid' ? 'selected' : '' }}>
                                                    {{ translate('NID') }}</option>
                                                <option value="driving_license"
                                                    {{ $driver->identification_type == 'driving_license' ? 'selected' : '' }}>
                                                    {{ translate('driving_license') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="identity_card_num"
                                                   class="mb-2">{{ translate('identity_number') }} <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" required value="{{ $driver->identification_number }}"
                                                   name="identification_number" id="identity_card_num"
                                                   class="form-control"
                                                   placeholder="{{translate('Ex: 3032')}}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="d-flex flex-column justify-content-around gap-3">
                                    <h5 class="text-center text-capitalize">{{ translate('driver_image') }}</h5>

                                    <div class="d-flex justify-content-center">
                                        <div class="upload-file auto">
                                            <input type="file" name="profile_image" class="upload-file__input"
                                                   accept=".jpg, .jpeg, .png, .webp">
                                            <span class="edit-btn show">
                                                <img
                                                    src="{{ asset('public/assets/admin-module/img/svg/edit-circle.svg') }}"
                                                    alt="" class="svg">
                                            </span>
                                            <div
                                                class="upload-file__img border-gray d-flex justify-content-center align-items-center w-180 h-180 p-0">
                                                <img class="upload-file__img__img h-100" width="180" height="180"
                                                     loading="lazy"
                                                     src="{{ onErrorImage(
                                                    $driver?->profile_image,
                                                    getMediaUrl($driver?->profile_image, 'driver/profile'),
                                                    asset('public/assets/admin-module/img/avatar/avatar.png'),
                                                    'driver/profile',
                                                ) }}"
                                                     alt="">
                                            </div>
                                        </div>
                                    </div>
                                    <p class="opacity-75 mx-auto max-w220">
                                        {{ translate('JPG, JPEG, PNG, WEBP Less Than 1MB') }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex flex-column justify-content-around gap-3">
                                            <h5 class="">{{ translate('identity_card_images') }}</h5>

                                            <div class="gap-3 d-flex image-contain">
                                                @if ($driver?->identification_image)
                                                    @foreach ($driver?->identification_image as $img)
                                                        <div class="upload-file__img upload-file__img_banner">
                                                            <img src="{{ onErrorImage(
                                                                $img,
                                                                getMediaUrl($img, 'driver/identity'),
                                                                asset('public/assets/admin-module/img/media/banner-upload-file.png'),
                                                                'driver/identity',
                                                            ) }}"
                                                                 class="rounded-circle dark-support"
                                                                 width="100%"
                                                                 alt="">
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>

                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex flex-column justify-content-around gap-3">
                                            <h5 class="">{{ translate('update_identity_card_images') }}</h5>
                                            <div class="gap-3 d-flex custom" id="multi_image_picker">
                                            </div>

                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                            
                        </div>

                    </div>
                </div>

                <div class="card overflow-visible mt-3">
                    <div class="card-body">
                        <h5 class="text-primary text-uppercase mb-4">{{ translate('account_information') }}</h5>

                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label for="phone_number" class="mb-2">{{ translate('phone') }} <span
                                            class="text-danger">*</span></label>
                                    <input type="tel" pattern="[0-9]{1,14}" required value="{{ $driver->phone }}"
                                           id="phone_number" class="form-control w-100 text-dir-start"
                                           placeholder="{{ translate('ex') }}: xxxxx xxxxxx">
                                    <input type="hidden" id="phone_number-hidden-element" name="phone">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4 input-group_tooltip">
                                    <label for="password" class="mb-2">{{ translate('password') }}</label>
                                    <input type="password" name="password" id="password" class="form-control"
                                           placeholder="{{ translate('ex') }}: ********">
                                    <i id="password-eye" class="mt-3 bi bi-eye-slash-fill text-primary tooltip-icon"
                                       data-bs-toggle="tooltip" data-bs-title=""></i>

                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4 input-group_tooltip">
                                    <label for="confirm_password"
                                           class="mb-2">{{ translate('confirm_password') }}</label>
                                    <input type="password" name="confirm_password" id="confirm_password"
                                           class="form-control" placeholder="{{ translate('ex') }}: ********'">
                                    <i id="conf-password-eye"
                                       class="mt-3 bi bi-eye-slash-fill text-primary tooltip-icon"
                                       data-bs-toggle="tooltip" data-bs-title=""></i>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="mb-4">{{ translate('other_documents') }}</h5>
                        <div class="d-flex flex-wrap gap-3">
                            @if ($driver->other_documents != null)
                                @foreach ($driver->other_documents as $document)
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
                            <div class="d-flex flex-wrap gap-3" id="selected-files-container1"></div>
                            <div id="input-data"></div>
                            <!-- Upload New Documents -->
                            <div class="upload-file file__input" id="file__input">
                                <input type="file" class="upload-file__input2" multiple="multiple"
                                >
                                <div class="upload-file__img2">
                                    <div class="upload-box rounded media gap-4 align-items-center p-4 px-lg-5">
                                        <i class="bi bi-cloud-arrow-up-fill fs-20"></i>
                                        <div class="media-body">
                                            <p class="text-muted mb-2 fs-12">{{ translate('upload') }}</p>
                                            <h6 class="fs-12 text-capitalize">{{ translate('file_or_image') }}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-3">
                    <button class="btn btn-primary" type="submit">{{ translate('save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- End Main Content -->

@endsection

@push('script')
    <link href="{{ asset('public/assets/admin-module/css/intlTelInput.min.css') }}" rel="stylesheet"/>
    <script src="{{ asset('public/assets/admin-module/js/intlTelInput.min.js') }}"></script>
    <script src="{{ asset('public/assets/admin-module/js/spartan-multi-image-picker.js') }}"></script>
    <script src="{{ asset('public/assets/admin-module/js/password.js') }}"></script>
    <script src="{{ asset('public/assets/admin-module/js/upload-files-create.js') }}"></script>

    <script>
        "use strict";
        initializePhoneInput("#phone_number", "#phone_number-hidden-element");
    </script>
@endpush

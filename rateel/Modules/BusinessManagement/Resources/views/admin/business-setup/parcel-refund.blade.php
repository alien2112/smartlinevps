@extends('adminmodule::layouts.master')

@section('title', translate('Parcel Refund Settings'))

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
            <div class="card mb-3">
                <form action="{{route('admin.business.setup.parcel-refund.store')."?type=".PARCEL_SETTINGS}}"
                      id="parcel_form"
                      method="POST">
                    @csrf
                    <div class="collapsible-card-body">
                        <div class="card-header d-flex align-items-center justify-content-between gap-2">
                            <div class="w-0 flex-grow-1">
                                <h5 class="mb-2 text-capitalize">{{ translate('Parcel_Refund_Request') }}</h5>
                                <div class="fs-12">
                                    {{ translate('When_ON_the_feature_customer_can_send_you_a_parcel_refund_request.') }}
                                </div>
                            </div>
                            <label class="switcher">
                                <input class="switcher_input collapsible-card-switcher update-business-setting"
                                        id="parcelRefundStatus"
                                        type="checkbox"
                                        name="parcel_refund_status"
                                        data-name="parcel_refund_status"
                                        data-type="{{PARCEL_SETTINGS}}"
                                        data-url="{{route('admin.business.setup.update-business-setting')}}"
                                        data-icon=" {{asset('public/assets/admin-module/img/parcel_tracking.png')}}"
                                        data-title="{{translate('Are you sure')}}?"
                                        data-sub-title="{{($settings->firstWhere('key_name', 'parcel_refund_status')->value?? 0) == 1 ? translate('Do you want to turn OFF Parcel Refund for customer')."? " .translate("When it’s off the customer will not be able to  refund any parcel.") : translate('Do you want to turn ON Parcel Refund for customer')."? ". translate('When it’s off the customer will be able to  refund any parcel.')}}"
                                        data-confirm-btn="{{($settings->firstWhere('key_name', 'parcel_refund_status')->value?? 0) == 1 ? translate('Turn Off') : translate('Turn On')}}"
                                    {{($settings->firstWhere('key_name', 'parcel_refund_status')->value?? 0) == 1? 'checked' : ''}}
                                >
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                        <div class="card-body collapsible-card-content">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <h6 class="mb-2 text-capitalize">{{ translate('Validity_Setup') }}</h6>
                                    <div class="fs-12">
                                        {{ translate('Here you can set the time period during which customers can request a refund for their parcel after completing an order') }}
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="p-30 rounded bg-F6F6F6">
                                        <div class="mt-2">
                                            <label for="refundRequestValidity"
                                                   class="form-label">{{ translate('Refund_Request_Validity') }}
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-title="{{ translate('Set the maximum time after a parcel return for refund request.') }}"></i>
                                            </label>
                                            <div class="input-group input--group">
                                                <input type="number" name="parcel_refund_validity"
                                                       id="parcelRefundValidity" step="1" min="1" max="99999999"
                                                       class="form-control"
                                                       value="{{$settings->firstWhere('key_name', 'parcel_refund_validity')?->value ?? old('parcel_refund_validity')}}"
                                                       placeholder="Ex : 2">
                                                <select class="form-select" name="parcel_refund_validity_type">
                                                    <option value="day"
                                                        {{ $settings->firstWhere('key_name', 'parcel_refund_validity_type')?->value == 'day' ? 'selected' : '' }}>
                                                        {{ translate('Day') }}</option>
                                                    <option value="hour"
                                                        {{ $settings->firstWhere('key_name', 'parcel_refund_validity_type')?->value == 'hour' ? 'selected' : '' }}>
                                                        {{ translate('Hour') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit"
                                        class="btn btn-primary text-uppercase btn-lg">{{ translate('save') }}</button>
                            </div>
                        </div>

                    </div>
                </form>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <h5 class="mb-2">{{ translate('Parcel_Refund_Reasons') }}</h5>
                            <div class="fs-12">
                                {{ translate('Here you can set the reason that can be chosen by the customer when they are submitting a refund request') }}
                            </div>
                        </div>
                        <div class="col-md-8">
                            <form action="{{ route('admin.business.setup.parcel-refund.reason.store') }}"
                                  method="post">
                                @csrf
                                <div class="col-md-12">
                                    <div class="p-30 rounded bg-F6F6F6">
                                        <div class="mt-2">
                                            <label for=""
                                                   class="form-label">{{ translate('Parcel_Refund_Reasons') }}
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-title="{{ translate('Here you can set the reasons that customers choose when refund a parcel.') }}"></i>
                                            </label>
                                            <div class="character-count">
                                                <textarea id="title" name="title"
                                                          class="form-control character-count-field" cols="30" rows="1"
                                                          placeholder="{{ translate('Ex: Received product broken') }}"
                                                          maxlength="200" data-max-character="200" required></textarea>
                                                <span class="d-flex justify-content-end">{{translate('0/200')}}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex gap-3 justify-content-end mt-4">
                                        <button
                                            class="btn btn-secondary h-40px min-w-100px justify-content-center"
                                            type="reset">{{translate('Reset')}}</button>
                                        <button
                                            class="btn btn-primary h-40px min-w-100px justify-content-center">{{translate('Submit')}}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header border-0 d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    <h5 class="d-flex align-items-center gap-2 m-0">
                        <i class="bi bi-person-fill-gear"></i>
                        {{ translate('Parcel Refund Reason List') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light align-middle">
                            <tr>
                                <th class="text-center sl">{{translate('SL')}}</th>
                                <th class="text-center text-capitalize">{{translate('Reason')}}</th>
                                <th class="text-center text-capitalize">{{translate('Status')}}</th>
                                <th class="text-center action">{{translate('Action')}}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($parcelRefundReasons as $key => $parcelRefundReason)
                                <tr>
                                    <td class="text-center sl">{{ $key + $parcelRefundReasons->firstItem() }}</td>
                                    <td class="text-center">
                                        {{$parcelRefundReason->title}}
                                    </td>
                                    <td class="text-center">
                                        <label class="switcher mx-auto">
                                            <input class="switcher_input status-change"
                                                   data-url="{{ route('admin.business.setup.parcel-refund.reason.status') }}"
                                                   id="{{ $parcelRefundReason->id }}"
                                                   type="checkbox"
                                                   name="status" {{ $parcelRefundReason->is_active == 1 ? "checked": ""  }} >
                                            <span class="switcher_control"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2 align-items-center">
                                            <button class="btn btn-outline-primary btn-action editData"
                                                    data-id="{{$parcelRefundReason->id}}">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <button data-id="delete-{{ $parcelRefundReason?->id }}"
                                                    data-message="{{ translate('want_to_delete_this_refund_reason?') }}"
                                                    type="button"
                                                    class="btn btn-outline-danger btn-action form-alert">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                            <form
                                                action="{{ route('admin.business.setup.parcel-refund.reason.delete', ['id' => $parcelRefundReason?->id]) }}"
                                                id="delete-{{ $parcelRefundReason?->id }}" method="post">
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
        <!-- End Main Content -->
        <div class="d-flex justify-content-end mt-3">
            {{ $parcelRefundReasons->links() }}
        </div>
        {{-- Edit modal --}}
        <div class="edit-modal modal fade" id="editDataModal">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                </div>
            </div>
        </div>
        @endsection

        @push('script')
            <script>
                "use strict";


                $('.character-count-field').on('keyup change', function () {
                    initialCharacterCount($(this));
                });
                $('.character-count-field').each(function () {
                    initialCharacterCount($(this));
                });

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


                function initialCharacterCount(item) {
                    let str = item.val();
                    let maxCharacterCount = item.data('max-character');
                    let characterCount = str.length;
                    if (characterCount > maxCharacterCount) {
                        item.val(str.substring(0, maxCharacterCount));
                        characterCount = maxCharacterCount;
                    }
                    item.closest('.character-count').find('span').text(characterCount + '/' + maxCharacterCount);
                }

                let permission = false;
                @can('business_edit')
                    permission = true;
                @endcan

                $(document).ready(function () {
                    $('.editData').click(function () {
                        let id = $(this).data('id');
                        let url = "{{ route('admin.business.setup.parcel-refund.reason.edit', ':id') }}";
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

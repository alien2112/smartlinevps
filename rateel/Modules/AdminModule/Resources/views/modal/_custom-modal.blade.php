{{--Custom Modal Start--}}
<div class="modal fade" id="customModal">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-toggle="modal">
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto">
                    <div>
                        <div class="text-center">
                            <img alt="" class="mb-4" id="icon"
                                 src="{{asset('public/assets/admin-module/img/svg/blocked_customer.svg')}}">
                            <h5 class="modal-title mb-3" id="title">{{translate("Are you sure?")}}</h5>
                        </div>
                        <div class="text-center mb-4 pb-2">
                            <p id="subTitle">{{translate("Want to change status")}}</p>
                        </div>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--cancel min-w-120" id="modalCancelBtn">
                            {{translate('Cancel')}}
                        </button>
                        <button type="button" class="btn btn-primary min-w-120"
                                id="modalConfirmBtn">{{translate('Ok')}}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{--Custom Modal End--}}

{{--Customer Level Setting Warning Modal Start--}}
<div class="modal fade" id="customerLevelSettingWarningModal">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-toggle="modal">
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto">
                    <div>
                        <div class="text-center">
                            <img alt="" class="mb-4" id="icon"
                                 src="{{asset('public/assets/admin-module/img/warning.png')}}">
                            <h5 class="modal-title mb-3"
                                id="title">{{translate("This feature is turned off from settings")}}</h5>
                        </div>
                        <div class="text-center mb-4 pb-2">
                            <p id="subTitle">{{translate("Customer level feature is currently turned off from business settings. If you want to active all the level for customers in the app, turn on the feature from the settings")}}</p>
                        </div>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--cancel min-w-120" id="modalCancelBtn"
                                data-bs-toggle="modal">
                            {{translate('Not Now')}}
                        </button>
                        <a href="#" class="btn btn-primary min-w-120"
                           id="customerLevelSettingWarningModalConfirmBtn">{{translate('Go to Settings')}}</a>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{--Customer Level Setting Warning Modal End--}}
{{--Driver Level Setting Warning Modal Start--}}
<div class="modal fade" id="driverLevelSettingWarningModal">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-toggle="modal">
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto">
                    <div>
                        <div class="text-center">
                            <img alt="" class="mb-4" id="icon"
                                 src="{{asset('public/assets/admin-module/img/warning.png')}}">
                            <h5 class="modal-title mb-3"
                                id="title">{{translate("This feature is turned off from settings")}}</h5>
                        </div>
                        <div class="text-center mb-4 pb-2">
                            <p id="subTitle">{{translate("Driver level feature is currently turned off from business settings. If you want to active all the level for drivers in the app, turn on the feature from the settings")}}</p>
                        </div>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--cancel min-w-120" id="modalCancelBtn"
                                data-bs-toggle="modal">
                            {{translate('Not Now')}}
                        </button>
                        <a href="#" class="btn btn-primary min-w-120"
                           id="driverLevelSettingWarningModalConfirmBtn">{{translate('Go to Settings')}}</a>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{--Driver Level Setting Warning Modal End--}}
{{--SMS Configuration Warning Modal Start--}}
<div class="modal fade" id="smsGatewayWarningModal">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-toggle="modal">
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto">
                    <div>
                        <div class="text-center">
                            <img alt="" class="mb-4" id="icon"
                                 src="{{asset('public/assets/admin-module/img/sms_config_modal.png')}}">
                            <h5 class="modal-title mb-3" id="title">{{translate("Set Up SMS Configuration First")}}</h5>
                        </div>
                        <div class="text-center mb-4 pb-2">
                            <p id="subTitle">{{translate("It looks like your SMS configuration is not set up yet. To enable the OTP system, please set up the SMS configuration first.")}}</p>
                        </div>
                    </div>
                    <div class="btn--container justify-content-center">
                        <a href="#" class="btn btn-primary min-w-120"
                           id="smsGatewayWarningModalConfirmBtn">{{translate('Go to SMS Configuration')}}</a>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{--Driver Level Setting Warning Modal End--}}

{{-- Dynamic toggle modal Start--}}
<div class="modal fade" id="toggle-modal" aria-modal="true" role="dialog">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto mb-20">
                    <div class="text-center">
                        <img id="toggle-image" alt="" class="mb-20" src="">
                        <h5 class="modal-title" id="toggle-title">
                        </h5>
                    </div>
                    <div class="text-center" id="toggle-message">

                    </div>
                    <div class="btn--container justify-content-center mt-3">
                        <button id="reset_btn" type="reset" class="btn btn--cancel min-w-120 fs-14 fw-semibold"
                                data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button type="button" id="toggle-ok-button"
                                class="btn btn-primary min-w-120 confirm-Toggle fs-14 fw-semibold">{{ translate('Ok') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{-- Dynamic toggle modal End--}}

{{-- Parcel Refund Modal Start--}}
<div class="modal fade" id="parcelRefundModal">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-toggle="modal">
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <form id="parcelRefundForm" method="POST">
                    @csrf
                    <div class="max-349 mx-auto">
                        <div>
                            <div class="text-center">
                                <img alt="" class="mb-4" id="parcelRefundIcon"
                                     src="">
                                <h5 class="modal-title mb-3" id="parcelRefundTitle"></h5>
                            </div>
                            <div class="text-center mb-4 pb-2">
                                <p id="parcelRefundSubTitle"></p>
                            </div>
                            <div class="mb-4">
                                <label for="refund_reason" class="form-label"
                                ><span id="inputLabelTitle"></span><span class="text-danger">*</span></label>
                                <div class="character-count">
                                <textarea class="form-control character-count-field" maxlength="150"
                                          data-max-character="150" id="note" rows="3" name="note"
                                          placeholder="{{translate('Type a note')}}" required></textarea>
                                    <div class="text-end">{{ translate('0/150') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="btn--container justify-content-center">
                            <button type="button" class="btn btn--cancel min-w-120" id="parcelRefundModalCancelBtn"
                                    data-bs-toggle="modal">
                                {{translate('Cancel')}}
                            </button>
                            <button type="submit" class="btn btn-primary min-w-120"
                                    id="parcelRefundModalConfirmBtn">{{translate('Ok')}}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="parcelMakeRefundModal">
    <div class="modal-dialog modal-lg extra-fare-setup-modal">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">{{translate("Make Refund")}}</h5>
                <button type="submit" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="parcelMakeRefundForm" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="refundAmount" class="form-label">{{translate('Refund Amount')}}
                            ({{session()->get('currency_symbol')}}) <span class="text-danger">*</span>
                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                               data-bs-toggle="tooltip"
                               data-bs-title="{{ translate('Enter the amount you want to refund to the customer') }}"></i></label>
                        <input type="number" class="form-control" id="refundAmount" min="0" name="refund_amount"
                               placeholder="{{translate("Ex : 10")}}" required>
                    </div>
                    <label class="form-label">{{translate('Refund Method')}} <span class="text-danger">*</span>
                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                           data-bs-toggle="tooltip"
                           data-bs-title="{{ translate('Choose the method in which way you want to provide the refund amount') }}"></i></label>
                    <div class="border rounded border-ced4da p-3 mb-4">
                        <div class="d-flex flex-wrap gap-5">
                            <div>
                                <input type="radio" name="refund_method" id="payManually" value="manually" checked
                                       required>
                                <label class="form-check-label" for="payManually">{{translate("Pay Manually")}}</label>
                            </div>
                            <div>
                                <input type="radio" name="refund_method" id="payWallet" value="wallet" required>
                                <label class="form-check-label" for="payWallet">{{translate("Pay in Wallet")}}</label>
                            </div>
                            <div>
                                <input type="radio" name="refund_method" id="createRefundCoupon" value="coupon"
                                       required>
                                <label class="form-check-label"
                                       for="createRefundCoupon">{{translate("Create a refund Coupon")}}</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="refundNote" class="form-label">{{translate('Refund Note')}} <span
                                class="text-danger">*</span>
                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                               data-bs-toggle="tooltip"
                               data-bs-title="{{ translate('Write the message that will be displayed to the customer upon approval of their refund request') }}"></i></label>
                        <div class="character-count">
                            <textarea class="form-control character-count-field" maxlength="150"
                                      data-max-character="150" id="refundNote" rows="3" name="refund_note"
                                      placeholder="{{translate('Type a refund note')}}" required></textarea>
                            <div class="text-end">{{ translate('0/150') }}</div>
                        </div>

                    </div>
                    <div class="d-flex gap-10px justify-content-end">
                        <button class="btn btn-secondary" data-bs-dismiss="modal"
                                type="button">{{ translate('Cancel') }}</button>
                        <button class="btn btn-primary"
                                type="submit">{{ translate('Make Refund') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


{{-- Parcel Refund Modal End--}}


{{--status change modal--}}

<div class="modal fade" id="customModalForStatus"
     {{--     data-bs-backdrop="static"--}}
     {{--     data-bs-keyboard="false"--}}
     aria-modal="true" role="dialog">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-toggle="modal">
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto">
                    <div>
                        <div class="text-center">
                            <img alt="" class="mb-4" id="iconForStatus"
                                 src="">
                            <div class="swal2-icon swal2-warning swal2-animate-warning-icon d-flex"><span
                                    class="swal2-icon-text">!</span></div>
                            <h5 class="modal-title mb-3" id="titleForStatus">{{translate("Are you sure?")}}</h5>
                        </div>
                        <div class="text-center mb-4 pb-2">
                            <p id="subTitleForStatus">{{translate("Want to change status")}}</p>
                        </div>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn-primary min-w-120"
                                id="modalConfirmBtnForStatus">{{translate('Ok')}}</button>
                        <button type="button" class="btn btn--cancel min-w-120" id="modalCancelBtnForStatus">
                            {{translate('Cancel')}}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{--status change modal ends--}}


{{-- Vehicle Request Approve modal --}}
<div class="modal fade" id="vehicleRequestApprovalModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close outline-none shadow-none" data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body text-center">
                <img width="80" height="80" src="{{ asset('public/assets/admin-module/img/modal/mark.png') }}"
                     alt="">
                <h5 class="title-color mt-4 mb-2 "
                    id="vehicleRequestApprovalTitle"> {{ translate('Are you sure you want to approve the vehicle') }}?
                </h5>
                <p id="vehicleRequestApprovalSubTitle">{{ translate('Please review the information carefully before confirming.') }}
                </p>
                <form id="vehicleRequestApprovalForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="d-flex justify-content-center gap-3 mt-5">
                        <button type="button"
                                class="btn btn--cancel min-w-120 d-flex justify-content-center"
                                data-bs-dismiss="modal">{{ translate('cancel') }}</button>
                        <button type="submit" class="btn btn-primary min-w-120 d-flex justify-content-center"
                        >{{ translate('Yes, Approve') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
{{-- Vehicle Request Approve modal ends --}}

{{-- Vehicle Request Cancel modal --}}
<div class="modal fade" id="vehicleRequestDenyModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close outline-none shadow-none" data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <img width="80" height="80"
                         src="{{ asset('public/assets/admin-module/img/modal/delete.png') }}" alt="">
                    <h5 class="title-color mt-4 mb-2"> {{ translate('Are you sure you want to deny the vehicle') }}?
                    </h5>
                    <p>{{ translate('Please review the information carefully before confirming.') }}
                    </p>
                </div>
                <form id="vehicleRequestDenyForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mt-4">
                        <label for="" class="form-label"> {{ translate('Deny Note') }} <span
                                class="text-danger">*</span></label>
                        <div class="character-count d-flex flex-column">
                            <textarea class="form-control character-count-field" maxlength="150"
                                      data-max-character="150" id=""
                                      name="deny_note" rows="4" placeholder="Type a deny note for your customer"
                                      required></textarea>
                            <span class="text-end">0/150</span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-center gap-3 mt-5">
                        <button type="reset"
                                class="btn btn--cancel min-w-120 d-flex justify-content-center"
                                data-bs-dismiss="modal">{{ translate('cancel') }}</button>
                        <button type="submit" class="btn btn-primary min-w-120 d-flex justify-content-center"
                        >{{ translate('Submit') }}</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
{{-- Vehicle Request Cancel modal ends --}}


{{--Vehicle Update Cancel modal--}}
<div class="modal fade" id="vehicleUpdateDenyModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close outline-none shadow-none" data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <img width="80" height="80"
                         src="{{ asset('public/assets/admin-module/img/modal/delete.png') }}" alt="">
                    <h5 class="title-color mt-4 mb-2"> {{ translate('Are you sure you want to deny the vehicle') }}?
                    </h5>
                    <p>{{ translate('Please review the information carefully before confirming.') }}
                    </p>
                </div>
                <form id="vehicleUpdateDenyForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="d-flex justify-content-center gap-3 mt-5">
                        <button type="reset"
                                class="btn btn--cancel min-w-120 d-flex justify-content-center"
                                data-bs-dismiss="modal">{{ translate('cancel') }}</button>
                        <button type="submit" class="btn btn-primary min-w-120 d-flex justify-content-center"
                        >{{ translate('Submit') }}</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
{{-- Vehicle Update Cancel modal ends --}}


{{--deleteModal--}}
<div class="modal fade" id="deleteModal">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-toggle="modal">
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto">
                    <div>
                        <div class="text-center">
                            <img alt="" class="mb-4" id="deleteIcon"
                                 src="{{asset('public/assets/admin-module/img/modal/trash.png')}}">
                            <h5 class="modal-title mb-3" id="deleteTitle">{{translate("Are you sure?")}}</h5>
                        </div>
                        <div class="text-center mb-4 pb-2">
                            <p id="deleteSubTitle">{{translate("Want to delete")}}</p>
                        </div>
                    </div>
                    <form action="" id="deleteForm" method="POST">
                        @csrf
                        @method('DELETE')
                        <div class="btn--container justify-content-center">
                            <button type="submit" class="btn btn-danger min-w-120"
                                    id="deleteModalConfirmBtn">{{translate('Ok')}}</button>
                            <button type="button" class="btn btn-secondary min-w-120" id="deleteModalCancelBtn"
                                    data-bs-toggle="modal">
                                {{translate('Cancel')}}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


{{--safetyAlertNotificationModal--}}
<div class="modal fade" id="safetyAlertNotificationModal" aria-modal="true" role="dialog">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" id="btnClose" class="btn-close" data-bs-toggle="modal">
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto">
                    <div>
                        <div class="text-center">
                            <img alt="" class="mb-4" id="deleteIcon"
                                 src="{{asset('public/assets/admin-module/img/safety-alert-shield-modal-icon-red.svg')}}">
                            <h5 class="modal-title mb-3" id="safetyAlertNotificationTitle"></h5>
                        </div>
                        <div class="text-center mb-4 pb-2">
                            <p id="safetyAlertNotificationSubtitle"></p>
                        </div>
                    </div>
                    <div class="btn--container justify-content-center mt-3">
                        <button id="checkLater" class="btn btn--cancel min-w-120 fs-14 fw-semibold"
                        >{{ translate('Check Later') }}</button>
                        <a href=""
                           class="show-safety-alert-user-details btn btn-primary min-w-120 confirm-Toggle fs-14 fw-semibold"
                           data-user-id="">
                            {{ translate('View Alert') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{--panicAlertNotificationModal--}}
<div class="modal fade" id="panicAlertNotificationModal" aria-modal="true" role="dialog">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" id="panicBtnClose" class="btn-close" data-bs-toggle="modal">
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto">
                    <div>
                        <div class="text-center">
                            <img alt="" class="mb-4" id="panicAlertIcon"
                                 src="{{asset('public/assets/admin-module/img/safety-alert-shield-modal-icon-red.svg')}}">
                            <h5 class="modal-title mb-3 text-danger" id="panicAlertNotificationTitle">{{ translate('Emergency Panic Alert') }}</h5>
                        </div>
                        <div class="text-center mb-2">
                            <p id="panicAlertNotificationSubtitle"></p>
                        </div>
                        <div class="text-center mb-2">
                            <p><strong>{{ translate('Customer') }}:</strong> <span id="panicAlertCustomerName"></span></p>
                            <p><strong>{{ translate('Phone') }}:</strong> <span id="panicAlertCustomerPhone"></span></p>
                            <p><strong>{{ translate('Reason') }}:</strong> <span id="panicAlertReason"></span></p>
                        </div>
                        <div class="text-center mb-4 pb-2">
                            <a href="#" id="panicAlertMapLink" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-geo-alt"></i> {{ translate('View Location on Map') }}
                            </a>
                        </div>
                    </div>
                    <div class="btn--container justify-content-center mt-3">
                        <button id="panicCheckLater" class="btn btn--cancel min-w-120 fs-14 fw-semibold"
                        >{{ translate('Check Later') }}</button>
                        <a href="{{ route('admin.business.setup.safety-precaution.index', ['type' => 'precaution']) }}"
                           class="btn btn-primary min-w-120 confirm-Toggle fs-14 fw-semibold" id="panicViewAlert">
                            {{ translate('View Alert') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


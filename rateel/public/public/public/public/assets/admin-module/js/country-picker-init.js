"use strict";
function initializePhoneInput(selector, outputSelector) {
    const phoneInput = document.querySelector(selector);
    const systemDefaultCountryCode = $('.system-default-country-code');
    const phoneNumber = phoneInput.value;
    const countryCodeMatch = phoneNumber.replace(/[^0-9]/g, '');
    const initialCountry = countryCodeMatch ? `+${countryCodeMatch}` : systemDefaultCountryCode.data('value').toLowerCase();

    let phoneInputInit = window.intlTelInput(phoneInput, {
        initialCountry: initialCountry.toLowerCase(),
        showSelectedDialCode: true,
    });

    if (!phoneInputInit.selectedCountryData.dialCode) {
        phoneInputInit.destroy();
        phoneInputInit = window.intlTelInput(phoneInput, {
            initialCountry: systemDefaultCountryCode.data('value').toLowerCase(),
            showSelectedDialCode: true,
        });
    }

    function updateOutput() {
        let cleanedPhone = phoneInput.value.replace(/[^0-9]/g, '');
        if (cleanedPhone.startsWith('0')) {
            cleanedPhone = cleanedPhone.substring(1);
        }
        $(outputSelector).val('+' + phoneInputInit.selectedCountryData.dialCode + cleanedPhone);
    }

    updateOutput();

    $(selector).closest('.iti--allow-dropdown').find('.iti__country').on("click", function () {
        updateOutput();
    });

    $(selector).on("keyup keypress change", function () {
        updateOutput();
        $(selector).val(phoneInput.value.replace(/[^0-9]/g, ''));
    });
}


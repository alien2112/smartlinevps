function getCount() {
    let inputFields = document.querySelectorAll('input[name="existing_identity_images[]"]');
    let data = Array.from(inputFields).filter(input => input.value.trim() !== '').length;
    let inputFields1 = document.querySelectorAll('input[name="identity_images[]"]');
    let data1 = Array.from(inputFields1).filter(input => input.value.trim() !== '').length;
    const maxCount = parseInt(data + data1);
    if (maxCount < 5) {
        $("#multi_image_picker .upload-file__img:last-child").removeClass('d-none');
    } else {
        $("#multi_image_picker .upload-file__img:last-child").addClass('d-none');
    }
}
$("#multi_image_picker").spartanMultiImagePicker({
    fieldName: 'identity_images[]',
    maxCount: 5,
    rowHeight: '130px',
    groupClassName: 'upload-file__img upload-file__img_banner',
    placeholderImage: {
        image: window.onMultipleImageUploadBaseImage,
        width: '34px',
    },
    dropFileLabel: `
                <h6 id="dropAreaLabel" class="mt-2 fw-semibold">
                    <span class="text-info">${onMultipleImageUploadText1}</span>
                    <br>
                    ${onMultipleImageUploadText2}
            </h6>`,
    onAddRow: function (index) {
        getCount();
    },
    onRenderedPreview: function (index) {
        $("#dropAreaLabel").hide();
        $(".file_upload").on("dragenter, input", function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).find('#dropAreaLabel').hide();
            $(this).find('.spartan_image_placeholder').hide();
        });
        toastr.success(onMultipleImageUploadSuccess, {
            CloseButton: true,
            ProgressBar: true
        });
    },
    onRemoveRow: function (index) {
        getCount();
    },
    onExtensionErr: function (index, file) {
        toastr.error(onMultipleImageUploadExtensionError, {
            CloseButton: true,
            ProgressBar: true
        });
    },
    onSizeErr: function (index, file) {
        toastr.error(onMultipleImageUploadSizeError, {
            CloseButton: true,
            ProgressBar: true
        });
    }
});


// upload multiple images
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

    onRenderedPreview: function(index) {
        $("#dropAreaLabel").hide();

        $(".file_upload").on("dragenter input", function(e) {
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


    onRemoveRow: function(index) {
        if ($(".file_upload").find(".img_").length === 0) {
            $("#dropAreaLabel").show();
        }
    },

    onExtensionErr: function(index, file) {
        toastr.error(onMultipleImageUploadExtensionError, {
            CloseButton: true,
            ProgressBar: true
        });
    },

    onSizeErr: function(index, file) {
        toastr.error(onMultipleImageUploadSizeError, {
            CloseButton: true,
            ProgressBar: true
        });
    }
});
//upload multiple images ends

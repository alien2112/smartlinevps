"use strict";
$(document).ready( () => {
    $("#select-image").on("change", function () {
        let totalSize = 0;
        const images = this.files; // this refers to the input element
        const maxImageSize = 2 * 1024 * 1024; // 2MB in bytes

        // Check the total size of selected images
        for (let i = 0; i < images.length; i++) {
            totalSize += images[i].size;

            if (images[i].size > maxImageSize) {
                toastr.error(`File ${images[i].name} is too large. Maximum allowed size is 2MB.`);
                // Clear the input field if file is too large
                this.value = null;
                return; // Exit function if file exceeds size limit
            }
        }

        // If all files are valid, push them to the selectedImages array
        for (let index = 0; index < images.length; ++index) {
            selectedImages.push(images[index]);
        }

        // Call your functions
        displaySelectedImages();
        msgBtn();
    });

    function displaySelectedImages() {
        const containerImage = document.getElementById(
            "selected-image-container"
        );
        containerImage.innerHTML = "";
        selectedImages.forEach((file, index) => {
            const input = document.createElement("input");
            input.type = "file";
            input.name = `image[${index}]`;
            input.classList.add(`image-index${index}`);
            input.hidden = true;
            containerImage.appendChild(input);
            const blob = new Blob([file], { type: file.type });
            const file_obj = new File([file], file.name);
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file_obj);
            input.files = dataTransfer.files;
            console.log(dataTransfer, file_obj);
        });
        let imageArray = $(".image-array");
        imageArray.empty();
        for (let index = 0; index < selectedImages.length; ++index) {
            let fileReader = new FileReader();
            let $uploadDiv = jQuery.parseHTML(
                "<div class='upload_img_box'><span class='img-clear'><i class='tio-clear'></i></span><img src='' alt=''></div>"
            );
            console.log(fileReader);
            fileReader.onload = function () {
                $($uploadDiv).find("img").attr("src", this.result);
                let imageData = this.result;
            };
            console.log(fileReader);
            fileReader.readAsDataURL(selectedImages[index]);
            imageArray.append($uploadDiv);
            $($uploadDiv)
                .find(".img-clear")
                .on("click", function () {
                    $(this).closest(".upload_img_box").remove();
                    $(".image-index" + index).remove();
                    selectedImages.splice(selectedImages.indexOf(index), 1);
                    msgBtn()
                });
        }
    }
});

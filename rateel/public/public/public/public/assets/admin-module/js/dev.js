"use strict";

(function ($) {
    $(document).ready(function () {
        let element = document.getElementById("coordinates");
        $(".js-select").select2();
        if (element) {
            auto_grow();
        }
    });
    $(".js-select2").select2({
        dropdownParent: $("#activityLogModal"),
    });

    // character count
    function initialCharacterCount(item) {
        let str = item.val();
        let maxCharacterCount = item.data("max-character");
        let characterCount = str.length;
        if (characterCount > maxCharacterCount) {
            item.val(str.substring(0, maxCharacterCount));
            characterCount = maxCharacterCount;
        }
        item.closest(".character-count")
            .find("span")
            .text(characterCount + "/" + maxCharacterCount);
    }

    function initialCharacterCountDiv(item) {
        let str = item.val();
        let maxCharacterCount = item.data("max-character");
        let characterCount = str.length;
        if (characterCount > maxCharacterCount) {
            item.val(str.substring(0, maxCharacterCount));
            characterCount = maxCharacterCount;
        }
        item.closest(".character-count")
            .find("div")
            .text(characterCount + "/" + maxCharacterCount);
    }

    $(".character-count-field").on("keyup change", function () {
        initialCharacterCount($(this));
        initialCharacterCountDiv($(this));
    });
    $(".character-count-field").each(function () {
        initialCharacterCount($(this));
        initialCharacterCountDiv($(this));
    });

    function auto_grow() {
        if (element) {
            element.style.height = "5px";
            element.style.height = element.scrollHeight + "px";
        }
    }

    function ajax_get(route, id) {
        $.get({
            url: route,
            dataType: "json",
            data: {},
            beforeSend: function () {
            },
            success: function (response) {
                $("#" + id).html(response.template);
            },
            complete: function () {
            },
        });
    }
})(jQuery);

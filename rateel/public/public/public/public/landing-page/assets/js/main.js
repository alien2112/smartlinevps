(function ($) {
    "user strict";
    $(window).on("load", function () {
        $(".preloader").fadeOut(500);
        var img = $(".bg__img");
        img.css("background-image", function () {
            var bg = "url(" + $(this).data("img") + ")";
            var bg = `url(${$(this).data("img")})`;
            return bg;
        });
    });
    // $(window).on("load", () => {
    // 	$("#landing-loader").fadeOut(1000);
    //  });

    $(document).ready(function () {
        $(".accordion-title").on("click", function (e) {
            var element = $(this).parent(".accordion-item");
            if (element.hasClass("open")) {
                element.removeClass("open");
                element.find(".accordion-content").removeClass("open");
                element.find(".accordion-content").slideUp(200, "swing");
            } else {
                element.addClass("open");
                element.children(".accordion-content").slideDown(200, "swing");
                element
                    .siblings(".accordion-item")
                    .children(".accordion-content")
                    .slideUp(200, "swing");
                element.siblings(".accordion-item").removeClass("open");
                element
                    .siblings(".accordion-item")
                    .find(".accordion-title")
                    .removeClass("open");
                element
                    .siblings(".accordion-item")
                    .find(".accordion-content")
                    .slideUp(200, "swing");
            }
        });

        var fixed_top = $(".navbar-bottom");
        $(window).on("scroll", function () {
            if ($(this).scrollTop() > 110) {
                fixed_top.addClass("active");
            } else {
                fixed_top.removeClass("active");
            }
        });

        $(".owl-prev").html('<i class="fas fa-angle-left">');
        $(".owl-next").html('<i class="fas fa-angle-right">');

        if ($(".wow").length) {
            var wow = new WOW({
                boxClass: "wow",
                animateClass: "animated",
                offset: 0,
                mobile: true,
                live: true,
            });
            wow.init();
        }

        $(".nav-toggle").on("click", () => {
            $(".nav-toggle").toggleClass("active");
            $(".menu").toggleClass("active");
            $(".navbar-bottom-wrapper").toggleClass("rounded-0");
        });

        /* Testimonial Slider */
        var testimonial = $(".testimonial-slider")
            .on("initialized.owl.carousel changed.owl.carousel", function (e) {
                if (!e.namespace) {
                    return;
                }
                var carousel = e.relatedTarget;
                $(".slider-counter").text(
                    carousel.relative(carousel.current()) +
                        1 +
                        " / " +
                        carousel.items().length
                );
            })
            .owlCarousel({
                items: 1,
                loop: true,
                margin: 0,
                nav: false,
                mouseDrag: true,
                touchDrag: true,
                center: true,
                autoplay: true,
                speed: 1000,
                navSpeed: 1000,
                autoplaySpeed: 1000,
                smartSpeed: 1000,
                fluidSpeed: 1000,
                responsive: {
                    768: {
                        items: 3,
                    },
                },
            });
        $(".testimonial-owl-prev").on("click", function () {
            testimonial.trigger("prev.owl.carousel");
        });
        $(".testimonial-owl-next").on("click", function () {
            testimonial.trigger("next.owl.carousel");
        });
    });
})(jQuery);

const images = document.querySelectorAll("img.svg");
images.forEach(function (img) {
    const imgID = img.getAttribute("id");
    const imgClass = img.getAttribute("class");
    const imgURL = img.getAttribute("src");

    fetch(imgURL)
        .then((response) => response.text())
        .then((data) => {
            // Get the SVG tag, ignore the rest
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(data, "text/xml");
            const svg = xmlDoc.getElementsByTagName("svg")[0];

            // Add replaced image's ID to the new SVG
            if (typeof imgID !== "undefined") {
                svg.setAttribute("id", imgID);
            }
            // Add replaced image's classes to the new SVG
            if (typeof imgClass !== "undefined") {
                svg.setAttribute("class", imgClass + " replaced-svg");
            }

            // Remove any invalid XML tags as per http://validator.w3.org
            svg.removeAttribute("xmlns:a");

            // Check if the viewport is set, else we gonna set it if we can.
            if (
                !svg.getAttribute("viewBox") &&
                svg.getAttribute("height") &&
                svg.getAttribute("width")
            ) {
                svg.setAttribute(
                    "viewBox",
                    "0 0 " +
                        svg.getAttribute("height") +
                        " " +
                        svg.getAttribute("width")
                );
            }

            // Replace image with new SVG
            img.parentNode.replaceChild(svg, img);
        })
        .catch((error) => console.error(error));
});

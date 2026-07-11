/* Accessible activation for the single full-block shipment link. */
(function () {
    "use strict";

    document.addEventListener("keydown", function (event) {
        var link = event.target && event.target.closest
            ? event.target.closest("a.cpk-shipment-link")
            : null;

        if (!link || event.key !== "Enter" || event.repeat) return;

        event.preventDefault();
        event.stopPropagation();
        if (link.getAttribute("data-cpk-navigating") === "1") return;

        link.setAttribute("data-cpk-navigating", "1");
        window.location.assign(link.href);
    });
})();

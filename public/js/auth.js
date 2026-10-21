/* MobileTech Pro -- password show/hide toggle (login page) */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var toggle = document.getElementById("pwToggle");
        var input = document.getElementById("password");

        if (!toggle || !input) {
            return;
        }

        toggle.addEventListener("click", function () {
            var willShow = input.type === "password";
            input.type = willShow ? "text" : "password";
            toggle.classList.toggle("is-visible", willShow);
            toggle.setAttribute(
                "aria-label",
                willShow ? "Hide password" : "Show password"
            );
            input.focus();
        });
    });
})();

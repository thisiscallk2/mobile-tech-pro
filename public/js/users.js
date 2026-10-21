/* =====================================================================
   MobileTech Pro -- User Management interactions
   (password toggle, role-based permission defaults,
   Escape-to-close for the slide-over). No inline JS.
   ===================================================================== */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {

        /* ---- password show / hide ---------------------------------- */
        var toggle = document.getElementById("panelPwToggle");
        var input = document.getElementById("panelPassword");
        if (toggle && input) {
            toggle.addEventListener("click", function () {
                var show = input.type === "password";
                input.type = show ? "text" : "password";
                toggle.classList.toggle("is-visible", show);
                toggle.setAttribute("aria-label", show ? "Hide password" : "Show password");
                input.focus();
            });
        }

        /* ---- auto-select module permissions when the role changes --- */
        var panel = document.querySelector(".slideover-form");
        if (panel) {
            var defaults = {};
            try {
                defaults = JSON.parse(panel.getAttribute("data-role-defaults") || "{}");
            } catch (err) {
                defaults = {};
            }

            var roleInputs = panel.querySelectorAll('input[name="user_type"]');
            Array.prototype.forEach.call(roleInputs, function (radio) {
                radio.addEventListener("change", function () {
                    var allowed = defaults[radio.value] || [];
                    var boxes = panel.querySelectorAll('input[name="permissions[]"]');
                    Array.prototype.forEach.call(boxes, function (box) {
                        box.checked = allowed.indexOf(box.value) !== -1;
                    });
                });
            });
        }

        /* ---- Escape closes the slide-over -------------------------- */
        var overlay = document.querySelector(".slideover-overlay.is-open");
        if (overlay) {
            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape") {
                    window.location.href = overlay.getAttribute("href");
                }
            });
        }
    });
})();

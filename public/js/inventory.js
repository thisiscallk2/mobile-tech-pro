/* =====================================================================
   MobileTech Pro -- Inventory interactions
   Stock-bar widths, image drop zone + preview, live product preview card,
   delete confirmation, Escape-to-close. No inline JS/CSS.
   ===================================================================== */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {

        /* ---- stock progress bar widths (from data-pct) ------------- */
        var bars = document.querySelectorAll(".stock-bar-fill[data-pct]");
        Array.prototype.forEach.call(bars, function (bar) {
            bar.style.width = (parseInt(bar.getAttribute("data-pct"), 10) || 0) + "%";
        });

        /* ---- delete confirmation ----------------------------------- */
        var forms = document.querySelectorAll("form[data-confirm]");
        Array.prototype.forEach.call(forms, function (form) {
            form.addEventListener("submit", function (e) {
                if (!window.confirm(form.getAttribute("data-confirm"))) {
                    e.preventDefault();
                }
            });
        });

        /* ---- image drop zone + preview ----------------------------- */
        var dropzone = document.getElementById("dropzone");
        var input = document.getElementById("imageInput");
        var dropPreview = document.getElementById("dropPreview");
        var previewImg = document.getElementById("previewImg");

        function showImage(src) {
            if (dropPreview) {
                dropPreview.src = src;
            }
            if (dropzone) {
                dropzone.classList.add("has-image");
            }
            if (previewImg) {
                previewImg.src = src;
            }
        }

        if (dropzone && input) {
            if (dropzone.getAttribute("data-existing")) {
                dropzone.classList.add("has-image");
            }

            function readFile(file) {
                if (!file || file.type.indexOf("image/") !== 0) {
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (ev) {
                    showImage(ev.target.result);
                };
                reader.readAsDataURL(file);
            }

            input.addEventListener("change", function () {
                if (input.files && input.files[0]) {
                    readFile(input.files[0]);
                }
            });

            ["dragenter", "dragover"].forEach(function (evt) {
                dropzone.addEventListener(evt, function (e) {
                    e.preventDefault();
                    dropzone.classList.add("is-dragover");
                });
            });
            ["dragleave", "drop"].forEach(function (evt) {
                dropzone.addEventListener(evt, function (e) {
                    e.preventDefault();
                    dropzone.classList.remove("is-dragover");
                });
            });
            dropzone.addEventListener("drop", function (e) {
                var dt = e.dataTransfer;
                if (dt && dt.files && dt.files[0]) {
                    input.files = dt.files;
                    readFile(dt.files[0]);
                }
            });
        }

        /* ---- live preview card ------------------------------------- */
        var form = document.getElementById("productForm");
        if (form) {
            var nameEl = document.getElementById("pName");
            var priceEl = document.getElementById("pPrice");
            var qtyEl = document.getElementById("pQty");
            var catEl = document.getElementById("pCategory");
            var brandEl = document.getElementById("pBrand");

            var pName = document.getElementById("previewName");
            var pSub = document.getElementById("previewSub");
            var pPrice = document.getElementById("previewPrice");
            var pStock = document.getElementById("previewStock");
            var card = document.getElementById("previewCard");

            function selText(sel) {
                if (!sel || sel.selectedIndex < 0) {
                    return "";
                }
                var opt = sel.options[sel.selectedIndex];
                return (opt && opt.value) ? opt.text : "";
            }

            function money(v) {
                var n = parseFloat(v);
                return "$" + (isNaN(n) ? 0 : n).toFixed(2);
            }

            function refresh() {
                var name = nameEl ? nameEl.value.trim() : "";
                var brand = selText(brandEl);
                var cat = selText(catEl);
                var price = priceEl ? priceEl.value : "";
                var qty = qtyEl ? qtyEl.value : "";

                if (pName) {
                    pName.textContent = name || "Product name";
                }
                if (pSub) {
                    pSub.textContent = (brand || "Brand") + " \u00b7 " + (cat || "Category");
                }
                if (pPrice) {
                    pPrice.textContent = money(price);
                }
                if (pStock) {
                    pStock.textContent = (qty === "" ? 0 : parseInt(qty, 10)) + " in stock";
                }

                var valid = name !== "" && price !== "" && parseFloat(price) >= 0 && qty !== "";
                if (card) {
                    card.classList.toggle("is-valid", valid);
                }
            }

            [nameEl, priceEl, qtyEl, catEl, brandEl].forEach(function (el) {
                if (el) {
                    el.addEventListener("input", refresh);
                    el.addEventListener("change", refresh);
                }
            });
            refresh();
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

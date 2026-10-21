"use strict";

(function () {
    var cart = [];
    var debounceTimer = null;
    var BASE = "";
    var BASE_URL = "";
    var couponData = null;
    var discount = 0;
    var tax = 0;
    var total = 0;
    var paymentMethod = "cash";
    var selectedCustomer = null;
    var customerSearchTimer = null;

    var gridEl, searchEl, catBtns, cartItemsEl, cartCountEl;
    var toastEl, clearBtn;
    var couponInput, applyCouponBtn, couponMsg, discountRow, summaryDiscount;
    var summarySubtotal, summaryTax, summaryTotal;
    var paymentMethods, processPaymentBtn, processTotal;
    var holdOrderBtn, printQuoteBtn;
    var receiptOverlay, receiptContent;
    var customerSearch, customerResults, customerSelected;
    var newCustomerBtn, removeCustomerBtn, customerModal;

    document.addEventListener("DOMContentLoaded", function () {
        gridEl = document.getElementById("posGrid");
        searchEl = document.getElementById("posSearch");
        catBtns = document.querySelectorAll(".pos-cat-btn");
        cartItemsEl = document.getElementById("cartItems");
        cartCountEl = document.getElementById("cartCount");
        toastEl = document.getElementById("posToast");
        clearBtn = document.getElementById("clearCartBtn");

        couponInput = document.getElementById("couponInput");
        applyCouponBtn = document.getElementById("applyCouponBtn");
        couponMsg = document.getElementById("couponMsg");
        discountRow = document.getElementById("discountRow");
        summaryDiscount = document.getElementById("summaryDiscount");
        summarySubtotal = document.getElementById("summarySubtotal");
        summaryTax = document.getElementById("summaryTax");
        summaryTotal = document.getElementById("summaryTotal");
        paymentMethods = document.getElementById("paymentMethods");
        processPaymentBtn = document.getElementById("processPaymentBtn");
        processTotal = document.getElementById("processTotal");
        holdOrderBtn = document.getElementById("holdOrderBtn");
        printQuoteBtn = document.getElementById("printQuoteBtn");
        receiptOverlay = document.getElementById("receiptOverlay");
        receiptContent = document.getElementById("receiptContent");

        customerSearch = document.getElementById("customerSearch");
        customerResults = document.getElementById("customerResults");
        customerSelected = document.getElementById("customerSelected");
        newCustomerBtn = document.getElementById("newCustomerBtn");
        removeCustomerBtn = document.getElementById("removeCustomerBtn");
        customerModal = document.getElementById("customerModal");

        var urls = window.__posData || {};
        BASE = {
            search: urls.searchUrl || "",
            add: urls.cartAddUrl || "",
            update: urls.cartUpdateUrl || "",
            remove: urls.cartRemoveUrl || "",
            get: urls.cartGetUrl || "",
            coupon: urls.couponUrl || "",
            process: urls.processUrl || "",
            customerSearch: urls.customerSearchUrl || "",
            customerCreate: urls.customerQuickCreateUrl || "",
            customerSet: urls.customerSetUrl || ""
        };
        BASE_URL = urls.baseUrl || "";

        var posData = window.__pos;
        if (posData) {
            cart = posData.cart || [];
            couponData = posData.coupon || null;
            discount = posData.discount || 0;
            tax = posData.tax || 0;
            total = posData.total || 0;
            selectedCustomer = posData.customer || null;
            refreshCartUI();
        }

        renderProducts(urls.initialProducts || []);

        if (searchEl) {
            searchEl.addEventListener("input", function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    fetchProducts();
                }, 300);
            });
        }

        catBtns.forEach(function (btn) {
            btn.addEventListener("click", function () {
                catBtns.forEach(function (b) { b.classList.remove("is-active"); });
                btn.classList.add("is-active");
                fetchProducts();
            });
        });

        if (gridEl) {
            gridEl.addEventListener("click", function (e) {
                var card = e.target.closest(".pos-card");
                if (!card) return;
                if (card.classList.contains("pos-card--oos")) return;
                if (!e.target.classList.contains("pos-card-add")) return;

                var pid = parseInt(card.getAttribute("data-product-id"), 10);
                var name = card.getAttribute("data-name") || "";
                var price = parseFloat(card.getAttribute("data-price")) || 0;
                var sku = card.getAttribute("data-sku") || "";
                var stock = parseInt(card.getAttribute("data-stock"), 10) || 0;
                var img = card.getAttribute("data-image") || "";
                addToCart(pid, name, price, sku, stock, img);
            });
        }

        if (cartItemsEl) {
            cartItemsEl.addEventListener("click", function (e) {
                var btn = e.target.closest("button");
                if (!btn) return;
                var itemEl = btn.closest(".pos-cart-item");
                if (!itemEl) return;
                var pid = parseInt(itemEl.getAttribute("data-product-id"), 10);

                if (btn.classList.contains("qty-plus")) {
                    changeQty(pid, 1);
                } else if (btn.classList.contains("qty-minus")) {
                    changeQty(pid, -1);
                } else if (btn.classList.contains("pos-cart-item-remove") || btn.closest(".pos-cart-item-remove")) {
                    removeFromCart(pid);
                }
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener("click", function () {
                if (cart.length === 0) return;
                var removePromises = cart.map(function (item) {
                    return fetch(BASE.remove, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ product_id: item.product_id })
                    }).then(function (r) { return r.json(); });
                });
                Promise.all(removePromises).then(function () {
                    cart = [];
                    refreshCartUI();
                    showToast("Cart cleared.", "success");
                }).catch(function () {
                    showToast("Could not clear cart.", "error");
                });
            });
        }

        if (applyCouponBtn) {
            applyCouponBtn.addEventListener("click", function () {
                var code = couponInput ? couponInput.value.trim() : "";
                applyCoupon(code);
            });
        }

        if (couponInput) {
            couponInput.addEventListener("keydown", function (e) {
                if (e.key === "Enter") {
                    e.preventDefault();
                    var code = couponInput.value.trim();
                    applyCoupon(code);
                }
            });
        }

        if (paymentMethods) {
            paymentMethods.addEventListener("click", function (e) {
                var btn = e.target.closest(".pos-pay-btn");
                if (!btn) return;
                var method = btn.getAttribute("data-method");
                if (!method) return;
                paymentMethod = method;
                paymentMethods.querySelectorAll(".pos-pay-btn").forEach(function (b) { b.classList.remove("is-active"); });
                btn.classList.add("is-active");
            });
        }

        if (processPaymentBtn) {
            processPaymentBtn.addEventListener("click", function () {
                processPayment();
            });
        }

        if (holdOrderBtn) {
            holdOrderBtn.addEventListener("click", function () {
                showToast("Hold Order coming in a future round.", "error");
            });
        }

        if (printQuoteBtn) {
            printQuoteBtn.addEventListener("click", function () {
                if (cart.length === 0) {
                    showToast("Cart is empty.", "error");
                    return;
                }
                printQuote();
            });
        }

        if (receiptOverlay) {
            receiptOverlay.addEventListener("click", function (e) {
                if (e.target === receiptOverlay) {
                    closeReceipt();
                }
            });
        }

        if (customerSearch) {
            customerSearch.addEventListener("input", function () {
                clearTimeout(customerSearchTimer);
                var q = customerSearch.value.trim();
                if (q.length < 2) {
                    if (customerResults) customerResults.hidden = true;
                    return;
                }
                customerSearchTimer = setTimeout(function () {
                    searchCustomers(q);
                }, 250);
            });
        }

        if (customerResults) {
            customerResults.addEventListener("click", function (e) {
                var row = e.target.closest("[data-customer-id]");
                if (!row) return;
                var cid = parseInt(row.getAttribute("data-customer-id"), 10);
                selectCustomer(cid);
            });
        }

        if (newCustomerBtn) {
            newCustomerBtn.addEventListener("click", function () {
                openCustomerModal();
            });
        }

        if (removeCustomerBtn) {
            removeCustomerBtn.addEventListener("click", function () {
                deselectCustomer();
            });
        }

        if (customerModal) {
            document.getElementById("closeCustomerModal").addEventListener("click", closeCustomerModal);
            document.getElementById("cancelCustomerBtn").addEventListener("click", closeCustomerModal);
            document.getElementById("saveCustomerBtn").addEventListener("click", saveNewCustomer);
            customerModal.addEventListener("click", function (e) {
                if (e.target === customerModal) closeCustomerModal();
            });
        }

        refreshSelectedCustomer();
    });

    function fetchProducts() {
        var q = searchEl ? searchEl.value.trim() : "";
        var catId = "";
        var activeCat = document.querySelector(".pos-cat-btn.is-active");
        if (activeCat) {
            catId = activeCat.getAttribute("data-category") || "";
        }

        var params = new URLSearchParams();
        if (q) { params.set("q", q); }
        if (catId) { params.set("category", catId); }

        var url = BASE.search + "?" + params.toString();

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.products) {
                    renderProducts(data.products);
                }
            })
            .catch(function () {
                showToast("Search failed.", "error");
            });
    }

    function renderProducts(products) {
        if (!gridEl) return;

        if (!products || products.length === 0) {
            gridEl.innerHTML = "<p class='pos-empty'>No products found.</p>";
            return;
        }

        var html = "";
        products.forEach(function (p) {
            var outOfStock = p.quantity <= 0;
            var imgHtml = "";
            if (p.image_path) {
                imgHtml = '<img src="' + escAttr(BASE_URL + "/" + p.image_path) + '" alt="" loading="lazy">';
            } else {
                imgHtml = '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
                    + '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8"/></svg>';
            }

            html += '<div class="pos-card' + (outOfStock ? ' pos-card--oos' : '') + '"'
                + ' data-product-id="' + p.product_id + '"'
                + ' data-name="' + escAttr(p.name) + '"'
                + ' data-price="' + p.selling_price + '"'
                + ' data-sku="' + escAttr(p.sku) + '"'
                + ' data-stock="' + p.quantity + '"'
                + ' data-image="' + escAttr(p.image_path || "") + '">';
            html += '<div class="pos-card-img">' + imgHtml;
            if (outOfStock) {
                html += '<span class="pos-card-badge">OOS</span>';
            }
            html += '</div>';
            html += '<div class="pos-card-body">';
            html += '<span class="pos-card-name">' + escHtml(p.name) + '</span>';
            html += '<span class="pos-card-price">$' + numberFormat(p.selling_price) + '</span>';
            html += '</div>';
            if (!outOfStock) {
                html += '<button class="pos-card-add" type="button" aria-label="Add ' + escAttr(p.name) + ' to cart">+</button>';
            }
            html += '</div>';
        });

        gridEl.innerHTML = html;
    }

    function addToCart(pid, name, price, sku, stock, img) {
        if (stock <= 0) {
            showToast("This product is out of stock.", "error");
            return;
        }

        fetch(BASE.add, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ product_id: pid })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    cart = data.cart || [];
                    updateCheckoutState(data);
                    refreshCartUI();
                    showToast(name + " added to cart.", "success");
                } else {
                    showToast(data.error || "Could not add item.", "error");
                }
            })
            .catch(function () {
                showToast("Could not add item.", "error");
            });
    }

    function changeQty(pid, delta) {
        var existing = cart.find(function (item) { return item.product_id === pid; });
        if (!existing) return;

        var newQty = existing.quantity + delta;
        if (newQty < 1) {
            newQty = 1;
        }

        fetch(BASE.update, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ product_id: pid, quantity: newQty })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    cart = data.cart || [];
                    updateCheckoutState(data);
                    refreshCartUI();
                } else {
                    showToast(data.error || "Could not update quantity.", "error");
                }
            })
            .catch(function () {
                showToast("Could not update quantity.", "error");
            });
    }

    function removeFromCart(pid) {
        fetch(BASE.remove, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ product_id: pid })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    cart = data.cart || [];
                    updateCheckoutState(data);
                    refreshCartUI();
                }
            })
            .catch(function () {
                showToast("Could not remove item.", "error");
            });
    }

    function refreshCartUI() {
        var count = cart.length;
        if (cartCountEl) {
            cartCountEl.textContent = count + " item" + (count !== 1 ? "s" : "");
        }

        if (!cartItemsEl) return;

        if (count === 0) {
            cartItemsEl.innerHTML = '<p class="pos-cart-empty">Your cart is empty.</p>';
        } else {
            var html = "";
            cart.forEach(function (item) {
                var lineTotal = (item.selling_price || 0) * (item.quantity || 0);
                var imgHtml = "";
                if (item.image_path) {
                    imgHtml = '<img src="' + escAttr(BASE_URL + "/" + item.image_path) + '" alt="" loading="lazy">';
                } else {
                    imgHtml = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                        + '<rect x="2" y="2" width="20" height="20" rx="5"/></svg>';
                }

                html += '<div class="pos-cart-item" data-product-id="' + item.product_id + '">';
                html += '<div class="pos-cart-item-img">' + imgHtml + '</div>';
                html += '<div class="pos-cart-item-info">';
                html += '<span class="pos-cart-item-name">' + escHtml(item.name || "") + '</span>';
                html += '<span class="pos-cart-item-price">$' + numberFormat(item.selling_price || 0) + '</span>';
                html += '<div class="pos-cart-item-qty">';
                html += '<button class="qty-btn qty-minus" type="button" aria-label="Decrease quantity">&minus;</button>';
                html += '<span class="qty-val">' + (item.quantity || 1) + '</span>';
                html += '<button class="qty-btn qty-plus" type="button" aria-label="Increase quantity">+</button>';
                html += '</div></div>';
                html += '<div class="pos-cart-item-right">';
                html += '<span class="pos-cart-item-total">$' + numberFormat(lineTotal) + '</span>';
                html += '<button class="pos-cart-item-remove" type="button" aria-label="Remove item" title="Remove">';
                html += '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
                html += '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
                html += '</button></div>';
                html += '</div>';
            });
            cartItemsEl.innerHTML = html;
        }

        updateOrderSummary();
    }

    function updateCheckoutState(data) {
        if (data.hasOwnProperty("coupon")) {
            couponData = data.coupon || null;
        }
        if (data.hasOwnProperty("discount")) {
            discount = data.discount || 0;
        }
        if (data.hasOwnProperty("tax")) {
            tax = data.tax || 0;
        }
        if (data.hasOwnProperty("total")) {
            total = data.total || 0;
        }
    }

    function updateOrderSummary() {
        var localSubtotal = 0;
        cart.forEach(function (item) {
            localSubtotal += (item.selling_price || 0) * (item.quantity || 0);
        });

        if (summarySubtotal) {
            summarySubtotal.textContent = "$" + numberFormat(localSubtotal);
        }

        if (discountRow && summaryDiscount) {
            if (discount > 0) {
                discountRow.hidden = false;
                summaryDiscount.textContent = "- $" + numberFormat(discount);
            } else {
                discountRow.hidden = true;
            }
        }

        if (summaryTax) {
            summaryTax.textContent = "$" + numberFormat(tax);
        }

        if (summaryTotal) {
            summaryTotal.textContent = "$" + numberFormat(total);
        }

        if (processTotal) {
            processTotal.textContent = numberFormat(total);
        }

        if (processPaymentBtn) {
            processPaymentBtn.disabled = cart.length === 0;
        }
    }

    function applyCoupon(code) {
        if (cart.length === 0) {
            showToast("Cart is empty.", "error");
            return;
        }

        fetch(BASE.coupon, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ code: code })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    updateCheckoutState(data);
                    updateOrderSummary();
                    if (couponMsg) {
                        if (code === "" && !data.coupon) {
                            couponMsg.textContent = "Coupon removed.";
                            couponMsg.className = "pos-coupon-msg";
                        } else if (data.coupon) {
                            var typeLabel = data.coupon.discount_type === "percent"
                                ? data.coupon.value + "%"
                                : "$" + numberFormat(data.coupon.value);
                            couponMsg.textContent = "Coupon \"" + data.coupon.code + "\" applied (" + typeLabel + ").";
                            couponMsg.className = "pos-coupon-msg pos-coupon-msg--ok";
                            if (couponInput) {
                                couponInput.value = data.coupon.code;
                            }
                        }
                    }
                } else {
                    couponData = null;
                    discount = 0;
                    tax = 0;
                    total = 0;
                    updateOrderSummary();
                    if (couponMsg) {
                        couponMsg.textContent = data.error || "Invalid coupon.";
                        couponMsg.className = "pos-coupon-msg pos-coupon-msg--error";
                    }
                }
            })
            .catch(function () {
                showToast("Could not apply coupon.", "error");
            });
    }

    function processPayment() {
        if (cart.length === 0) {
            showToast("Cart is empty.", "error");
            return;
        }

        if (processPaymentBtn) {
            processPaymentBtn.disabled = true;
            processPaymentBtn.textContent = "Processing...";
        }

        var payload = { payment_method: paymentMethod };
        if (selectedCustomer && selectedCustomer.customer_id) {
            payload.customer_id = selectedCustomer.customer_id;
        }

        fetch(BASE.process, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    cart = [];
                    couponData = null;
                    discount = 0;
                    tax = 0;
                    total = 0;
                    selectedCustomer = null;
                    refreshCartUI();
                    refreshSelectedCustomer();
                    if (couponInput) {
                        couponInput.value = "";
                    }
                    if (couponMsg) {
                        couponMsg.textContent = "";
                        couponMsg.className = "pos-coupon-msg";
                    }
                    renderReceipt(data);
                } else {
                    showToast(data.error || "Payment failed.", "error");
                    if (processPaymentBtn) {
                        processPaymentBtn.disabled = false;
                        processPaymentBtn.innerHTML = 'Process Payment &mdash; $<span id="processTotal">' + numberFormat(total) + '</span>';
                        processTotal = document.getElementById("processTotal");
                    }
                }
            })
            .catch(function () {
                showToast("Payment failed. Please try again.", "error");
                if (processPaymentBtn) {
                    processPaymentBtn.disabled = false;
                    processPaymentBtn.innerHTML = 'Process Payment &mdash; $<span id="processTotal">' + numberFormat(total) + '</span>';
                    processTotal = document.getElementById("processTotal");
                }
            });
    }

    function renderReceipt(data) {
        var s = data.sale || {};
        var store = data.store || {};
        var rcp = data.receipt || {};
        var items = s.items || [];
        var paymentMethodLabel = { "cash": "Cash", "card": "Card", "bank_transfer": "Bank Transfer", "mobile": "Mobile Payment" }[s.payment_method] || s.payment_method;

        var html = '<div class="receipt-body">';

        html += '<div class="receipt-icon-wrap">';
        html += '<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
        html += '</div>';
        html += '<h2 class="receipt-title">Payment Successful</h2>';

        html += '<div class="receipt-header-info">';
        html += '<span class="receipt-number">' + escHtml(rcp.receipt_number || s.invoice_no || "") + '</span>';
        html += '<span class="receipt-badge">Paid</span>';
        html += '</div>';

        html += '<div class="receipt-store">';
        html += '<strong>' + escHtml(store.name || "MobileTech Pro") + '</strong>';
        html += '<span>' + escHtml(store.address || "") + '</span>';
        html += '<span>Phone: ' + escHtml(store.phone || "") + ' &bull; Email: ' + escHtml(store.email || "") + '</span>';
        html += '</div>';

        html += '<div class="receipt-meta">';
        html += '<div class="receipt-meta-row"><span>Date &amp; Time</span><span>' + escHtml(s.created_at || "") + '</span></div>';
        html += '<div class="receipt-meta-row"><span>Cashier</span><span>' + escHtml(s.cashier_name || "") + '</span></div>';
        html += '<div class="receipt-meta-row"><span>Customer</span><span>' + escHtml(s.customer_name || "Walk-in Customer") + '</span></div>';
        html += '<div class="receipt-meta-row"><span>Payment Method</span><span>' + escHtml(paymentMethodLabel) + '</span></div>';
        html += '</div>';

        html += '<table class="receipt-items"><thead><tr><th>Item</th><th>SKU</th><th class="r-qty">Qty</th><th class="r-price">Price</th><th class="r-amount">Amount</th></tr></thead><tbody>';
        items.forEach(function (item) {
            html += '<tr>';
            html += '<td>' + escHtml(item.product_name || "&mdash;") + '</td>';
            html += '<td>' + escHtml(item.sku || "") + '</td>';
            html += '<td class="r-qty">' + (item.quantity || 1) + '</td>';
            html += '<td class="r-price">$' + numberFormat(item.unit_price || 0) + '</td>';
            html += '<td class="r-amount">$' + numberFormat(item.line_total || 0) + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';

        html += '<div class="receipt-totals">';
        html += '<div class="receipt-totals-row"><span>Subtotal</span><span>$' + numberFormat(s.subtotal || 0) + '</span></div>';
        if ((s.discount || 0) > 0) {
            html += '<div class="receipt-totals-row"><span>Discount</span><span>- $' + numberFormat(s.discount || 0) + '</span></div>';
        }
        html += '<div class="receipt-totals-row"><span>Tax (10%)</span><span>$' + numberFormat(s.tax || 0) + '</span></div>';
        html += '<div class="receipt-totals-row receipt-total-row"><span>Total</span><span>$' + numberFormat(s.total || 0) + '</span></div>';
        html += '</div>';

        html += '<div class="receipt-actions">';
        html += '<button class="btn-cyan" type="button" id="printReceiptBtn">Print</button>';
        html += '<button class="btn-outline" type="button" id="closeReceiptBtn">Close</button>';
        html += '</div>';

        html += '</div>';

        if (receiptContent) {
            receiptContent.innerHTML = html;
        }
        if (receiptOverlay) {
            receiptOverlay.hidden = false;
        }

        document.getElementById("printReceiptBtn").addEventListener("click", function () {
            window.print();
        });
        document.getElementById("closeReceiptBtn").addEventListener("click", function () {
            closeReceipt();
        });
    }

    function closeReceipt() {
        if (receiptOverlay) {
            receiptOverlay.hidden = true;
        }
        if (receiptContent) {
            receiptContent.innerHTML = "";
        }
    }

    function printQuote() {
        var localSubtotal = 0;
        cart.forEach(function (item) {
            localSubtotal += (item.selling_price || 0) * (item.quantity || 0);
        });

        var win = window.open("", "_blank", "width=400,height=600");
        if (!win) return;

        var itemsHtml = "";
        cart.forEach(function (item) {
            var line = (item.selling_price || 0) * (item.quantity || 0);
            itemsHtml += '<tr><td>' + escHtml(item.name || "") + '</td><td>' + escHtml(item.sku || "") + '</td>'
                + '<td>' + (item.quantity || 1) + '</td><td>$' + numberFormat(item.selling_price || 0) + '</td>'
                + '<td>$' + numberFormat(line) + '</td></tr>';
        });

        var discHtml = "";
        if (discount > 0) {
            discHtml = '<div class="sum-row"><span>Discount</span><span>- $' + numberFormat(discount) + '</span></div>';
        }

        win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Quote</title>'
            + '<link rel="stylesheet" href="' + BASE_URL + '/css/pos-print.css' + '"></head><body class="print-quote">'
            + '<div class="receipt-body">'
            + '<h2 style="text-align:center;margin-top:0">Quote</h2>'
            + '<div class="receipt-store">'
            + '<strong>MobileTech Pro</strong>'
            + '<span>No. 12, Yangon, Myanmar</span>'
            + '<span>Phone: 0100000001 &bull; Email: info@mobiletechpro.test</span>'
            + '</div>'
            + '<div class="receipt-meta">'
            + '<div class="receipt-meta-row"><span>Date</span><span>' + new Date().toLocaleString() + '</span></div>'
            + '</div>'
            + '<table class="receipt-items"><thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th>Price</th><th>Amount</th></tr></thead><tbody>'
            + itemsHtml
            + '</tbody></table>'
            + '<div class="receipt-totals">'
            + '<div class="receipt-totals-row"><span>Subtotal</span><span>$' + numberFormat(localSubtotal) + '</span></div>'
            + discHtml
            + '<div class="receipt-totals-row"><span>Tax (10%)</span><span>$' + numberFormat(tax) + '</span></div>'
            + '<div class="receipt-totals-row receipt-total-row"><span>Total</span><span>$' + numberFormat(total) + '</span></div>'
            + '</div>'
            + '<p style="text-align:center;margin-top:16px"><em>This is a quote, not a paid invoice.</em></p>'
            + '<div style="text-align:center;margin-top:10px">'
            + '<button onclick="window.print()" style="padding:8px 20px">Print</button>'
            + '</div>'
            + '</div></body></html>');
        win.document.close();
    }

    function searchCustomers(q) {
        fetch(BASE.customerSearch + "?q=" + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!customerResults) return;
                var customers = data.customers || [];
                if (customers.length === 0) {
                    customerResults.innerHTML = '<p class="pos-customer-no-results">No customers found.</p>';
                    customerResults.hidden = false;
                    return;
                }
                var html = "";
                customers.forEach(function (c) {
                    html += '<div class="pos-customer-result" data-customer-id="' + c.customer_id + '">';
                    html += '<strong>' + escHtml(c.full_name) + '</strong>';
                    html += '<span>' + escHtml(c.phone || c.email || "—") + '</span>';
                    html += '</div>';
                });
                customerResults.innerHTML = html;
                customerResults.hidden = false;
            });
    }

    function selectCustomer(customerId) {
        fetch(BASE.customerSet, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ customer_id: customerId })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    selectedCustomer = data.customer || null;
                    refreshSelectedCustomer();
                    if (customerSearch) customerSearch.value = "";
                    if (customerResults) customerResults.hidden = true;
                }
            });
    }

    function deselectCustomer() {
        fetch(BASE.customerSet, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ customer_id: 0 })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    selectedCustomer = null;
                    refreshSelectedCustomer();
                }
            });
    }

    function refreshSelectedCustomer() {
        if (!customerSelected) return;
        if (selectedCustomer) {
            customerSelected.hidden = false;
            var nameEl = document.getElementById("selectedCustomerName");
            var infoEl = document.getElementById("selectedCustomerInfo");
            if (nameEl) nameEl.textContent = selectedCustomer.full_name || "";
            if (infoEl) infoEl.textContent = selectedCustomer.phone || selectedCustomer.email || "No contact info";
        } else {
            customerSelected.hidden = true;
        }
    }

    function openCustomerModal() {
        if (!customerModal) return;
        document.getElementById("newCustomerName").value = "";
        document.getElementById("newCustomerPhone").value = "";
        document.getElementById("newCustomerEmail").value = "";
        document.getElementById("customerModalError").hidden = true;
        customerModal.hidden = false;
    }

    function closeCustomerModal() {
        if (customerModal) customerModal.hidden = true;
    }

    function saveNewCustomer() {
        var name = document.getElementById("newCustomerName").value.trim();
        if (!name) {
            var err = document.getElementById("customerModalError");
            err.textContent = "Full name is required.";
            err.hidden = false;
            return;
        }
        var phone = document.getElementById("newCustomerPhone").value.trim();
        var email = document.getElementById("newCustomerEmail").value.trim();

        fetch(BASE.customerCreate, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ full_name: name, phone: phone, email: email })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    selectedCustomer = data.customer || null;
                    refreshSelectedCustomer();
                    closeCustomerModal();
                    showToast("Customer \"" + data.customer.full_name + "\" created.", "success");
                } else {
                    var err = document.getElementById("customerModalError");
                    err.textContent = data.error || "Could not create customer.";
                    err.hidden = false;
                }
            })
            .catch(function () {
                var err = document.getElementById("customerModalError");
                err.textContent = "Could not create customer.";
                err.hidden = false;
            });
    }

    function showToast(msg, type) {
        if (!toastEl) return;
        toastEl.textContent = msg;
        toastEl.className = "pos-toast pos-toast--" + (type || "error") + " is-visible";
        clearTimeout(toastEl._timeout);
        toastEl._timeout = setTimeout(function () {
            toastEl.classList.remove("is-visible");
        }, 2500);
    }

    function escHtml(s) {
        var d = document.createElement("div");
        if (typeof s === "string") {
            d.textContent = s;
        } else {
            d.textContent = String(s || "");
        }
        return d.innerHTML;
    }

    function escAttr(s) {
        return String(s || "").replace(/&/g, "&amp;").replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }

    function numberFormat(v) {
        return parseFloat(v).toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
})();

"use strict";

(function () {
    var draggedCard = null;
    var MOVE_URL = "";
    var NOTE_URL = "";

    document.addEventListener("DOMContentLoaded", function () {
        var board = window.__board || {};
        MOVE_URL = board.moveUrl || "";
        NOTE_URL = board.noteUrl || "";

        initCreateJobPage();
        initDetailPage();

        var boardCols = document.getElementById("boardColumns");
        if (!boardCols) return;

        boardCols.addEventListener("dragstart", function (e) {
            draggedCard = e.target.closest(".job-card");
            if (!draggedCard) return;
            draggedCard.classList.add("is-dragging");
            e.dataTransfer.effectAllowed = "move";
            e.dataTransfer.setData("text/plain", draggedCard.getAttribute("data-repair-id") || "");
        });

        boardCols.addEventListener("dragend", function () {
            if (draggedCard) {
                draggedCard.classList.remove("is-dragging");
                draggedCard = null;
            }
            document.querySelectorAll(".board-column").forEach(function (col) {
                col.classList.remove("drag-over");
            });
        });

        boardCols.addEventListener("dragover", function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
            var col = e.target.closest(".board-column");
            if (col) col.classList.add("drag-over");
        });

        boardCols.addEventListener("dragleave", function (e) {
            var col = e.target.closest(".board-column");
            if (col && !col.contains(e.relatedTarget)) {
                col.classList.remove("drag-over");
            }
        });

        boardCols.addEventListener("drop", function (e) {
            e.preventDefault();
            var col = e.target.closest(".board-column");
            if (col) col.classList.remove("drag-over");
            if (!draggedCard) return;

            var targetColumn = col ? col.getAttribute("data-column") : null;
            if (!targetColumn) return;

            var sourceColumn = draggedCard.getAttribute("data-column");
            if (sourceColumn === targetColumn) return;

            var repairId = parseInt(draggedCard.getAttribute("data-repair-id"), 10);
            if (!repairId) return;

            fetch(MOVE_URL, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ repair_id: repairId, column: targetColumn })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        var targetBody = col ? col.querySelector(".board-column-body") : null;
                        if (targetBody) {
                            draggedCard.setAttribute("data-column", targetColumn);
                            targetBody.appendChild(draggedCard);
                            updateColumnCounts();
                            updateDoneButton(repairId, targetColumn);
                        }
                    } else {
                        alert(data.error || "Could not move job.");
                    }
                })
                .catch(function () {
                    alert("Could not move job.");
                });
        });

        boardCols.addEventListener("click", function (e) {
            var btn = e.target.closest("button[data-action]");
            if (!btn) return;

            var action = btn.getAttribute("data-action");
            var repairId = parseInt(btn.getAttribute("data-repair-id"), 10);

            if (action === "done") {
                var nextCol = btn.getAttribute("data-next-column");
                moveJob(repairId, nextCol);
            } else if (action === "note") {
                toggleNoteForm(repairId);
            } else if (action === "save-note") {
                saveNote(repairId);
            } else if (action === "cancel-note") {
                toggleNoteForm(repairId);
            }
        });

        var filterTech = document.getElementById("filterTechnician");
        var filterSearch = document.getElementById("filterSearch");
        var urgencyPills = document.getElementById("urgencyPills");

        if (filterTech) {
            filterTech.addEventListener("change", applyFilters);
        }
        if (filterSearch) {
            var searchTimer = null;
            filterSearch.addEventListener("input", function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(applyFilters, 300);
            });
            filterSearch.addEventListener("keydown", function (e) {
                if (e.key === "Enter") {
                    clearTimeout(searchTimer);
                    applyFilters();
                }
            });
        }
        if (urgencyPills) {
            urgencyPills.addEventListener("click", function (e) {
                var pill = e.target.closest(".urgency-pill");
                if (!pill) return;
                document.querySelectorAll(".urgency-pill").forEach(function (b) { b.classList.remove("is-active"); });
                pill.classList.add("is-active");
                applyFilters();
            });
        }
    });

    function applyFilters() {
        var board = window.__board || {};
        var params = new URLSearchParams();
        var tech = document.getElementById("filterTechnician");
        var search = document.getElementById("filterSearch");
        var urgency = document.querySelector(".urgency-pill.is-active");
        if (tech && tech.value) params.set("technician", tech.value);
        if (urgency && urgency.getAttribute("data-urgency")) params.set("urgency", urgency.getAttribute("data-urgency"));
        if (search && search.value.trim()) params.set("search", search.value.trim());
        window.location = (board.boardUrl || "") + (params.toString() ? "?" + params.toString() : "");
    }

    function initCreateJobPage() {
        var custSearch = document.getElementById("custSearch");
        var custResults = document.getElementById("custResults");
        var selectedCust = document.getElementById("selectedCust");
        var customerIdInput = document.getElementById("customerIdInput");
        var searchTimer = null;
        var createData = window.__createJob || {};

        if (!custSearch) return;

        custSearch.addEventListener("input", function () {
            clearTimeout(searchTimer);
            var q = custSearch.value.trim();
            if (q.length < 2) {
                if (custResults) custResults.hidden = true;
                return;
            }
            searchTimer = setTimeout(function () {
                fetch(createData.searchUrl + "?q=" + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!custResults) return;
                        var customers = data.customers || [];
                        if (customers.length === 0) {
                            custResults.innerHTML = '<p class="cj-no-results">No customers found.</p>';
                            custResults.hidden = false;
                            return;
                        }
                        var html = "";
                        customers.forEach(function (c) {
                            html += '<div class="cj-result" data-customer-id="' + c.customer_id + '"'
                                + ' data-name="' + escAttr(c.full_name) + '"'
                                + ' data-phone="' + escAttr(c.phone || "") + '"'
                                + ' data-email="' + escAttr(c.email || "") + '">'
                                + '<strong>' + escHtml(c.full_name) + '</strong>'
                                + '<span>' + escHtml(c.phone || c.email || "—") + '</span>'
                                + '</div>';
                        });
                        custResults.innerHTML = html;
                        custResults.hidden = false;
                    });
            }, 250);
        });

        if (custResults) {
            custResults.addEventListener("click", function (e) {
                var row = e.target.closest(".cj-result");
                if (!row) return;
                var cid = row.getAttribute("data-customer-id");
                var name = row.getAttribute("data-name");
                var phone = row.getAttribute("data-phone");
                var email = row.getAttribute("data-email");

                if (customerIdInput) customerIdInput.value = cid;
                if (selectedCust) selectedCust.classList.add("is-visible");
                var nameEl = document.getElementById("selectedCustName");
                if (nameEl) nameEl.textContent = name;
                if (custSearch) custSearch.value = "";
                if (custResults) custResults.hidden = true;

                var newCustFields = document.getElementById("newCustFields");
                if (newCustFields) newCustFields.hidden = true;
            });
        }

        var clearCustBtn = document.getElementById("clearCustBtn");
        if (clearCustBtn) {
            clearCustBtn.addEventListener("click", function () {
                if (customerIdInput) customerIdInput.value = "";
                if (selectedCust) selectedCust.classList.remove("is-visible");
            });
        }

        var newCustToggle = document.getElementById("newCustToggle");
        if (newCustToggle) {
            newCustToggle.addEventListener("click", function () {
                var fields = document.getElementById("newCustFields");
                if (!fields) return;
                fields.hidden = !fields.hidden;
                if (!fields.hidden) {
                    if (customerIdInput) customerIdInput.value = "";
                    if (selectedCust) selectedCust.classList.remove("is-visible");
                    var nameField = document.getElementById("custNameField");
                    if (nameField) nameField.focus();
                }
            });
        }
    }

    function escAttr(s) {
        return String(s || "").replace(/&/g, "&amp;").replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }

    function escHtml(s) {
        var d = document.createElement("div");
        d.textContent = String(s || "");
        return d.innerHTML;
    }

    function initDetailPage() {
        var detail = window.__detail || {};

        var notifyBtn = document.getElementById("notifyBtn");
        var markDoneBtn = document.getElementById("markDoneBtn");
        var saveCostBtn = document.getElementById("saveCostBtn");
        var saveFinalCostBtn = document.getElementById("saveFinalCostBtn");

        if (notifyBtn) {
            notifyBtn.addEventListener("click", function () {
                var rid = notifyBtn.getAttribute("data-repair-id");
                if (!rid) return;
                notifyBtn.disabled = true;
                fetch(detail.notifyUrl || "", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ repair_id: parseInt(rid, 10) })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            window.location.reload();
                        } else {
                            alert(data.error || "Could not send notification.");
                            notifyBtn.disabled = false;
                        }
                    })
                    .catch(function () {
                        alert("Could not send notification.");
                        notifyBtn.disabled = false;
                    });
            });
        }

        if (markDoneBtn) {
            markDoneBtn.addEventListener("click", function () {
                var rid = markDoneBtn.getAttribute("data-repair-id");
                if (!rid) return;
                if (!confirm("Mark this job as completed?")) return;
                markDoneBtn.disabled = true;
                fetch(detail.completeUrl || "", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ repair_id: parseInt(rid, 10) })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            window.location.reload();
                        } else {
                            alert(data.error || "Could not complete job.");
                            markDoneBtn.disabled = false;
                        }
                    })
                    .catch(function () {
                        alert("Could not complete job.");
                        markDoneBtn.disabled = false;
                    });
            });
        }

        function postCost(payload, btn) {
            btn.disabled = true;
            fetch(detail.updateUrl || "", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        window.location.reload();
                    } else {
                        alert(data.error || "Could not update costs.");
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    alert("Could not update costs.");
                    btn.disabled = false;
                });
        }

        if (saveCostBtn) {
            saveCostBtn.addEventListener("click", function () {
                var estEl = document.getElementById("estCostInput");
                var payload = { repair_id: parseInt(saveCostBtn.getAttribute("data-repair-id") || "0", 10) };
                if (estEl) payload.estimated_cost = parseFloat(estEl.value) || 0;
                postCost(payload, saveCostBtn);
            });
            var estEl = document.getElementById("estCostInput");
            if (estEl) estEl.addEventListener("keydown", function (e) { if (e.key === "Enter") saveCostBtn.click(); });
        }

        if (saveFinalCostBtn) {
            saveFinalCostBtn.addEventListener("click", function () {
                var finalEl = document.getElementById("finalCostInput");
                var payload = { repair_id: parseInt(saveFinalCostBtn.getAttribute("data-repair-id") || "0", 10) };
                if (finalEl) payload.final_cost = parseFloat(finalEl.value) || 0;
                postCost(payload, saveFinalCostBtn);
            });
            var finalEl = document.getElementById("finalCostInput");
            if (finalEl) finalEl.addEventListener("keydown", function (e) { if (e.key === "Enter") saveFinalCostBtn.click(); });
        }
    }

    function moveJob(repairId, targetColumn) {
        fetch(MOVE_URL, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ repair_id: repairId, column: targetColumn })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    window.location.reload();
                } else {
                    alert(data.error || "Could not update job.");
                }
            });
    }

    function toggleNoteForm(repairId) {
        var form = document.getElementById("noteForm-" + repairId);
        if (!form) return;
        form.hidden = !form.hidden;
        if (!form.hidden) {
            var input = document.getElementById("noteInput-" + repairId);
            if (input) input.focus();
        }
    }

    function saveNote(repairId) {
        var input = document.getElementById("noteInput-" + repairId);
        if (!input) return;
        var note = input.value.trim();
        if (!note) return;

        fetch(NOTE_URL, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ repair_id: repairId, note: note })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    toggleNoteForm(repairId);
                } else {
                    alert(data.error || "Could not save note.");
                }
            })
            .catch(function () {
                alert("Could not save note.");
            });
    }

    function updateColumnCounts() {
        document.querySelectorAll(".board-column").forEach(function (col) {
            var count = col.querySelectorAll(".job-card").length;
            var countEl = col.querySelector(".board-column-count");
            if (countEl) countEl.textContent = count;
            var empty = col.querySelector(".board-column-empty");
            if (empty) empty.style.display = count === 0 ? "" : "none";
        });
    }

    function updateDoneButton(repairId, column) {
        var card = document.querySelector('.job-card[data-repair-id="' + repairId + '"]');
        if (!card) return;
        var doneBtn = card.querySelector(".job-btn--done");
        if (column === "completed") {
            if (doneBtn) doneBtn.remove();
        } else if (!doneBtn) {
            var actions = card.querySelector(".job-card-actions");
            if (actions) {
                var nextCol = column === "pending" ? "in_progress" : "completed";
                var btn = document.createElement("button");
                btn.className = "job-btn job-btn--done";
                btn.type = "button";
                btn.title = "Mark Done";
                btn.setAttribute("data-action", "done");
                btn.setAttribute("data-repair-id", repairId);
                btn.setAttribute("data-next-column", nextCol);
                btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
                actions.appendChild(btn);
            }
        }
    }
})();

"use strict";

(function () {
    document.addEventListener("DOMContentLoaded", function () {
        initPeriodToggle();
        renderRevenueChart();
        renderStaffBars();
    });

    function initPeriodToggle() {
        var btns = document.querySelectorAll("#periodToggle .toggle-btn");
        var input = document.getElementById("periodInput");
        btns.forEach(function (btn) {
            btn.addEventListener("click", function () {
                btns.forEach(function (b) { b.classList.remove("is-active"); });
                btn.classList.add("is-active");
                if (input) {
                    input.value = btn.getAttribute("data-value") || "weekly";
                    document.getElementById("reportFilters").submit();
                }
            });
        });
    }

    function renderRevenueChart() {
        var container = document.getElementById("revenueChart");
        if (!container) return;

        var data = window.__reportData;
        if (!data) return;

        var current = data.revenueSeries || [];
        var previous = data.prevSeries || [];
        var compare = data.compare && previous.length > 0;

        var allValues = current.concat(previous).map(function (p) { return p.value; });
        var maxVal = Math.max.apply(null, allValues.concat([0]));

        var svg = createSvg(container, 700, 280);
        var pad = { top: 20, right: 30, bottom: 35, left: 60 };
        var w = 700 - pad.left - pad.right;
        var h = 280 - pad.top - pad.bottom;

        var g = svgEl("g", { transform: "translate(" + pad.left + "," + pad.top + ")" });
        svg.appendChild(g);

        var steps = 4;
        for (var i = 0; i <= steps; i++) {
            var y = (i / steps) * h;
            var val = formatCurrency(maxVal * (1 - i / steps));
            g.appendChild(svgEl("line", {
                x1: 0, y1: y, x2: w, y2: y,
                class: "chart-grid-line"
            }));
            g.appendChild(svgEl("text", {
                x: -8, y: y + 4,
                "text-anchor": "end",
                class: "chart-axis-label"
            }, val));
        }

        var xStep = current.length > 1 ? w / (current.length - 1) : w;

        current.forEach(function (p, i) {
            var x = i * xStep;
            g.appendChild(svgEl("text", {
                x: x, y: h + 20,
                "text-anchor": "middle",
                class: "chart-axis-label"
            }, p.label));
        });

        g.appendChild(drawLine(current, w, h, maxVal, xStep, "chart-line-cyan"));
        current.forEach(function (p, i) {
            var x = i * xStep;
            var y = maxVal > 0 ? h - (p.value / maxVal) * h : h;
            g.appendChild(svgEl("circle", {
                cx: x, cy: y, r: 4,
                class: "chart-dot",
                stroke: "var(--cyan-500)"
            }));
        });

        if (compare) {
            g.appendChild(drawLine(previous, w, h, maxVal, xStep, "chart-line-amber"));
            previous.forEach(function (p, i) {
                var x = i * xStep;
                var y = maxVal > 0 ? h - (p.value / maxVal) * h : h;
                g.appendChild(svgEl("circle", {
                    cx: x, cy: y, r: 3,
                    class: "chart-dot",
                    stroke: "var(--amber-500)"
                }));
            });
        }
    }

    function drawLine(arr, w, h, max, step, cls) {
        if (arr.length === 0) return svgEl("g");

        var points = arr.map(function (p, i) {
            var x = i * step;
            var y = max > 0 ? h - (p.value / max) * h : h;
            return { x: x, y: y };
        });

        var d = catmullRomPath(points);

        var line = svgEl("path", { d: d, class: cls });
        return line;
    }

    function catmullRomPath(pts) {
        if (pts.length === 0) return "";
        if (pts.length === 1) return "M" + pts[0].x + "," + pts[0].y;
        if (pts.length === 2) return "M" + pts[0].x + "," + pts[0].y + " L" + pts[1].x + "," + pts[1].y;

        var path = "";
        var n = pts.length;
        for (var i = -1; i < n; i++) {
            var p0 = pts[Math.max(0, i)];
            var p1 = pts[Math.min(n - 1, i + 1)];
            var p2 = pts[Math.min(n - 1, i + 2)];
            var p3 = pts[Math.min(n - 1, i + 3)];

            var cp1x = p1.x + (p2.x - p0.x) / 6;
            var cp1y = p1.y + (p2.y - p0.y) / 6;
            var cp2x = p2.x - (p3.x - p1.x) / 6;
            var cp2y = p2.y - (p3.y - p1.y) / 6;

            if (i === -1) {
                path += "M" + p1.x.toFixed(1) + "," + p1.y.toFixed(1);
            } else {
                path += " C" + cp1x.toFixed(1) + "," + cp1y.toFixed(1)
                     + " " + cp2x.toFixed(1) + "," + cp2y.toFixed(1)
                     + " " + p2.x.toFixed(1) + "," + p2.y.toFixed(1);
            }
        }
        return path;
    }

    function renderStaffBars() {
        var container = document.getElementById("staffBars");
        if (!container) return;

        var data = window.__reportData;
        if (!data) return;

        var staff = data.staffData || [];
        var max = data.staffMax || 1;
        if (max <= 0) max = 1;

        if (staff.length === 0) {
            container.innerHTML = '<p style="color:var(--text-500);padding:20px;text-align:center;font-size:0.88rem;">No sales data for this period.</p>';
            return;
        }

        var html = "";
        var colors = ["var(--cyan-500)", "var(--green-500)", "var(--amber-500)", "#a78bfa", "#f472b6", "#38bdf8"];

        staff.forEach(function (s, i) {
            var pct = Math.max(4, (s.total_revenue / max) * 100);
            var color = colors[i % colors.length];
            var initials = s.initials || "?";
            var name = s.full_name || "Unknown";

            html += '<div class="staff-bar-row">';
            html += '<span class="staff-bar-avatar">' + esc(initials) + '</span>';
            html += '<div class="staff-bar-info">';
            html += '<span class="staff-bar-name">' + esc(name) + '</span>';
            html += '<div class="staff-bar-track"><div class="staff-bar-fill" style="width:' + pct.toFixed(1) + '%;background:' + color + '"></div></div>';
            html += '</div>';
            html += '<span class="staff-bar-value">$' + numberFormat(s.total_revenue) + '</span>';
            html += '</div>';
        });

        container.innerHTML = html;
    }

    function createSvg(container, w, h) {
        container.innerHTML = "";
        var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        svg.setAttribute("width", "100%");
        svg.setAttribute("height", "100%");
        svg.setAttribute("viewBox", "0 0 " + w + " " + h);
        svg.setAttribute("preserveAspectRatio", "xMidYMid meet");
        container.appendChild(svg);
        return svg;
    }

    function svgEl(name, attrs, text) {
        var el = document.createElementNS("http://www.w3.org/2000/svg", name);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                el.setAttribute(k, attrs[k]);
            });
        }
        if (text !== undefined) {
            el.textContent = text;
        }
        return el;
    }

    function formatCurrency(v) {
        if (v >= 1000000) return "$" + (v / 1000000).toFixed(1) + "M";
        if (v >= 1000) return "$" + (v / 1000).toFixed(1) + "K";
        return "$" + Math.round(v);
    }

    function numberFormat(v) {
        return v.toLocaleString("en-US", { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function esc(s) {
        var d = document.createElement("div");
        d.textContent = s;
        return d.innerHTML;
    }
})();

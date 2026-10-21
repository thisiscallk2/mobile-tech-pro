/* =====================================================================
   MobileTech Pro -- dashboard charts (vanilla JS, plain SVG, no library)
   Data is read from data-* attributes rendered by PHP.
   ===================================================================== */
(function () {
    "use strict";

    var SVG_NS = "http://www.w3.org/2000/svg";

    function el(name, attrs) {
        var node = document.createElementNS(SVG_NS, name);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                node.setAttribute(k, attrs[k]);
            });
        }
        return node;
    }

    function parseData(node, key, fallback) {
        try {
            var raw = node.getAttribute("data-" + key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    /* ---- nice rounded axis maximum -------------------------------- */
    function niceMax(value) {
        if (value <= 0) {
            return 10;
        }
        var pow = Math.pow(10, Math.floor(Math.log10(value)));
        var n = value / pow;
        var step;
        if (n <= 1) {
            step = 1;
        } else if (n <= 2) {
            step = 2;
        } else if (n <= 5) {
            step = 5;
        } else {
            step = 10;
        }
        return step * pow;
    }

    /* ---- Catmull-Rom -> cubic bezier smoothing -------------------- */
    function smoothPath(points) {
        if (points.length < 2) {
            return points.length ? "M" + points[0].x + "," + points[0].y : "";
        }
        var d = "M" + points[0].x + "," + points[0].y;
        for (var i = 0; i < points.length - 1; i++) {
            var p0 = points[i - 1] || points[i];
            var p1 = points[i];
            var p2 = points[i + 1];
            var p3 = points[i + 2] || p2;
            var c1x = p1.x + (p2.x - p0.x) / 6;
            var c1y = p1.y + (p2.y - p0.y) / 6;
            var c2x = p2.x - (p3.x - p1.x) / 6;
            var c2y = p2.y - (p3.y - p1.y) / 6;
            d += " C" + c1x + "," + c1y + " " + c2x + "," + c2y + " " + p2.x + "," + p2.y;
        }
        return d;
    }

    function formatMoney(v) {
        if (v >= 1000) {
            return "$" + (v / 1000).toFixed(v % 1000 === 0 ? 0 : 1) + "k";
        }
        return "$" + Math.round(v);
    }

    /* ================================================================ */
    /*  Area / line revenue chart                                       */
    /* ================================================================ */
    function renderArea(host, series) {
        while (host.firstChild) {
            host.removeChild(host.firstChild);
        }
        if (!series || !series.length) {
            return;
        }

        var w = host.clientWidth || 600;
        var h = host.clientHeight || 260;
        var padL = 48;
        var padR = 18;
        var padT = 16;
        var padB = 30;
        var innerW = w - padL - padR;
        var innerH = h - padT - padB;
        var n = series.length;

        var max = niceMax(Math.max.apply(null, series.map(function (p) { return p.value; })));

        var xFor = function (i) {
            return n <= 1 ? padL + innerW / 2 : padL + (innerW * i) / (n - 1);
        };
        var yFor = function (v) {
            return padT + innerH - (v / max) * innerH;
        };

        var svg = el("svg", {
            viewBox: "0 0 " + w + " " + h,
            width: "100%",
            height: h,
            preserveAspectRatio: "none"
        });

        /* gradient fill */
        var defs = el("defs");
        var grad = el("linearGradient", { id: "revGrad", x1: "0", y1: "0", x2: "0", y2: "1" });
        grad.appendChild(el("stop", { offset: "0%", "stop-color": "#22d3ee", "stop-opacity": "0.35" }));
        grad.appendChild(el("stop", { offset: "100%", "stop-color": "#22d3ee", "stop-opacity": "0" }));
        defs.appendChild(grad);
        svg.appendChild(defs);

        /* horizontal grid + y labels */
        var ticks = 4;
        for (var t = 0; t <= ticks; t++) {
            var val = (max / ticks) * t;
            var gy = yFor(val);
            svg.appendChild(el("line", {
                x1: padL, y1: gy, x2: w - padR, y2: gy, class: "chart-grid-line"
            }));
            var yl = el("text", { x: padL - 8, y: gy + 4, "text-anchor": "end", class: "chart-axis-label" });
            yl.textContent = formatMoney(val);
            svg.appendChild(yl);
        }

        var points = series.map(function (p, i) {
            return { x: xFor(i), y: yFor(p.value) };
        });

        var line = smoothPath(points);

        /* area (line + baseline) */
        var baseline = padT + innerH;
        var areaD = line + " L" + points[points.length - 1].x + "," + baseline +
            " L" + points[0].x + "," + baseline + " Z";
        svg.appendChild(el("path", { d: areaD, fill: "url(#revGrad)", stroke: "none" }));

        /* line */
        svg.appendChild(el("path", {
            d: line, fill: "none", stroke: "#22d3ee", "stroke-width": "2.5",
            "stroke-linecap": "round", "stroke-linejoin": "round"
        }));

        /* points + x labels */
        points.forEach(function (pt, i) {
            svg.appendChild(el("circle", {
                cx: pt.x, cy: pt.y, r: "3.5", fill: "#0a0f1c",
                stroke: "#22d3ee", "stroke-width": "2"
            }));
            var xl = el("text", {
                x: pt.x, y: h - 10, "text-anchor": "middle", class: "chart-axis-label"
            });
            xl.textContent = series[i].label;
            svg.appendChild(xl);
        });

        host.appendChild(svg);
    }

    /* ================================================================ */
    /*  Donut chart                                                     */
    /* ================================================================ */
    function renderDonut(host, segments, total) {
        while (host.firstChild) {
            host.removeChild(host.firstChild);
        }

        var size = 190;
        var stroke = 24;
        var cx = size / 2;
        var cy = size / 2;
        var r = (size - stroke) / 2;
        var circ = 2 * Math.PI * r;

        var sum = segments.reduce(function (acc, s) { return acc + (s.value || 0); }, 0);

        var svg = el("svg", { viewBox: "0 0 " + size + " " + size, width: size, height: size });

        /* track */
        svg.appendChild(el("circle", {
            cx: cx, cy: cy, r: r, fill: "none", stroke: "#1b2b49", "stroke-width": stroke
        }));

        var offset = 0;
        segments.forEach(function (s) {
            if (sum <= 0 || !s.value) {
                return;
            }
            var frac = s.value / sum;
            var dash = frac * circ;
            var arc = el("circle", {
                cx: cx, cy: cy, r: r, fill: "none",
                stroke: s.color, "stroke-width": stroke,
                "stroke-dasharray": dash + " " + (circ - dash),
                "stroke-dashoffset": -offset,
                transform: "rotate(-90 " + cx + " " + cy + ")"
            });
            svg.appendChild(arc);
            offset += dash;
        });

        var big = el("text", {
            x: cx, y: cy - 2, "text-anchor": "middle", class: "donut-total-value"
        });
        big.textContent = String(total);
        svg.appendChild(big);

        var small = el("text", {
            x: cx, y: cy + 18, "text-anchor": "middle", class: "donut-total-label"
        });
        small.textContent = "Total Jobs";
        svg.appendChild(small);

        host.appendChild(svg);
    }

    /* ---- colour the legend dots from their data-color -------------- */
    function paintLegend() {
        var dots = document.querySelectorAll(".legend-dot[data-color]");
        Array.prototype.forEach.call(dots, function (dot) {
            dot.style.background = dot.getAttribute("data-color");
        });
    }

    /* ---- debounce -------------------------------------------------- */
    function debounce(fn, wait) {
        var timer;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, wait);
        };
    }

    /* ================================================================ */
    document.addEventListener("DOMContentLoaded", function () {
        var chart = document.getElementById("revenueChart");
        var donut = document.getElementById("repairDonut");

        var seriesMap = null;
        var currentRange = "daily";

        if (chart) {
            seriesMap = {
                daily: parseData(chart, "daily", []),
                weekly: parseData(chart, "weekly", []),
                monthly: parseData(chart, "monthly", [])
            };
            renderArea(chart, seriesMap[currentRange]);

            var buttons = document.querySelectorAll(".chart-toggle .toggle-btn");
            Array.prototype.forEach.call(buttons, function (btn) {
                btn.addEventListener("click", function () {
                    var range = btn.getAttribute("data-range");
                    if (!seriesMap[range]) {
                        return;
                    }
                    currentRange = range;
                    Array.prototype.forEach.call(buttons, function (b) {
                        b.classList.toggle("is-active", b === btn);
                    });
                    renderArea(chart, seriesMap[currentRange]);
                });
            });

            window.addEventListener("resize", debounce(function () {
                renderArea(chart, seriesMap[currentRange]);
            }, 150));
        }

        if (donut) {
            var segments = parseData(donut, "segments", []);
            var total = parseInt(donut.getAttribute("data-total"), 10) || 0;
            renderDonut(donut, segments, total);
        }

        paintLegend();
    });
})();

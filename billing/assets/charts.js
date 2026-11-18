/* Renderer grafik ringan tanpa dependensi eksternal (offline-friendly). */
(function () {
    'use strict';

    var PALETTE = ['#ffb02e', '#60a5fa', '#a78bfa', '#34d399', '#fb7185', '#22d3ee', '#f472b6', '#94a3b8'];

    function usd(value) {
        return '$' + Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function niceMax(value) {
        if (!isFinite(value) || value <= 0) {
            return 10;
        }
        var power = Math.pow(10, Math.floor(Math.log10(value)));
        var n = value / power;
        var step = n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10;
        return step * power;
    }

    function prepare(canvas) {
        var dpr = window.devicePixelRatio || 1;
        var parent = canvas.parentElement;
        var width = parent.clientWidth || 600;
        var height = parent.clientHeight || 280;
        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        var ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        return { ctx: ctx, width: width, height: height };
    }

    function smoothPath(ctx, points) {
        if (points.length < 2) {
            return;
        }
        ctx.moveTo(points[0].x, points[0].y);
        for (var i = 1; i < points.length; i++) {
            var prev = points[i - 1];
            var current = points[i];
            var midX = (prev.x + current.x) / 2;
            ctx.bezierCurveTo(midX, prev.y, midX, current.y, current.x, current.y);
        }
    }

    function ensureTooltip(canvas) {
        var parent = canvas.parentElement;
        var tip = parent.querySelector('.chart-tip');
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'chart-tip';
            parent.appendChild(tip);
        }
        return tip;
    }

    function renderLineChart(canvas, labels, values, options) {
        options = options || {};
        var colors = options.colors || {};
        var lineColor = colors.line || '#ffb02e';
        var state = prepare(canvas);
        var ctx = state.ctx;
        var width = state.width;
        var height = state.height;

        var pad = { top: 18, right: 16, bottom: 34, left: 56 };
        var plotW = Math.max(10, width - pad.left - pad.right);
        var plotH = Math.max(10, height - pad.top - pad.bottom);
        var max = niceMax(Math.max.apply(null, values.concat([1])) * 1.12);

        ctx.clearRect(0, 0, width, height);

        // Grid + label sumbu Y
        ctx.font = '10px "Segoe UI", system-ui, sans-serif';
        ctx.textBaseline = 'middle';
        var steps = 4;
        for (var s = 0; s <= steps; s++) {
            var y = pad.top + (plotH / steps) * s;
            var tickValue = max - (max / steps) * s;
            ctx.strokeStyle = 'rgba(32, 48, 74, .62)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(pad.left, y);
            ctx.lineTo(pad.left + plotW, y);
            ctx.stroke();

            ctx.fillStyle = '#6f84a3';
            ctx.textAlign = 'right';
            ctx.fillText(tickValue >= 1000 ? '$' + (tickValue / 1000).toFixed(1) + 'k' : '$' + Math.round(tickValue), pad.left - 9, y);
        }

        // Label sumbu X
        ctx.textAlign = 'center';
        ctx.fillStyle = '#6f84a3';
        var labelEvery = Math.ceil(labels.length / 7);
        for (var i = 0; i < labels.length; i++) {
            if (i % labelEvery !== 0 && i !== labels.length - 1) {
                continue;
            }
            var x = pad.left + (labels.length === 1 ? plotW / 2 : (plotW / (labels.length - 1)) * i);
            ctx.fillText(labels[i], x, height - pad.bottom / 2 + 2);
        }

        // Titik data
        var points = values.map(function (value, index) {
            var x = pad.left + (values.length === 1 ? plotW / 2 : (plotW / (values.length - 1)) * index);
            var y = pad.top + plotH - (value / max) * plotH;
            return { x: x, y: y, value: value, label: labels[index] };
        });

        // Area gradient
        var gradient = ctx.createLinearGradient(0, pad.top, 0, pad.top + plotH);
        gradient.addColorStop(0, 'rgba(255, 176, 46, .30)');
        gradient.addColorStop(1, 'rgba(255, 176, 46, .01)');

        ctx.beginPath();
        smoothPath(ctx, points);
        ctx.lineTo(points[points.length - 1].x, pad.top + plotH);
        ctx.lineTo(points[0].x, pad.top + plotH);
        ctx.closePath();
        ctx.fillStyle = gradient;
        ctx.fill();

        // Garis
        ctx.beginPath();
        smoothPath(ctx, points);
        ctx.strokeStyle = lineColor;
        ctx.lineWidth = 2.2;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.stroke();

        // Titik
        points.forEach(function (point) {
            ctx.beginPath();
            ctx.arc(point.x, point.y, 3.4, 0, Math.PI * 2);
            ctx.fillStyle = '#0f1a2c';
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = lineColor;
            ctx.stroke();
        });

        // Tooltip interaktif
        var tip = ensureTooltip(canvas);
        var hoverIndex = -1;
        var hoverLine = null;

        function hide() {
            tip.style.opacity = '0';
            if (hoverLine !== null) {
                hoverLine.remove();
                hoverLine = null;
            }
        }

        function move(event) {
            var rect = canvas.getBoundingClientRect();
            var mouseX = event.clientX - rect.left;
            var best = 0;
            var bestDistance = Infinity;
            points.forEach(function (point, index) {
                var distance = Math.abs(point.x - mouseX);
                if (distance < bestDistance) {
                    bestDistance = distance;
                    best = index;
                }
            });
            if (bestDistance > 60) {
                hide();
                return;
            }
            if (hoverIndex !== best) {
                hoverIndex = best;
                if (hoverLine === null) {
                    hoverLine = document.createElement('div');
                    hoverLine.className = 'chart-hover-line';
                    canvas.parentElement.appendChild(hoverLine);
                }
                var point = points[best];
                hoverLine.style.left = point.x + 'px';
                hoverLine.style.top = pad.top + 'px';
                hoverLine.style.height = plotH + 'px';
                tip.innerHTML = '<span>' + point.label + '</span><strong>' + usd(point.value) + '</strong>';
                tip.style.left = point.x + 'px';
                tip.style.top = Math.max(4, point.y - 48) + 'px';
                tip.style.opacity = '1';
            }
        }

        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseleave', hide);

        // Redraw saat ukuran berubah
        if (window.ResizeObserver) {
            var lastWidth = width;
            var observer = new ResizeObserver(function () {
                var current = canvas.parentElement.clientWidth;
                if (Math.abs(current - lastWidth) > 4) {
                    lastWidth = current;
                    renderLineChart(canvas, labels, values, options);
                }
            });
            observer.observe(canvas.parentElement);
        }
    }

    window.renderLineChart = renderLineChart;
    window.BILLING_PALETTE = PALETTE;
})();

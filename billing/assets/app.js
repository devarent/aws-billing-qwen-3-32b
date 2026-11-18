/* Qween 3 32B — interaksi UI & grafik */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var sidebar = document.getElementById('sidebar');
        var toggle = document.getElementById('menu-toggle');

        if (toggle && sidebar) {
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                sidebar.classList.toggle('open');
            });
            document.addEventListener('click', function (event) {
                if (sidebar.classList.contains('open') && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('open');
                }
            });
        }

        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('a[data-confirm]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (!window.confirm(link.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });
        });

        window.setTimeout(function () {
            document.querySelectorAll('.flash').forEach(function (flash) {
                flash.style.transition = 'opacity .3s ease';
                flash.style.opacity = '0';
                window.setTimeout(function () { flash.remove(); }, 320);
            });
        }, 9000);

        if (window.BILLING_CHARTS && window.renderLineChart) {
            var canvas = document.getElementById('chart-monthly');
            if (canvas) {
                window.renderLineChart(
                    canvas,
                    window.BILLING_CHARTS.months || [],
                    window.BILLING_CHARTS.monthTotals || [],
                    { colors: { line: '#ffb02e' } }
                );
            }
        }
    });
})();

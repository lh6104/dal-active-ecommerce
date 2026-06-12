(function () {
    'use strict';

    function init(root) {
        var gender = 'men';
        var type = 'shoes';
        var genderTabs = root.querySelectorAll('[data-size-gender]');
        var typeTabs = root.querySelectorAll('[data-size-type]');
        var charts = root.querySelectorAll('[data-size-chart]');
        var measurePanels = root.querySelectorAll('[data-measure-panel]');

        function setActive(tabs, attr, value) {
            Array.prototype.forEach.call(tabs, function (tab) {
                var active = tab.getAttribute(attr) === value;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        function render() {
            var key = gender + '-' + (type === 'clothing' ? 'clothing' : 'shoes');
            setActive(genderTabs, 'data-size-gender', gender);
            setActive(typeTabs, 'data-size-type', type);

            Array.prototype.forEach.call(charts, function (chart) {
                chart.classList.toggle('is-active', chart.getAttribute('data-size-chart') === key);
            });

            Array.prototype.forEach.call(measurePanels, function (panel) {
                panel.hidden = panel.getAttribute('data-measure-panel') !== type;
            });
        }

        Array.prototype.forEach.call(genderTabs, function (tab) {
            tab.addEventListener('click', function () {
                gender = tab.getAttribute('data-size-gender');
                render();
            });
        });

        Array.prototype.forEach.call(typeTabs, function (tab) {
            tab.addEventListener('click', function () {
                type = tab.getAttribute('data-size-type');
                render();
            });
        });

        render();
    }

    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('[data-size-guide]'), init);
    });
}());

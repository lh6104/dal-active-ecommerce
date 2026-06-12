define(['leaflet'], function (L) {
    'use strict';

    return function (config, element) {
        var root = element;
        if (!root || !L) {
            return;
        }

        var stores = [];
        try {
            stores = JSON.parse(root.getAttribute('data-stores') || '[]');
        } catch (error) {
            stores = [];
        }

        var searchInput = root.querySelector('#dal-storelocator-search');
        var countNode = root.querySelector('.dal-storelocator-count');
        var listNode = root.querySelector('[data-store-list]');
        var emptyMapNode = root.querySelector('.dal-storelocator-mapempty');
        var iconUrl = typeof require !== 'undefined' && require.toUrl
            ? require.toUrl('Dalactive_StoreLocator/images/google-maps.svg')
            : '/static/frontend/Hiddentechies/bizkick/vi_VN/Dalactive_StoreLocator/images/google-maps.svg';

        if (!searchInput || !countNode || !listNode || !root.querySelector('#dal-storelocator-map')) {
            return;
        }

        var map = L.map('dal-storelocator-map', {
            zoomControl: false,
            scrollWheelZoom: false
        }).setView([16.047079, 108.206230], 5);

        L.control.zoom({
            position: 'bottomright'
        }).addTo(map);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var markerById = {};
        var activeMarkers = [];
        var activeStoreId = null;

        function hasCoordinates(store) {
            return typeof store.latitude === 'number'
                && typeof store.longitude === 'number'
                && !isNaN(store.latitude)
                && !isNaN(store.longitude);
        }

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (character) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[character];
            });
        }

        function getSearchText(store) {
            return [
                store.name,
                store.address,
                store.city,
                store.region
            ].join(' ').toLowerCase();
        }

        function createPopup(store) {
            var direction = store.directionsUrl
                ? '<p><a href="' + escapeHtml(store.directionsUrl) + '" target="_blank" rel="noopener noreferrer">Chỉ đường</a></p>'
                : '';

            return '<div class="dal-storelocator-popup">'
                + '<strong>' + escapeHtml(store.name) + '</strong>'
                + '<p>' + escapeHtml(store.address) + '</p>'
                + (store.openingHours ? '<p>Giờ mở cửa: ' + escapeHtml(store.openingHours) + '</p>' : '')
                + direction
                + '</div>';
        }

        function fitVisibleMarkers() {
            if (activeMarkers.length === 0) {
                emptyMapNode.hidden = false;
                map.setView([16.047079, 108.206230], 5);
                return;
            }

            emptyMapNode.hidden = true;
            if (activeMarkers.length === 1) {
                map.setView(activeMarkers[0].getLatLng(), 15);
                return;
            }

            map.fitBounds(L.featureGroup(activeMarkers).getBounds(), {
                padding: [40, 40],
                maxZoom: 14
            });
        }

        function syncMarkers(filteredStores) {
            activeMarkers.forEach(function (marker) {
                marker.remove();
            });
            activeMarkers = [];
            markerById = {};

            filteredStores.forEach(function (store) {
                if (!hasCoordinates(store)) {
                    return;
                }

                var marker = L.marker([store.latitude, store.longitude]).addTo(map);
                marker.bindPopup(createPopup(store));
                markerById[store.id] = marker;
                activeMarkers.push(marker);
            });

            fitVisibleMarkers();
            map.invalidateSize();
        }

        function renderList(filteredStores) {
            countNode.textContent = filteredStores.length + ' cửa hàng';

            if (filteredStores.length === 0) {
                listNode.innerHTML = '<div class="dal-storelocator-empty">Không tìm thấy cửa hàng phù hợp.</div>';
                return;
            }

            listNode.innerHTML = filteredStores.map(function (store) {
                var mapButton = hasCoordinates(store)
                    ? '<button class="dal-storelocator-button" type="button" data-map-store="' + store.id + '">Xem trên bản đồ</button>'
                    : '<button class="dal-storelocator-button" type="button" disabled>Xem trên bản đồ</button>';
                var directionButton = store.directionsUrl
                    ? '<a class="dal-storelocator-button dal-storelocator-button--primary" href="' + escapeHtml(store.directionsUrl) + '" target="_blank" rel="noopener noreferrer"><span>Chỉ đường</span><img src="' + iconUrl + '" alt="Google Maps"/></a>'
                    : '';

                return '<article class="dal-storelocator-card' + (activeStoreId === store.id ? ' is-active' : '') + '" data-store-card="' + store.id + '">'
                    + '<h2>' + escapeHtml(store.name) + '</h2>'
                    + '<p>' + escapeHtml(store.address) + '</p>'
                    + (store.openingHours ? '<p class="dal-storelocator-card__status">Giờ mở cửa: ' + escapeHtml(store.openingHours) + '</p>' : '')
                    + (store.phone ? '<p class="dal-storelocator-card__meta">Điện thoại: ' + escapeHtml(store.phone) + '</p>' : '')
                    + (store.email ? '<p class="dal-storelocator-card__meta">Email: ' + escapeHtml(store.email) + '</p>' : '')
                    + '<div class="dal-storelocator-store-actions">' + mapButton + directionButton + '</div>'
                    + '</article>';
            }).join('');
        }

        function getFilteredStores() {
            var query = (searchInput.value || '').trim().toLowerCase();
            return query
                ? stores.filter(function (store) {
                    return getSearchText(store).indexOf(query) !== -1;
                })
                : stores.slice();
        }

        function render() {
            var filteredStores = getFilteredStores();
            if (activeStoreId && !filteredStores.some(function (store) { return store.id === activeStoreId; })) {
                activeStoreId = null;
            }

            renderList(filteredStores);
            syncMarkers(filteredStores);
        }

        listNode.addEventListener('click', function (event) {
            var button = event.target.closest('[data-map-store]');
            if (!button) {
                return;
            }

            activeStoreId = parseInt(button.getAttribute('data-map-store'), 10);
            var marker = markerById[activeStoreId];
            if (marker) {
                map.setView(marker.getLatLng(), 16);
                marker.openPopup();
            }
            renderList(getFilteredStores());
        });

        searchInput.addEventListener('input', render);
        window.addEventListener('resize', function () {
            map.invalidateSize();
        });

        render();
        map.invalidateSize();
        setTimeout(function () {
            map.invalidateSize();
            fitVisibleMarkers();
        }, 250);
    };
});

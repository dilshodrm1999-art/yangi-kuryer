// Checkout xaritasi: avtomatik geolokatsiya + reverse geocoding (manzilni avto to'ldirish)
(function () {
  var mapEl = document.getElementById('map');
  if (!mapEl || typeof L === 'undefined') return;

  var def = [41.311081, 69.240562]; // Toshkent
  var map = L.map('map', { zoomControl: true }).setView(def, 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '© OpenStreetMap'
  }).addTo(map);

  // Shahar chegarasi poligonini ko'rsatish (agar belgilangan bo'lsa)
  if (window.CITY_POLYGON && window.CITY_POLYGON.length >= 3) {
    try {
      var cityLayer = L.polygon(window.CITY_POLYGON, {
        color: '#ff6b35', weight: 2, fillColor: '#ff6b35', fillOpacity: 0.08,
        dashArray: '6 6'
      }).addTo(map);
      cityLayer.bindTooltip('Shahar ichi zonasi', { sticky: true });
      map.fitBounds(cityLayer.getBounds(), { padding: [30, 30] });
    } catch (e) {}
  }

  // Olish nuqtasi (do'kon) markeri + yo'l chizig'i
  var pickupMarker = null, routeLine = null, routeReqId = 0;
  if (window.PICKUP && window.PICKUP.lat && window.PICKUP.lng) {
    var storeIcon = L.divIcon({ className: 'emoji-marker', html: '🏪', iconSize: [30, 30] });
    pickupMarker = L.marker([window.PICKUP.lat, window.PICKUP.lng], { icon: storeIcon, title: window.PICKUP.name })
      .addTo(map).bindPopup('🏪 ' + (window.PICKUP.name || 'Do\'kon'));
  }

  // To'g'ri chiziq masofasi (zaxira)
  function straightKm(aLat, aLng, bLat, bLng) {
    var R = 6371, p = Math.PI / 180;
    var x = Math.sin((bLat - aLat) * p / 2) ** 2 +
            Math.cos(aLat * p) * Math.cos(bLat * p) * Math.sin((bLng - aLng) * p / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
  }

  // Do'kondan mijozgacha VELOSIPED yo'lini chizish (OSRM) + masofani aniqlash.
  // Yo'l xizmati ishlamasa to'g'ri chiziqqa qaytadi.
  function drawRoute(lat, lng) {
    if (!window.PICKUP || !window.PICKUP.lat) {
      if (typeof window.onRouteResult === 'function') window.onRouteResult(null, lat, lng);
      return;
    }
    var p = window.PICKUP;
    var myReq = ++routeReqId;

    // Vaqtinchalik to'g'ri chiziq (yo'l kelguncha)
    var fallbackPts = [[p.lat, p.lng], [lat, lng]];
    setLine(fallbackPts, true);

    var url = 'https://routing.openstreetmap.de/routed-bike/route/v1/driving/' +
              p.lng + ',' + p.lat + ';' + lng + ',' + lat + '?overview=full&geometries=geojson';

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (myReq !== routeReqId) return; // eskirgan so'rov
        if (d && d.routes && d.routes[0]) {
          var coords = d.routes[0].geometry.coordinates.map(function (c) { return [c[1], c[0]]; });
          setLine(coords, false);
          var km = d.routes[0].distance / 1000;
          if (typeof window.onRouteResult === 'function') window.onRouteResult(km, lat, lng);
        } else { throw new Error('no route'); }
      })
      .catch(function () {
        if (myReq !== routeReqId) return;
        var km = straightKm(p.lat, p.lng, lat, lng) * 1.3;
        if (typeof window.onRouteResult === 'function') window.onRouteResult(km, lat, lng);
      });
  }

  function setLine(pts, dashed) {
    if (routeLine) { map.removeLayer(routeLine); }
    routeLine = L.polyline(pts, {
      color: '#2563eb', weight: 4, opacity: 0.85,
      dashArray: dashed ? '8 6' : null
    }).addTo(map);
  }

  var marker = null;
  var latInput = document.getElementById('lat');
  var lngInput = document.getElementById('lng');
  var coordsEl = document.getElementById('coords');
  var addrInput = document.getElementById('address');
  var statusEl = document.getElementById('geoStatus');

  function setPoint(lat, lng, doGeocode) {
    if (marker) { marker.setLatLng([lat, lng]); }
    else {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.on('dragend', function (e) {
        var p = e.target.getLatLng();
        save(p.lat, p.lng); reverseGeocode(p.lat, p.lng);
      });
    }
    save(lat, lng);
    if (doGeocode) reverseGeocode(lat, lng);
  }

  function save(lat, lng) {
    latInput.value = lat.toFixed(7);
    lngInput.value = lng.toFixed(7);
    if (coordsEl) coordsEl.textContent = '📍 ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
    drawRoute(lat, lng); // real yo'lni chizadi va masofani onRouteResult orqali qaytaradi
  }

  // OpenStreetMap Nominatim orqali manzilni aniqlash
  function reverseGeocode(lat, lng) {
    if (!addrInput) return;
    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&accept-language=uz')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.display_name && (!addrInput.value || addrInput.dataset.auto === '1')) {
          addrInput.value = d.display_name;
          addrInput.dataset.auto = '1';
        }
      }).catch(function () {});
  }

  map.on('click', function (e) { setPoint(e.latlng.lat, e.latlng.lng, true); });

  function locate() {
    if (!navigator.geolocation) { if (statusEl) statusEl.textContent = 'Geolokatsiya qo\'llab-quvvatlanmaydi'; return; }
    if (statusEl) statusEl.textContent = '📡 Joylashuv aniqlanmoqda...';
    navigator.geolocation.getCurrentPosition(function (pos) {
      var lat = pos.coords.latitude, lng = pos.coords.longitude;
      map.setView([lat, lng], 16);
      setPoint(lat, lng, true);
      if (statusEl) statusEl.textContent = '✅ Joylashuvingiz aniqlandi';
    }, function () {
      if (statusEl) statusEl.textContent = '⚠️ Joylashuvga ruxsat berilmadi. Xaritadan tanlang.';
    }, { enableHighAccuracy: true, timeout: 8000 });
  }

  var locBtn = document.getElementById('locBtn');
  if (locBtn) locBtn.addEventListener('click', locate);

  setTimeout(function () { map.invalidateSize(); locate(); }, 300); // sahifa ochilishida avto-aniqlash
})();

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
    if (typeof window.onLocationChange === 'function') window.onLocationChange(lat, lng);
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

// Checkout xaritasi (Leaflet + OpenStreetMap)
// Foydalanuvchi xaritaga bosib lokatsiyani tanlaydi yoki "Mening joylashuvim" tugmasini bosadi.
(function () {
  var mapEl = document.getElementById('map');
  if (!mapEl || typeof L === 'undefined') return;

  // Toshkent markazi (default)
  var def = [41.311081, 69.240562];
  var map = L.map('map').setView(def, 12);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
  }).addTo(map);

  var marker = null;
  var latInput = document.getElementById('lat');
  var lngInput = document.getElementById('lng');
  var coordsEl = document.getElementById('coords');

  function setPoint(lat, lng) {
    if (marker) {
      marker.setLatLng([lat, lng]);
    } else {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.on('dragend', function (e) {
        var p = e.target.getLatLng();
        save(p.lat, p.lng);
      });
    }
    save(lat, lng);
  }

  function save(lat, lng) {
    latInput.value = lat.toFixed(7);
    lngInput.value = lng.toFixed(7);
    coordsEl.textContent = '📍 ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
  }

  map.on('click', function (e) {
    setPoint(e.latlng.lat, e.latlng.lng);
  });

  var locBtn = document.getElementById('locBtn');
  if (locBtn) {
    locBtn.addEventListener('click', function () {
      if (!navigator.geolocation) {
        alert('Brauzer geolokatsiyani qo\'llab-quvvatlamaydi.');
        return;
      }
      navigator.geolocation.getCurrentPosition(function (pos) {
        var lat = pos.coords.latitude, lng = pos.coords.longitude;
        map.setView([lat, lng], 16);
        setPoint(lat, lng);
      }, function () {
        alert('Joylashuvni aniqlab bo\'lmadi. Xaritadan qo\'lda tanlang.');
      });
    });
  }

  // Xarita o'lchami to'g'ri ko'rinishi uchun
  setTimeout(function () { map.invalidateSize(); }, 200);
})();

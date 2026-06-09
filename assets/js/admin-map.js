// Admin: barcha faol kuryerlarni jonli xaritada ko'rish
(function () {
  var el = document.getElementById('admin-map');
  if (!el || typeof L === 'undefined') return;

  var map = L.map(el, { zoomControl: true }).setView([41.311081, 69.240562], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '© OpenStreetMap'
  }).addTo(map);

  var icon = L.divIcon({ html: '🛵', className: 'emoji-marker', iconSize: [32, 32] });
  var markers = {};
  var countEl = document.getElementById('liveCount');

  function refresh() {
    fetch('/api/couriers_live.php')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) return;
        if (countEl) countEl.textContent = d.couriers.length;
        var seen = {};
        d.couriers.forEach(function (c) {
          seen[c.id] = true;
          var label = c.name + ' · ' + c.active_orders + ' buyurtma · 📞 ' + c.phone;
          if (markers[c.id]) {
            markers[c.id].setLatLng([c.lat, c.lng]).setPopupContent(label);
          } else {
            markers[c.id] = L.marker([c.lat, c.lng], { icon: icon }).addTo(map).bindPopup(label);
          }
        });
        // Yo'qolgan kuryerlarni o'chirish
        Object.keys(markers).forEach(function (id) {
          if (!seen[id]) { map.removeLayer(markers[id]); delete markers[id]; }
        });
      }).catch(function () {});
  }

  refresh();
  setInterval(refresh, 5000);
  setTimeout(function () { map.invalidateSize(); }, 300);
})();

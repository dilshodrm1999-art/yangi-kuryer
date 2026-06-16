// Admin: barcha faol kuryerlarni jonli xaritada ko'rish (ID + ism yorlig'i bilan)
(function () {
  var el = document.getElementById('admin-map');
  if (!el || typeof L === 'undefined') return;

  var startLat = parseFloat(el.dataset.lat) || 39.509868;
  var startLng = parseFloat(el.dataset.lng) || 63.85389;
  var startZoom = parseInt(el.dataset.zoom, 10) || 13;

  var map = L.map(el, { zoomControl: true }).setView([startLat, startLng], startZoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '© OpenStreetMap'
  }).addTo(map);

  var markers = {};
  var countEl = document.getElementById('liveCount');

  // Kuryer yorlig'i: ID + ism (icon o'rniga)
  function makeIcon(c) {
    var cls = 'courier-tag' + (c.active_orders > 0 ? ' busy' : '');
    return L.divIcon({
      className: 'courier-div',
      html: '<div class="' + cls + '">' + c.short_id + ' · ' + escapeHtml(c.name) + '</div>',
      iconSize: [null, 24],
      iconAnchor: [40, 12]
    });
  }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
  }

  function refresh() {
    fetch('/api/couriers_live.php')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) return;
        if (countEl) countEl.textContent = d.couriers.length;
        // Ratsiya paneli uchun kuryerlar ro'yxatini global saqlaymiz
        window.__liveCouriers = d.couriers;
        if (typeof window.onCouriersUpdate === 'function') window.onCouriersUpdate(d.couriers);

        var seen = {};
        d.couriers.forEach(function (c) {
          seen[c.id] = true;
          var popup = '<strong>' + c.short_id + ' · ' + escapeHtml(c.name) + '</strong><br>'
                    + '📞 ' + escapeHtml(c.phone) + '<br>'
                    + '📦 ' + c.active_orders + ' ta aktiv buyurtma<br>'
                    + '<a class="btn sm primary" style="margin-top:6px;color:#fff" '
                    + 'href="/admin/ratsiya.php?courier_id=' + c.id + '">🎙 Ratsiya orqali gaplashish</a>';
          if (markers[c.id]) {
            markers[c.id].setLatLng([c.lat, c.lng]).setIcon(makeIcon(c)).setPopupContent(popup);
          } else {
            markers[c.id] = L.marker([c.lat, c.lng], { icon: makeIcon(c) }).addTo(map).bindPopup(popup);
          }
        });
        Object.keys(markers).forEach(function (id) {
          if (!seen[id]) { map.removeLayer(markers[id]); delete markers[id]; }
        });
      }).catch(function () {});
  }

  refresh();
  setInterval(refresh, 5000);
  setTimeout(function () { map.invalidateSize(); }, 300);
})();

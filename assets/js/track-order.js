// Mijoz: o'z buyurtma(lar)idagi kuryerni jonli xaritada kuzatish
// Har bir .live-map elementi data-order-id ga ega bo'ladi.
(function () {
  if (typeof L === 'undefined') return;
  var maps = document.querySelectorAll('.live-map[data-order-id]');

  maps.forEach(function (el) {
    var orderId = el.dataset.orderId;
    var map = L.map(el, { zoomControl: true }).setView([41.311081, 69.240562], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '© OpenStreetMap'
    }).addTo(map);

    var courierIcon = L.divIcon({ html: '🛵', className: 'emoji-marker', iconSize: [30, 30] });
    var destIcon    = L.divIcon({ html: '📍', className: 'emoji-marker', iconSize: [30, 30] });
    var cMarker = null, dMarker = null;
    var infoEl = document.getElementById('trackInfo-' + orderId);

    function refresh() {
      fetch('/api/order_track.php?order_id=' + orderId)
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) return;
          var pts = [];
          if (d.dest) {
            if (!dMarker) dMarker = L.marker([d.dest.lat, d.dest.lng], { icon: destIcon }).addTo(map).bindPopup('Manzilingiz');
            pts.push([d.dest.lat, d.dest.lng]);
          }
          if (d.courier && d.courier.lat != null) {
            if (!cMarker) cMarker = L.marker([d.courier.lat, d.courier.lng], { icon: courierIcon }).addTo(map).bindPopup('Kuryer: ' + d.courier.name);
            else cMarker.setLatLng([d.courier.lat, d.courier.lng]);
            pts.push([d.courier.lat, d.courier.lng]);
            if (infoEl) infoEl.textContent = '🛵 ' + d.courier.name + ' yo\'lda · ' + d.status_label;
          } else if (infoEl) {
            infoEl.textContent = d.courier ? 'Kuryer GPS hali yoqilmagan' : 'Kuryer tayinlanmoqda...';
          }
          if (pts.length === 2) map.fitBounds(pts, { padding: [40, 40] });
          else if (pts.length === 1) map.setView(pts[0], 15);
        }).catch(function () {});
    }

    refresh();
    setInterval(refresh, 5000);
    setTimeout(function () { map.invalidateSize(); }, 300);
  });
})();

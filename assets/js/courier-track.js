// Kuryer joylashuvini serverga uzatuvchi (jonli kuzatuv uchun)
(function () {
  if (!navigator.geolocation) return;
  var badge = document.getElementById('gpsBadge');

  function send(lat, lng) {
    var fd = new FormData();
    fd.append('lat', lat); fd.append('lng', lng);
    fetch('/api/location_update.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (badge) {
          badge.textContent = '🟢 GPS yoqilgan';
          badge.className = 'tag fee';
        }
      }).catch(function () {});
  }

  // Har bir o'zgarishda uzatish (watchPosition)
  navigator.geolocation.watchPosition(function (pos) {
    send(pos.coords.latitude, pos.coords.longitude);
  }, function () {
    if (badge) { badge.textContent = '🔴 GPS o\'chiq'; badge.className = 'tag'; }
  }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 });
})();

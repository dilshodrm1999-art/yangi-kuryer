// Kuryer joylashuvini serverga uzatuvchi (jonli kuzatuv uchun)
(function () {
  var badge = document.getElementById('gpsBadge');
  var gpsReq = document.getElementById('gpsRequired');
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';

  function setOn() {
    if (badge) { badge.textContent = 'GPS yoqilgan'; badge.className = 'gps-chip on'; }
    if (gpsReq) gpsReq.style.display = 'none';
  }
  function setOff() {
    if (badge) { badge.textContent = "GPS o'chiq"; badge.className = 'gps-chip off'; }
    if (gpsReq) gpsReq.style.display = '';
  }

  if (!navigator.geolocation) { setOff(); return; }

  function send(lat, lng) {
    var fd = new FormData();
    fd.append('lat', lat); fd.append('lng', lng);
    fd.append('csrf', csrf);
    fetch('/api/location_update.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-CSRF': csrf }
    })
      .then(function (r) { return r.json(); })
      .then(function () { setOn(); })
      .catch(function () {});
  }

  // Har bir o'zgarishda uzatish (watchPosition)
  navigator.geolocation.watchPosition(function (pos) {
    window.__lastPos = { lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy, t: Date.now() };
    send(pos.coords.latitude, pos.coords.longitude);
  }, function () {
    setOff();
  }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 });
})();

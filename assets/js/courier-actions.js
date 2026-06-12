// Kuryer amallari: GPS majburiy, yetkazishni 20m aniqlikda tasdiqlash, bekor so'rovi
(function () {
  var RADIUS = window.__deliveryRadius || 20;

  // Eng so'nggi aniqlangan joylashuv (courier-track.js ham yangilab turadi)
  window.__lastPos = window.__lastPos || null;
  if (navigator.geolocation) {
    navigator.geolocation.watchPosition(function (p) {
      window.__lastPos = { lat: p.coords.latitude, lng: p.coords.longitude, acc: p.coords.accuracy, t: Date.now() };
    }, function () {}, { enableHighAccuracy: true, maximumAge: 4000, timeout: 15000 });
  }

  function haversineM(aLat, aLng, bLat, bLng) {
    var R = 6371000, p = Math.PI / 180;
    var x = Math.sin((bLat - aLat) * p / 2) ** 2 +
            Math.cos(aLat * p) * Math.cos(bLat * p) * Math.sin((bLng - aLng) * p / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
  }

  function freshPos() {
    var p = window.__lastPos;
    if (!p) return null;
    if (Date.now() - p.t > 60000) return null; // 60s dan eski bo'lsa ishonchsiz
    return p;
  }

  // Holatni o'zgartirish formalariga joriy GPS qo'shish (Oldim/Yo'ldaman)
  document.querySelectorAll('.js-gps-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!freshPos()) {
        e.preventDefault();
        window.toast && window.toast("Geolokatsiya o'chiq. GPS'ni yoqing.", 'error');
      }
    });
  });

  // "Yetkazdim" — mijoz manziliga 20m yaqinlikni tekshirish
  document.querySelectorAll('.js-deliver-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      var pos = freshPos();
      if (!pos) {
        e.preventDefault();
        window.toast && window.toast("Geolokatsiya o'chiq. Yetkazishni tasdiqlash uchun GPS'ni yoqing.", 'error');
        return;
      }
      var dLat = parseFloat(f.dataset.destLat), dLng = parseFloat(f.dataset.destLng);
      if (!isNaN(dLat) && !isNaN(dLng)) {
        var dist = haversineM(pos.lat, pos.lng, dLat, dLng);
        if (dist > RADIUS) {
          e.preventDefault();
          window.toast && window.toast('Manzilgacha ' + Math.round(dist) + ' m. ' + RADIUS + ' m ichiga yaqinlashing.', 'error');
          return;
        }
      }
      // Koordinatani formaga yozamiz (server ham qayta tekshiradi)
      f.querySelector('.cur-lat').value = pos.lat.toFixed(7);
      f.querySelector('.cur-lng').value = pos.lng.toFixed(7);
    });
  });

  // Bekor qilish so'rovi (adminga yuboriladi)
  document.querySelectorAll('.js-cancel-req').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var reason = prompt("Bekor qilish sababini yozing (admin ko'rib chiqadi):", '');
      if (reason === null) return; // foydalanuvchi bekor qildi
      document.getElementById('cancelOrderId').value = btn.dataset.order;
      document.getElementById('cancelReason').value = reason;
      document.getElementById('cancelForm').submit();
    });
  });
})();

// Admin sozlamalari: hudud tanlash + shahar poligonini chizish + ombor nuqtasi
(function () {
  if (typeof L === 'undefined') return;

  var REGIONS = window.UZ_REGIONS || {};
  var INIT = window.SETTINGS_INIT || {};

  var regionSel = document.getElementById('regionSel');
  var districtSel = document.getElementById('districtSel');
  var mapLatEl = document.getElementById('mapLat');
  var mapLngEl = document.getElementById('mapLng');
  var mapZoomEl = document.getElementById('mapZoom');
  var polyEl = document.getElementById('cityPolygon');
  var latEl = document.getElementById('lat');
  var lngEl = document.getElementById('lng');
  var coordsEl = document.getElementById('coords');
  var modeInfo = document.getElementById('modeInfo');

  // --- Xarita ---
  var map = L.map('map').setView([INIT.mapLat || 39.509868, INIT.mapLng || 63.85389], INIT.mapZoom || 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '\u00a9 OpenStreetMap'
  }).addTo(map);

  // --- Tumanlar ro'yxatini to'ldirish ---
  function fillDistricts(region, selected) {
    districtSel.innerHTML = '<option value="">— barcha tumanlar —</option>';
    var info = REGIONS[region];
    if (info && info.districts) {
      info.districts.forEach(function (d) {
        var opt = document.createElement('option');
        opt.value = d; opt.textContent = d;
        if (d === selected) opt.selected = true;
        districtSel.appendChild(opt);
      });
    }
  }

  // --- Ombor markeri ---
  var storeMarker = L.marker([INIT.storeLat || 39.509868, INIT.storeLng || 63.85389], {
    draggable: true, title: 'Ombor'
  }).addTo(map).bindPopup('📦 Ombor');

  function saveStore(lat, lng) {
    latEl.value = lat.toFixed(7);
    lngEl.value = lng.toFixed(7);
    if (coordsEl) coordsEl.textContent = '📦 Ombor: ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
  }
  storeMarker.on('dragend', function (e) {
    var p = e.target.getLatLng();
    saveStore(p.lat, p.lng);
  });

  // --- Shahar poligoni ---
  var polyPoints = [];
  try {
    var parsed = JSON.parse(polyEl.value || '[]');
    if (Array.isArray(parsed)) polyPoints = parsed.slice();
  } catch (e) { polyPoints = []; }

  var polygonLayer = null;
  var vertexMarkers = [];

  function renderPolygon() {
    if (polygonLayer) { map.removeLayer(polygonLayer); polygonLayer = null; }
    vertexMarkers.forEach(function (m) { map.removeLayer(m); });
    vertexMarkers = [];

    if (polyPoints.length >= 2) {
      polygonLayer = L.polygon(polyPoints, {
        color: '#ff6b35', weight: 2, fillColor: '#ff6b35', fillOpacity: 0.12
      }).addTo(map);
    }
    // Har bir nuqtaga draggable marker
    polyPoints.forEach(function (pt, idx) {
      var vm = L.circleMarker(pt, {
        radius: 6, color: '#fff', weight: 2, fillColor: '#ff6b35', fillOpacity: 1
      }).addTo(map);
      vm.on('click', function (ev) {
        // Shift+klik = nuqtani o'chirish
        if (window.__settingsMode !== 'draw') return;
        L.DomEvent.stopPropagation(ev);
        polyPoints.splice(idx, 1);
        syncPolygon();
      });
      vertexMarkers.push(vm);
    });
    polyEl.value = JSON.stringify(polyPoints);
  }

  function syncPolygon() {
    renderPolygon();
  }

  // --- Rejimlar: 'view' | 'draw' | 'store' ---
  window.__settingsMode = 'view';
  function setMode(mode) {
    window.__settingsMode = mode;
    if (modeInfo) {
      modeInfo.textContent = mode === 'draw'
        ? '✏️ Rejim: shahar chizig\'ini chizish (xaritaga bosing, nuqtaga bosib o\'chiring)'
        : (mode === 'store' ? '📦 Rejim: ombor joyini belgilash (xaritaga bosing)' : '🟢 Rejim: ko\'rish');
    }
  }

  var drawBtn = document.getElementById('drawBtn');
  var clearBtn = document.getElementById('clearBtn');
  var storeBtn = document.getElementById('storeBtn');
  if (drawBtn) drawBtn.addEventListener('click', function () {
    setMode(window.__settingsMode === 'draw' ? 'view' : 'draw');
  });
  if (storeBtn) storeBtn.addEventListener('click', function () {
    setMode(window.__settingsMode === 'store' ? 'view' : 'store');
  });
  if (clearBtn) clearBtn.addEventListener('click', function () {
    polyPoints = [];
    syncPolygon();
  });

  map.on('click', function (e) {
    if (window.__settingsMode === 'draw') {
      polyPoints.push([+e.latlng.lat.toFixed(7), +e.latlng.lng.toFixed(7)]);
      syncPolygon();
    } else if (window.__settingsMode === 'store') {
      storeMarker.setLatLng(e.latlng);
      saveStore(e.latlng.lat, e.latlng.lng);
    }
  });

  // Xarita harakatlanganda markaz/zoom ni saqlaymiz
  map.on('moveend', function () {
    var c = map.getCenter();
    mapLatEl.value = c.lat.toFixed(7);
    mapLngEl.value = c.lng.toFixed(7);
    mapZoomEl.value = map.getZoom();
  });

  // --- Hudud o'zgarganda xaritani markazga olib o'tish ---
  // Xarita allaqachon sozlangan bo'lsa (poligon yoki ombor belgilangan),
  // hududni o'zgartirishda tasdiq so'raymiz — tasodifan markaz o'zgarib ketmasin.
  regionSel.addEventListener('change', function () {
    var info = REGIONS[this.value];
    fillDistricts(this.value, '');
    if (!info) return;
    var configured = polyPoints.length >= 3;
    if (configured) {
      if (!confirm('Hududni o\'zgartirsangiz, xarita yangi hudud markaziga o\'tadi. Belgilangan shahar chizig\'i saqlanib qoladi. Davom etamizmi?')) {
        return;
      }
    }
    map.setView([info.lat, info.lng], info.zoom || 12);
  });
  districtSel.addEventListener('change', function () {
    // Tuman tanlansa, viloyat markazida qoladi (aniq tuman koordinatasi yo'q)
  });

  // Boshlang'ich holat
  fillDistricts(INIT.region, INIT.district);
  renderPolygon();

  setTimeout(function () { map.invalidateSize(); }, 300);
})();

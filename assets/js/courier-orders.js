// Kuryer: yangi buyurtmalarni tekshirib, signal (tovush) chaladi
(function () {
  var lastId = window.__lastOrderId || 0;
  var alertEl = document.getElementById('newOrderAlert');
  var countEl = document.getElementById('availCount');
  var audioCtx = null;

  // Audio kontekstini foydalanuvchi harakatidan keyin yoqamiz (brauzer talabi)
  function ensureAudio() {
    if (!audioCtx) {
      try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); }
      catch (e) { audioCtx = null; }
    }
    if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
  }
  document.addEventListener('click', ensureAudio, { once: false });
  ensureAudio();

  // Signal tovushi (uch marta "bip")
  function beep() {
    if (!audioCtx) return;
    [0, 0.25, 0.5].forEach(function (t) {
      var osc = audioCtx.createOscillator();
      var gain = audioCtx.createGain();
      osc.type = 'sine';
      osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.001, audioCtx.currentTime + t);
      gain.gain.exponentialRampToValueAtTime(0.4, audioCtx.currentTime + t + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + t + 0.18);
      osc.connect(gain); gain.connect(audioCtx.destination);
      osc.start(audioCtx.currentTime + t);
      osc.stop(audioCtx.currentTime + t + 0.2);
    });
  }

  function vibrate() {
    if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
  }

  function poll() {
    fetch('/api/courier_new_orders.php')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) return;
        if (countEl) countEl.textContent = d.count;

        // Yangi (oldingidan katta ID) buyurtma paydo bo'ldi
        if (d.latest_id > lastId) {
          lastId = d.latest_id;
          beep();
          vibrate();
          if (alertEl) alertEl.style.display = 'flex';
          if (document.title.indexOf('🔔') === -1) document.title = '🔔 ' + document.title;
        }
      })
      .catch(function () {});
  }

  poll();
  setInterval(poll, 8000); // har 8 soniyada tekshirish
})();

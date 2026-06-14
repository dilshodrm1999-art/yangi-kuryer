// ============================================================
//  RATSIYA — admin <-> kuryer ovozli aloqa
//  - Bosib turib gapiriladi (push-to-talk)
//  - Kelgan ovoz AVTOMATIK bir marta eshittiriladi (kuryerda saqlanmaydi)
//  - Admin ratsiya sahifasida to'liq yozishmalar tarixi ko'rinadi
// ============================================================
(function () {
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var body = document.body.className;
  var role = body.indexOf('role-admin') >= 0 ? 'admin'
           : (body.indexOf('role-courier') >= 0 ? 'courier' : '');
  if (!role) return;

  // ---------- Ovoz yozish (MediaRecorder) ----------
  var mediaRecorder = null, chunks = [], stream = null;

  function pickMime() {
    var types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg', 'audio/mp4'];
    for (var i = 0; i < types.length; i++) {
      if (window.MediaRecorder && MediaRecorder.isTypeSupported(types[i])) return types[i];
    }
    return '';
  }

  function startRec(statusEl, onReady) {
    if (!navigator.mediaDevices || !window.MediaRecorder) {
      if (statusEl) statusEl.textContent = "Brauzer ovoz yozishni qo'llamaydi";
      return false;
    }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
      stream = s;
      var mime = pickMime();
      mediaRecorder = mime ? new MediaRecorder(s, { mimeType: mime }) : new MediaRecorder(s);
      chunks = [];
      mediaRecorder.ondataavailable = function (e) { if (e.data.size > 0) chunks.push(e.data); };
      mediaRecorder.onstop = function () {
        var blob = new Blob(chunks, { type: mediaRecorder.mimeType || 'audio/webm' });
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        if (blob.size > 800) onReady(blob);
        else if (statusEl) statusEl.textContent = "Juda qisqa";
      };
      mediaRecorder.start();
      if (statusEl) statusEl.textContent = "🔴 Yozilmoqda...";
    }).catch(function () {
      if (statusEl) statusEl.textContent = "Mikrofonga ruxsat berilmadi";
    });
    return true;
  }

  function stopRec() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
  }

  function sendVoice(blob, receiverId, statusEl, onDone) {
    var ext = (blob.type.indexOf('ogg') >= 0) ? 'ogg' : (blob.type.indexOf('mp4') >= 0 ? 'm4a' : 'webm');
    var fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('audio', blob, 'voice.' + ext);
    if (receiverId) fd.append('receiver_id', receiverId);
    if (statusEl) statusEl.textContent = "📤 Yuborilmoqda...";
    fetch('/api/voice_send.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (statusEl) statusEl.textContent = d.ok ? "✅ Yuborildi" : ("Xato: " + (d.error || ''));
        setTimeout(function () { if (statusEl) statusEl.textContent = ''; }, 2200);
        if (d.ok && typeof onDone === 'function') onDone(d);
      })
      .catch(function () { if (statusEl) statusEl.textContent = "Tarmoq xatosi"; });
  }

  // ---------- Push-to-talk tugma ----------
  function bindPTT(btn, statusEl, getReceiver, onSent) {
    if (!btn) return;
    var holding = false;
    function begin(e) {
      e.preventDefault();
      if (btn.disabled) return;
      var rcv = getReceiver ? getReceiver() : null;
      if (getReceiver && !rcv) { if (statusEl) statusEl.textContent = "Avval kuryer tanlang"; return; }
      holding = true;
      btn.classList.add('rec');
      startRec(statusEl, function (blob) { sendVoice(blob, rcv, statusEl, onSent); });
    }
    function end(e) {
      if (!holding) return;
      e.preventDefault();
      holding = false;
      btn.classList.remove('rec');
      stopRec();
    }
    btn.addEventListener('mousedown', begin);
    btn.addEventListener('touchstart', begin, { passive: false });
    document.addEventListener('mouseup', end);
    document.addEventListener('touchend', end);
    btn.addEventListener('mouseleave', end);
  }

  // ---------- Kelgan ovozni AVTOMATIK eshittirish (bir marta) ----------
  var lastHeard = 0;
  function autoplayPoll() {
    fetch('/api/voice_poll.php?after=' + lastHeard)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok || !d.messages.length) return;
        var i = 0;
        function playNext() {
          if (i >= d.messages.length) return;
          var m = d.messages[i++];
          lastHeard = Math.max(lastHeard, m.id);
          var a = new Audio(m.audio);
          a.onended = playNext;
          a.onerror = playNext;
          a.play().catch(function () { playNext(); });
          if (navigator.vibrate) navigator.vibrate(120);
          // Agar admin ratsiya sahifasi ochiq bo'lsa, tarixni yangilaymiz
          if (window.__rtsRefresh) window.__rtsRefresh();
        }
        playNext();
      }).catch(function () {});
  }

  // ====== KURYER tomoni ======
  if (role === 'courier') {
    var cPtt = document.getElementById('courierPtt');
    var cStatus = document.getElementById('courierPttStatus');
    bindPTT(cPtt, cStatus, null, null); // kuryer -> barcha adminlarga
    autoplayPoll();
    setInterval(autoplayPoll, 4000);
    return;
  }

  // ====== ADMIN tomoni ======
  // Har doim: kelgan kuryer ovozini avto-eshittirish
  autoplayPoll();
  setInterval(autoplayPoll, 4000);

  // Admin RATSIYA sahifasi (to'liq yozishma + gapirish)
  var logEl = document.getElementById('rtsLog');
  if (logEl) {
    var courierId = window.__rtsCourierId || 0;
    var lastLogId = 0;

    function fmtMsg(m) {
      var who = m.dir === 'out'
        ? ('🧑‍💼 ' + (m.operator || 'Operator') + ' → ' + m.short + ' ' + m.courier)
        : ('🛵 ' + m.short + ' ' + m.courier + ' → operator');
      var cls = m.dir === 'out' ? 'out' : 'in';
      return '<div class="rts-msg ' + cls + '">'
           + '<div class="rts-meta">' + who + ' <span class="rts-time">' + m.date + ' ' + m.time + '</span></div>'
           + '<audio controls preload="none" src="' + m.audio + '"></audio>'
           + '</div>';
    }

    function loadLog(reset) {
      var url = '/api/voice_log.php?after=' + (reset ? 0 : lastLogId);
      if (courierId) url += '&courier_id=' + courierId;
      fetch(url).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok) return;
        if (reset) { logEl.innerHTML = ''; lastLogId = 0; }
        if (!d.messages.length && reset) {
          logEl.innerHTML = '<div class="rts-empty muted">Hozircha yozishma yo\'q. Mikrofonni bosib gapiring.</div>';
          return;
        }
        var emptyEl = logEl.querySelector('.rts-empty');
        if (emptyEl && d.messages.length) emptyEl.remove();
        d.messages.forEach(function (m) {
          lastLogId = Math.max(lastLogId, m.id);
          logEl.insertAdjacentHTML('beforeend', fmtMsg(m));
        });
        logEl.scrollTop = logEl.scrollHeight;
      }).catch(function () {});
    }

    window.__rtsRefresh = function () { loadLog(false); };
    loadLog(true);
    setInterval(function () { loadLog(false); }, 4000);

    // Tanlangan kuryerga gapirish
    var aPtt = document.getElementById('adminPtt');
    var aStatus = document.getElementById('adminPttStatus');
    bindPTT(aPtt, aStatus, function () { return courierId || null; }, function () { loadLog(false); });
  }
})();

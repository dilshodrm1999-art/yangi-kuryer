// Ratsiya (ovozli xabar) — admin <-> kuryer
// MediaRecorder bilan ovoz yozib, serverga yuboradi; yangi xabarlarni polling bilan oladi va chaladi.
(function () {
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var role = document.body.className.indexOf('role-admin') >= 0 ? 'admin'
           : (document.body.className.indexOf('role-courier') >= 0 ? 'courier' : '');
  if (!role) return;

  // ---- Yozib olish (record) ----
  var mediaRecorder = null, chunks = [], stream = null;

  function pickMime() {
    var types = ['audio/webm', 'audio/ogg', 'audio/mp4'];
    for (var i = 0; i < types.length; i++) {
      if (window.MediaRecorder && MediaRecorder.isTypeSupported(types[i])) return types[i];
    }
    return '';
  }

  function startRec(statusEl, onReady) {
    if (!navigator.mediaDevices || !window.MediaRecorder) {
      if (statusEl) statusEl.textContent = 'Brauzer ovoz yozishni qo\'llamaydi';
      return;
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
        onReady(blob);
      };
      mediaRecorder.start();
      if (statusEl) statusEl.textContent = '🔴 Yozilmoqda...';
    }).catch(function () {
      if (statusEl) statusEl.textContent = 'Mikrofonga ruxsat berilmadi';
    });
  }

  function stopRec() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
  }

  function sendVoice(blob, receiverId, statusEl) {
    var ext = (blob.type.indexOf('ogg') >= 0) ? 'ogg' : (blob.type.indexOf('mp4') >= 0 ? 'm4a' : 'webm');
    var fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('audio', blob, 'voice.' + ext);
    if (receiverId) fd.append('receiver_id', receiverId);
    if (statusEl) statusEl.textContent = '📤 Yuborilmoqda...';
    fetch('/api/voice_send.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (statusEl) statusEl.textContent = d.ok ? '✅ Yuborildi' : ('Xato: ' + (d.error || ''));
        setTimeout(function () { if (statusEl) statusEl.textContent = ''; }, 2500);
      })
      .catch(function () { if (statusEl) statusEl.textContent = 'Tarmoq xatosi'; });
  }

  // Push-to-talk tugmasini ulash (bosib turib gapirish)
  function bindPTT(btn, statusEl, getReceiver) {
    if (!btn) return;
    var holding = false;
    function begin(e) {
      e.preventDefault();
      var rcv = getReceiver ? getReceiver() : null;
      if (getReceiver && !rcv) { if (statusEl) statusEl.textContent = 'Avval kuryer tanlang'; return; }
      holding = true;
      btn.classList.add('rec');
      startRec(statusEl, function (blob) { sendVoice(blob, rcv, statusEl); });
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
    btn.addEventListener('touchend', end);
  }

  // ---- Kelgan xabarlarni olish va chalish ----
  var lastId = 0;
  function poll(inboxEl) {
    fetch('/api/voice_poll.php?after=' + lastId)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok || !d.messages.length) return;
        d.messages.forEach(function (m) {
          lastId = Math.max(lastId, m.id);
          // Avtomatik chalish
          var audio = new Audio(m.audio);
          audio.play().catch(function () {});
          if (navigator.vibrate) navigator.vibrate(150);
          // Inbox ro'yxatiga qo'shish (admin uchun)
          if (inboxEl) {
            var div = document.createElement('div');
            div.className = 'voice-msg';
            div.innerHTML = '<span>🎙 <strong>' + m.sender_short + '</strong> · ' + m.sender_name +
                            ' <span class="muted small">' + m.created_at + '</span></span>';
            var a = document.createElement('audio');
            a.controls = true; a.src = m.audio;
            div.appendChild(a);
            inboxEl.prepend(div);
          }
        });
      }).catch(function () {});
  }

  // ====== ADMIN tomoni ======
  if (role === 'admin') {
    var selCourier = document.getElementById('selCourier');
    var adminPtt = document.getElementById('adminPtt');
    var adminStatus = document.getElementById('adminPttStatus');
    var inbox = document.getElementById('voiceInbox');
    var selectedId = null;

    window.selectCourierForVoice = function (id, name) {
      selectedId = id;
      if (selCourier) selCourier.textContent = name + ' tanlandi';
      if (adminPtt) adminPtt.disabled = false;
    };
    bindPTT(adminPtt, adminStatus, function () { return selectedId; });

    if (inbox) { poll(inbox); setInterval(function () { poll(inbox); }, 5000); }
  }

  // ====== KURYER tomoni ======
  if (role === 'courier') {
    var cPtt = document.getElementById('courierPtt');
    var cStatus = document.getElementById('courierPttStatus');
    bindPTT(cPtt, cStatus, null); // kuryer -> barcha adminlarga
    poll(null);
    setInterval(function () { poll(null); }, 5000);
  }
})();

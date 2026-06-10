// Umumiy frontend skriptlar
(function () {
  // --- Tasdiqlash formalari ---
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!confirm(f.dataset.confirm)) e.preventDefault();
    });
  });

  // --- Toast bildirishnoma ---
  window.toast = function (msg, type) {
    var t = document.createElement('div');
    t.className = 'toast ' + (type || '');
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    setTimeout(function () {
      t.classList.remove('show');
      setTimeout(function () { t.remove(); }, 300);
    }, 2200);
  };

  // --- Savat hisoblagichini yangilash ---
  function updateCartBadges(count) {
    document.querySelectorAll('.cart-badge').forEach(function (b) {
      b.textContent = count;
      b.style.display = count > 0 ? '' : 'none';
    });
  }

  // --- Savatga AJAX qo'shish (sahifa yangilanmaydi) ---
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  document.querySelectorAll('.js-add').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var pid = btn.dataset.productId;
      btn.disabled = true;
      var fd = new FormData();
      fd.append('product_id', pid);
      fd.append('csrf', csrf);
      fetch('/api/cart_add.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (d) {
          btn.disabled = false;
          if (d.ok) {
            updateCartBadges(d.count);
            btn.classList.add('added');
            setTimeout(function () { btn.classList.remove('added'); }, 600);
            window.toast('✅ ' + d.name + ' savatga qo\'shildi', 'success');
          } else {
            window.toast(d.msg || 'Xatolik yuz berdi', 'error');
          }
        })
        .catch(function () { btn.disabled = false; window.toast('Tarmoq xatosi', 'error'); });
    });
  });
})();

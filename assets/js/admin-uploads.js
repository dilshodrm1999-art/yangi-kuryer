// Rasm yuklash: jonli ko'rish (preview) + kuryer rasmlari modal
(function () {
  // Fayl tanlanganda preview ko'rsatish
  function bindPreview(input) {
    var targetId = input.dataset.preview;
    var box = targetId ? document.getElementById(targetId) : null;
    input.addEventListener('change', function () {
      if (!box) return;
      var f = input.files && input.files[0];
      if (!f) { box.style.backgroundImage = ''; box.classList.remove('has'); return; }
      var url = URL.createObjectURL(f);
      box.style.backgroundImage = "url('" + url + "')";
      box.classList.add('has');
    });
  }
  document.querySelectorAll('.js-file').forEach(bindPreview);

  // Kuryer rasmlari modali
  var modal = document.getElementById('photoModal');
  if (modal) {
    var pmName = document.getElementById('pmName');
    var pmId = document.getElementById('pmId');
    var pmPhotoCur = document.getElementById('pmPhotoCur');
    var pmPassCur = document.getElementById('pmPassCur');
    var pmPhotoUrl = document.getElementById('pmPhotoUrl');
    var pmPassUrl = document.getElementById('pmPassUrl');

    function setBg(box, url) {
      if (!box) return;
      if (url) { box.style.backgroundImage = "url('" + url + "')"; box.classList.add('has'); }
      else { box.style.backgroundImage = ''; box.classList.remove('has'); }
    }

    document.querySelectorAll('.js-edit-photos').forEach(function (btn) {
      btn.addEventListener('click', function () {
        pmName.textContent = btn.dataset.name || '';
        pmId.value = btn.dataset.id || '';
        setBg(pmPhotoCur, btn.dataset.photo);
        setBg(pmPassCur, btn.dataset.passport);
        if (pmPhotoUrl) pmPhotoUrl.value = '';
        if (pmPassUrl) pmPassUrl.value = '';
        modal.style.display = 'flex';
      });
    });

    var closeBtn = document.getElementById('pmClose');
    if (closeBtn) closeBtn.addEventListener('click', function () { modal.style.display = 'none'; });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });
  }
})();

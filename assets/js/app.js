// Umumiy frontend skriptlar
// Toast / kichik animatsiyalar uchun joy.
(function () {
  // Bosilganda tugmalarga vaqtinchalik "loading" holati
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!confirm(f.dataset.confirm)) e.preventDefault();
    });
  });
})();

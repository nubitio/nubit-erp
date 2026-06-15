(function () {
  var overlay = document.getElementById('receipt-search-result');
  if (!overlay) {
    return;
  }

  if (overlay.parentElement !== document.body) {
    document.body.appendChild(overlay);
  }

  document.body.classList.add('receipt-public--has-result');

  var scrim = overlay.querySelector('.receipt-search-overlay__scrim');
  if (scrim) {
    scrim.addEventListener('click', function (event) {
      event.preventDefault();
      window.location.href = scrim.getAttribute('href') || '/buscar?clear=1';
    });
  }
})();
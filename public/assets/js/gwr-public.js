// PATCH: only modified open handler part
(function () {
  function qs(root, selector) { return root.querySelector(selector); }
  function qsa(root, selector) { return Array.prototype.slice.call(root.querySelectorAll(selector)); }

  function initModal(catalog) {
    var modal = qs(catalog, '[data-gwr-modal]');
    var content = qs(catalog, '[data-gwr-modal-content]');

    function openFromButton(btn) {
      try {
        var b64 = btn.getAttribute('data-gwr-vehicle-b64');
        if (!b64) return;
        var json = atob(b64);
        var vehicle = JSON.parse(json);
        content.innerHTML = renderModalContent(vehicle, {});
        modal.hidden = false;
        document.body.classList.add('gwr-modal-open');
      } catch (e) {
        console.error('Modal error', e);
        alert(gwrCatalog.i18n.modalError || 'Errore apertura veicolo');
      }
    }

    catalog.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-gwr-open-modal]');
      var closeTrigger = event.target.closest('[data-gwr-close-modal]');

      if (trigger) {
        event.preventDefault();
        openFromButton(trigger);
      }
      if (closeTrigger) {
        modal.hidden = true;
        document.body.classList.remove('gwr-modal-open');
      }
    });
  }
})();
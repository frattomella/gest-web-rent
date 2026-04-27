(function () {
  function qs(root, selector) { return root ? root.querySelector(selector) : null; }
  function qsa(root, selector) { return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : []; }
  function escapeHtml(value) { return String(value || '').replace(/[&<>"']/g, function (char) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]; }); }
  function debounce(fn, delay) { var timer; return function () { var args = arguments; window.clearTimeout(timer); timer = window.setTimeout(function () { fn.apply(null, args); }, delay); }; }
  function formDataObject(form) { var data = {}; if (!form) return data; new FormData(form).forEach(function (value, key) { data[key] = value; }); return data; }
  function catalogConfig() { return window.gwrCatalog || { ajaxUrl: '', nonce: '', i18n: {}, contact: {} }; }

  function validDateRange(data, errorNode) {
    var cfg = catalogConfig();
    if (data.start_date && data.end_date && data.end_date < data.start_date) {
      if (errorNode) { errorNode.textContent = cfg.i18n.dateError || 'La data fine non puo precedere la data inizio.'; errorNode.hidden = false; }
      return false;
    }
    if (errorNode) { errorNode.hidden = true; errorNode.textContent = ''; }
    return true;
  }

  function decodeVehiclePayload(trigger) {
    var b64 = trigger.getAttribute('data-gwr-vehicle-b64');
    if (b64) { return JSON.parse(window.atob(b64)); }
    var raw = trigger.getAttribute('data-gwr-vehicle');
    if (raw) { return JSON.parse(raw); }
    throw new Error('Missing vehicle payload');
  }

  function replaceTokens(template, vehicle, dates) {
    var cfg = catalogConfig();
    var start = dates.start_date || cfg.i18n.datesGeneric || 'Date da definire';
    var end = dates.end_date || dates.start_date || cfg.i18n.datesGeneric || 'Date da definire';
    var map = {'{vehicle_title}': vehicle.title || '', '{start_date}': start, '{end_date}': end, '{site_url}': cfg.contact.siteUrl || window.location.href, '{vehicle_url}': cfg.contact.siteUrl || window.location.href, '{dealer_name}': cfg.contact.dealerName || '', '{brand}': vehicle.brand || '', '{model}': vehicle.model || '', '{version}': vehicle.version || '', '{daily_price}': vehicle.daily_price || '', '{weekly_price}': vehicle.weekly_price || '', '{monthly_price}': vehicle.monthly_price || ''};
    return String(template || '').replace(/\{[a-z_]+\}/g, function (token) { return Object.prototype.hasOwnProperty.call(map, token) ? map[token] : token; });
  }

  function contactLinks(vehicle, dates) {
    var cfg = catalogConfig();
    var hasDates = !!(dates.start_date || dates.end_date);
    var whatsappTemplate = hasDates ? (cfg.contact.whatsappTemplate || 'Ciao, vorrei informazioni sul noleggio del veicolo {vehicle_title}. Date di interesse: {start_date} - {end_date}. Link: {site_url}') : 'Ciao, vorrei informazioni sul noleggio del veicolo {vehicle_title}. Link: {site_url}';
    var emailBody = hasDates ? (cfg.contact.emailBody || 'Buongiorno,\nvorrei ricevere informazioni sul noleggio del veicolo {vehicle_title}.\n\nDate di interesse:\nDal {start_date} al {end_date}\n\nGrazie.') : 'Buongiorno,\nvorrei ricevere informazioni sul noleggio del veicolo {vehicle_title}.\n\nDate di interesse: Date da definire.\n\nGrazie.';
    var subject = replaceTokens(cfg.contact.emailSubject || 'Richiesta noleggio {vehicle_title}', vehicle, dates);
    var body = replaceTokens(emailBody, vehicle, dates);
    var links = {};
    if (cfg.contact.whatsappNumber) links.whatsapp = 'https://wa.me/' + encodeURIComponent(cfg.contact.whatsappNumber) + '?text=' + encodeURIComponent(replaceTokens(whatsappTemplate, vehicle, dates));
    if (cfg.contact.contactEmail) links.email = 'mailto:' + encodeURIComponent(cfg.contact.contactEmail) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
    return links;
  }

  function detailRow(label, value) {
    if (value === undefined || value === null || value === '' || value === false) return '';
    if (value === true) value = 'Si';
    return '<div><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>';
  }

  function renderModalContent(vehicle, dates) {
    var cfg = catalogConfig();
    var images = vehicle.images && vehicle.images.length ? vehicle.images : [];
    var links = contactLinks(vehicle, dates || {});
    var mainImage = images[0] ? images[0].url : '';
    var thumbs = images.map(function (image, index) { return '<button type="button" data-gwr-gallery-index="' + index + '"' + (index === 0 ? ' class="is-active"' : '') + '><img src="' + escapeHtml(image.url) + '" alt="" /></button>'; }).join('');
    var features = vehicle.features && vehicle.features.length ? '<ul class="gwr-modal-features">' + vehicle.features.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') + '</ul>' : '<p class="gwr-muted">Dotazioni da definire.</p>';
    return ['<div class="gwr-modal-grid">','<div class="gwr-modal-gallery">', mainImage ? '<img class="gwr-modal-main-image" src="' + escapeHtml(mainImage) + '" alt="' + escapeHtml(vehicle.title) + '" data-gwr-modal-image />' : '<div class="gwr-modal-image-empty">Foto veicolo</div>', images.length > 1 ? '<button type="button" class="gwr-gallery-arrow is-prev" data-gwr-gallery-prev>&#8249;</button><button type="button" class="gwr-gallery-arrow is-next" data-gwr-gallery-next>&#8250;</button><div class="gwr-gallery-thumbs">' + thumbs + '</div>' : '', '</div>','<div class="gwr-modal-copy">','<span class="gwr-kicker">' + escapeHtml(vehicle.category || 'Noleggio') + '</span>','<h2 id="gwr-modal-title">' + escapeHtml(vehicle.title) + '</h2>','<p class="gwr-modal-subtitle">' + escapeHtml([vehicle.brand, vehicle.model, vehicle.version].filter(Boolean).join(' ')) + '</p>','<div class="gwr-modal-price-strip">', detailRow('Giorno', vehicle.daily_price), detailRow('Weekend', vehicle.weekend_price), detailRow('Settimana', vehicle.weekly_price), detailRow('Mese', vehicle.monthly_price), '</div>','<div class="gwr-modal-details">', detailRow('Km/giorno', vehicle.included_km_daily), detailRow('Km/settimana', vehicle.included_km_weekly), detailRow('Km/mese', vehicle.included_km_monthly), detailRow('Costo km extra', vehicle.extra_km_price), detailRow('Cauzione', vehicle.deposit), detailRow('Franchigia', vehicle.deductible), detailRow('Eta minima', vehicle.min_driver_age), detailRow('Anni patente', vehicle.min_license_years), detailRow('Patente', vehicle.required_license), detailRow('Durata minima', vehicle.min_rental_days), detailRow('Durata massima', vehicle.max_rental_days), detailRow('Assicurazione inclusa', vehicle.insurance_included), detailRow('Secondo conducente', vehicle.second_driver_included), detailRow('Consegna domicilio', vehicle.home_delivery), detailRow('Sede', vehicle.location), '</div>','<section class="gwr-modal-section"><h3>Dotazioni</h3>' + features + '</section>', vehicle.description ? '<section class="gwr-modal-section"><h3>Descrizione</h3><div class="gwr-modal-text">' + vehicle.description + '</div></section>' : '', vehicle.rental_notes ? '<section class="gwr-modal-section"><h3>Note noleggio</h3><div class="gwr-modal-text">' + vehicle.rental_notes + '</div></section>' : '', '<div class="gwr-modal-contact"><p>' + escapeHtml(cfg.contact.privacyNote || '') + '</p><div class="gwr-modal-contact__actions">', links.whatsapp ? '<a class="gwr-button" target="_blank" rel="noopener noreferrer" href="' + escapeHtml(links.whatsapp) + '">WhatsApp</a>' : '', links.email ? '<a class="gwr-button-secondary" href="' + escapeHtml(links.email) + '">Email</a>' : '', '</div></div>','</div></div>'].join('');
  }

  function openModalFromTrigger(trigger) {
    var catalog = trigger.closest('[data-gwr-catalog]') || document;
    var modal = qs(catalog, '[data-gwr-modal]') || qs(document, '[data-gwr-modal]');
    var content = modal ? qs(modal, '[data-gwr-modal-content]') : null;
    if (!modal || !content) throw new Error('Missing modal shell');
    var vehicle = decodeVehiclePayload(trigger);
    var dates = formDataObject(qs(catalog, '[data-gwr-filter-form]'));
    content.innerHTML = renderModalContent(vehicle, dates);
    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open');
    document.documentElement.classList.add('gwr-modal-open');
    document.body.classList.add('gwr-modal-open');
    var closeButton = qs(modal, '.gwr-modal__close');
    if (closeButton) closeButton.focus();
  }

  function closeModal(modal) {
    if (!modal) return;
    var content = qs(modal, '[data-gwr-modal-content]');
    modal.classList.remove('is-open');
    modal.hidden = true;
    modal.setAttribute('hidden', 'hidden');
    modal.setAttribute('aria-hidden', 'true');
    if (content) content.innerHTML = '';
    document.documentElement.classList.remove('gwr-modal-open');
    document.body.classList.remove('gwr-modal-open');
  }

  function initCatalog(catalog) {
    var form = qs(catalog, '[data-gwr-filter-form]');
    var results = qs(catalog, '[data-gwr-results]');
    var count = qs(catalog, '[data-gwr-count]');
    var errorNode = qs(catalog, '[data-gwr-error]');
    if (!form || !results) return;
    function update() {
      var cfg = catalogConfig();
      if (!cfg.ajaxUrl || !cfg.nonce) return;
      var data = formDataObject(form);
      var body = new FormData();
      if (!validDateRange(data, errorNode)) return;
      body.append('action', 'gwr_filter_catalog'); body.append('nonce', cfg.nonce);
      Object.keys(data).forEach(function (key) { body.append(key, data[key]); });
      catalog.classList.add('is-loading');
      return window.fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (response) { return response.json(); }).then(function (json) {
        if (!json || !json.success) throw new Error('AJAX error');
        results.innerHTML = json.data.html;
        if (json.data.error && errorNode) { errorNode.textContent = json.data.error; errorNode.hidden = false; } else if (errorNode) { errorNode.hidden = true; }
        if (count) count.textContent = json.data.count === 1 ? (cfg.i18n.countOne || '1 veicolo disponibile') : (cfg.i18n.countMany || '%d veicoli disponibili').replace('%d', json.data.count);
      }).catch(function (error) { console.error('Gest Web Rent filter error:', error); if (errorNode) { errorNode.textContent = 'Errore durante il filtro catalogo.'; errorNode.hidden = false; } }).finally(function () { catalog.classList.remove('is-loading'); });
    }
    var debouncedUpdate = debounce(update, 320);
    form.addEventListener('input', debouncedUpdate);
    form.addEventListener('change', debouncedUpdate);
    form.addEventListener('submit', function (event) { event.preventDefault(); update(); });
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-gwr-open-modal]');
    if (trigger) {
      event.preventDefault();
      try { openModalFromTrigger(trigger); } catch (error) {
        console.error('Gest Web Rent modal error:', error);
        var catalog = trigger.closest('[data-gwr-catalog]');
        var errorNode = catalog ? qs(catalog, '[data-gwr-error]') : null;
        if (errorNode) { errorNode.textContent = (catalogConfig().i18n.modalError || 'Impossibile aprire i dettagli del veicolo.'); errorNode.hidden = false; }
      }
      return;
    }
    var closeTrigger = event.target.closest('[data-gwr-close-modal]');
    if (closeTrigger) { closeModal(closeTrigger.closest('[data-gwr-modal]')); return; }
    var modal = event.target.closest('[data-gwr-modal]');
    if (!modal) return;
    var currentImage = qs(modal, '[data-gwr-modal-image]');
    var thumbs = qsa(modal, '[data-gwr-gallery-index]');
    var active = thumbs.findIndex(function (button) { return button.classList.contains('is-active'); });
    function setImage(index) { if (!thumbs.length || !currentImage) return; index = (index + thumbs.length) % thumbs.length; var img = qs(thumbs[index], 'img'); if (img) currentImage.src = img.src; thumbs.forEach(function (button, i) { button.classList.toggle('is-active', i === index); }); }
    if (event.target.closest('[data-gwr-gallery-prev]')) setImage(active - 1);
    if (event.target.closest('[data-gwr-gallery-next]')) setImage(active + 1);
    var thumb = event.target.closest('[data-gwr-gallery-index]');
    if (thumb) setImage(Number(thumb.getAttribute('data-gwr-gallery-index')));
  }, true);

  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') qsa(document, '[data-gwr-modal].is-open').forEach(closeModal); });

  function boot() { qsa(document, '[data-gwr-catalog]').forEach(initCatalog); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
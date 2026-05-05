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

  function hasValue(value) {
    return value !== undefined && value !== null && value !== '';
  }

  function valueWithUnit(value, unit) {
    if (!hasValue(value) || value === 0 || value === '0') {
      return '';
    }
    return String(value) + ' ' + unit;
  }

  function compactBooleanLabel(value) {
    if (!hasValue(value)) {
      return '';
    }
    return value === true || value === 1 || value === '1' || value === 'true' ? 'Si' : 'No';
  }

  function iconSvg(name) {
    var icons = {
      car: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 13l1.6-4.4A3 3 0 0 1 9.4 6h5.2a3 3 0 0 1 2.8 2.6L19 13"/><path d="M4 13h16v5H4z"/><path d="M7 18v2M17 18v2M7 15h.01M17 15h.01"/></svg>',
      fuel: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 21V4a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v17"/><path d="M5 21h11"/><path d="M15 7h2l2 2v8a2 2 0 0 0 4 0v-5l-2-2"/><path d="M8 7h4"/></svg>',
      transmission: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4v16M18 4v16M6 12h12"/><circle cx="6" cy="4" r="2"/><circle cx="6" cy="20" r="2"/><circle cx="18" cy="4" r="2"/><circle cx="18" cy="20" r="2"/></svg>',
      users: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 19c0-2.2-1.8-4-4-4s-4 1.8-4 4"/><circle cx="12" cy="9" r="3"/><path d="M20 18c0-1.7-1.1-3.1-2.7-3.7"/><path d="M4 18c0-1.7 1.1-3.1 2.7-3.7"/></svg>',
      door: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 21V4.8A1.8 1.8 0 0 1 8.8 3H17v18"/><path d="M7 21h12"/><path d="M14 12h.01"/></svg>',
      pin: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>',
      gauge: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 15a8 8 0 1 1 16 0"/><path d="M12 15l4-5"/><path d="M7 15h10"/></svg>',
      license: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 10h4M8 14h8"/></svg>',
      calendar: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>',
      shield: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-2.8 8.4-7 10-4.2-1.6-7-5.5-7-10V6l7-3z"/><path d="M9 12l2 2 4-5"/></svg>',
      check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4 4L19 6"/></svg>',
      euro: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 6.5A6 6 0 1 0 17 17.5"/><path d="M4 10h10M4 14h9"/></svg>',
      mail: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="6" width="16" height="12" rx="2"/><path d="M4 8l8 6 8-6"/></svg>',
      whatsapp: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.5 19l1-3A7 7 0 1 1 9 18.3L5.5 19z"/><path d="M9.5 9.5c.5 2 2 3.5 4 4l1.1-1.1 1.9.5c.3.1.5.4.5.7v1.2c0 .4-.3.7-.7.7A8.8 8.8 0 0 1 8.5 7.7c0-.4.3-.7.7-.7h1.2c.3 0 .6.2.7.5l.5 1.9-1.1 1.1z"/></svg>',
      home: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11l8-7 8 7"/><path d="M6 10v10h12V10"/><path d="M10 20v-6h4v6"/></svg>',
      palette: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4a8 8 0 0 0 0 16h1.5a1.8 1.8 0 0 0 1.2-3.1 1.8 1.8 0 0 1 1.2-3.1H17a3 3 0 0 0 0-6h-1"/><circle cx="8.5" cy="10" r=".5"/><circle cx="11" cy="8" r=".5"/><circle cx="7.5" cy="13" r=".5"/></svg>'
    };
    return icons[name] || icons.check;
  }

  function modalStat(label, value, icon) {
    if (!hasValue(value)) {
      return '';
    }
    return '<article class="gwr-stat-item"><span class="gwr-stat-icon">' + iconSvg(icon || 'check') + '</span><span class="gwr-stat-text"><span class="gwr-stat-label">' + escapeHtml(label) + '</span><strong class="gwr-stat-value">' + escapeHtml(value) + '</strong></span></article>';
  }

  function modalPrice(label, value) {
    if (!hasValue(value)) {
      return '';
    }
    return '<article class="gwr-price-card"><span class="gwr-price-card__icon">' + iconSvg('euro') + '</span><span><span class="gwr-price-card__label">' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></span></article>';
  }

  function modalChip(label, icon) {
    if (!hasValue(label)) {
      return '';
    }
    return '<span class="gwr-feature-chip"><span>' + iconSvg(icon || 'check') + '</span>' + escapeHtml(label) + '</span>';
  }

  function modalSection(title, content, extraClass) {
    if (!content || !String(content).trim()) {
      return '';
    }
    return '<section class="gwr-modal-section ' + escapeHtml(extraClass || '') + '"><h3>' + escapeHtml(title) + '</h3>' + content + '</section>';
  }

  function selectedDatesLabel(dates) {
    if (dates.start_date && dates.end_date) {
      return 'Date selezionate: dal ' + dates.start_date + ' al ' + dates.end_date + '.';
    }
    if (dates.start_date) {
      return 'Data selezionata: ' + dates.start_date + '.';
    }
    return 'Date non selezionate: richiesta generica.';
  }

  function renderModalContent(vehicle, dates) {
    var cfg = catalogConfig();
    dates = dates || {};
    var images = vehicle.images && vehicle.images.length ? vehicle.images : [];
    var links = contactLinks(vehicle, dates);
    var mainImage = images[0] ? images[0].url : '';
    var metaLine = [vehicle.brand, vehicle.model, vehicle.version].filter(Boolean).join(' ');
    var thumbs = images.map(function (image, index) {
      return '<button type="button" data-gwr-gallery-index="' + index + '"' + (index === 0 ? ' class="is-active"' : '') + ' aria-label="Mostra foto ' + (index + 1) + '"><img src="' + escapeHtml(image.url) + '" alt="" /></button>';
    }).join('');
    var gallery = [
      '<div class="gwr-modal-gallery">',
      '<div class="gwr-modal-gallery-frame">',
      mainImage ? '<img class="gwr-modal-main-image" src="' + escapeHtml(mainImage) + '" alt="' + escapeHtml(vehicle.title) + '" data-gwr-modal-image />' : '<div class="gwr-modal-image-empty">Foto veicolo</div>',
      images.length > 1 ? '<button type="button" class="gwr-gallery-arrow is-prev" data-gwr-gallery-prev aria-label="Foto precedente">&#8249;</button><button type="button" class="gwr-gallery-arrow is-next" data-gwr-gallery-next aria-label="Foto successiva">&#8250;</button>' : '',
      '</div>',
      images.length > 1 ? '<div class="gwr-gallery-thumbs">' + thumbs + '</div>' : '',
      '</div>'
    ].join('');
    var prices = [
      modalPrice('Giorno', vehicle.daily_price),
      modalPrice('Weekend', vehicle.weekend_price),
      modalPrice('Settimana', vehicle.weekly_price),
      modalPrice('Mese', vehicle.monthly_price),
      modalPrice('Cauzione', vehicle.deposit),
      modalPrice('Km extra', vehicle.extra_km_price)
    ].join('');
    var overview = [
      modalStat('Categoria', vehicle.category, 'car'),
      modalStat('Alimentazione', vehicle.fuel, 'fuel'),
      modalStat('Cambio', vehicle.transmission, 'transmission'),
      modalStat('Posti', valueWithUnit(vehicle.seats, 'posti'), 'users'),
      modalStat('Porte', valueWithUnit(vehicle.doors, 'porte'), 'door'),
      modalStat('Colore', vehicle.color, 'palette'),
      modalStat('Sede', vehicle.location, 'pin'),
      modalStat('Km/giorno', valueWithUnit(vehicle.included_km_daily, 'km'), 'gauge'),
      modalStat('Km/settimana', valueWithUnit(vehicle.included_km_weekly, 'km'), 'gauge'),
      modalStat('Km/mese', valueWithUnit(vehicle.included_km_monthly, 'km'), 'gauge'),
      modalStat('Patente', vehicle.required_license, 'license'),
      modalStat('Eta minima', valueWithUnit(vehicle.min_driver_age, 'anni'), 'license'),
      modalStat('Anni patente', valueWithUnit(vehicle.min_license_years, 'anni'), 'license'),
      modalStat('Durata minima', valueWithUnit(vehicle.min_rental_days, 'giorni'), 'calendar'),
      modalStat('Durata massima', valueWithUnit(vehicle.max_rental_days, 'giorni'), 'calendar'),
      modalStat('Franchigia', vehicle.deductible, 'shield'),
      modalStat('Assicurazione', compactBooleanLabel(vehicle.insurance_included), 'shield'),
      modalStat('Secondo conducente', compactBooleanLabel(vehicle.second_driver_included), 'users'),
      modalStat('Consegna domicilio', compactBooleanLabel(vehicle.home_delivery), 'home')
    ].join('');
    var features = vehicle.features && vehicle.features.length
      ? '<div class="gwr-feature-chips">' + vehicle.features.map(function (item) { return modalChip(item, 'check'); }).join('') + '</div>'
      : '<p class="gwr-muted">Dotazioni da definire.</p>';
    var privacyNote = cfg.contact.privacyNote || 'La disponibilita e indicativa: contatta il concessionario per conferma e dettagli.';
    var contactActions = [
      links.whatsapp ? '<a class="gwr-contact-action is-whatsapp" target="_blank" rel="noopener noreferrer" href="' + escapeHtml(links.whatsapp) + '">' + iconSvg('whatsapp') + '<span>WhatsApp</span></a>' : '',
      links.email ? '<a class="gwr-contact-action is-email" href="' + escapeHtml(links.email) + '">' + iconSvg('mail') + '<span>Email</span></a>' : ''
    ].join('');
    var priceSection = prices ? modalSection('Tariffe noleggio', '<div class="gwr-price-grid">' + prices + '</div>') : '';
    var overviewSection = overview ? modalSection('Panoramica noleggio', '<div class="gwr-stat-grid">' + overview + '</div>') : '';

    return [
      '<div class="gwr-modal-layout">',
      '<div class="gwr-modal-media-panel">' + gallery + '</div>',
      '<div class="gwr-modal-info-panel">',
      '<header class="gwr-modal-header">',
      '<span class="gwr-modal-badge">' + escapeHtml(vehicle.category || 'Noleggio') + '</span>',
      '<h2 id="gwr-modal-title">' + escapeHtml(vehicle.title) + '</h2>',
      metaLine ? '<p class="gwr-modal-subtitle">' + escapeHtml(metaLine) + '</p>' : '',
      vehicle.location ? '<p class="gwr-modal-location">' + iconSvg('pin') + '<span>' + escapeHtml(vehicle.location) + '</span></p>' : '',
      '</header>',
      priceSection,
      overviewSection,
      modalSection('Dotazioni', features),
      vehicle.description ? modalSection('Descrizione', '<div class="gwr-modal-text">' + vehicle.description + '</div>') : '',
      vehicle.rental_notes ? modalSection('Note noleggio', '<div class="gwr-modal-text">' + vehicle.rental_notes + '</div>') : '',
      '<section class="gwr-contact-panel">',
      '<div><span class="gwr-contact-panel__eyebrow">Contatto concessionario</span><h3>Richiedi disponibilita</h3><p class="gwr-contact-panel__dates">' + escapeHtml(selectedDatesLabel(dates)) + '</p></div>',
      '<p class="gwr-contact-panel__note">' + escapeHtml(privacyNote) + '</p>',
      contactActions ? '<div class="gwr-modal-contact__actions">' + contactActions + '</div>' : '<p class="gwr-muted">Configura WhatsApp o email nella dashboard per attivare i contatti.</p>',
      '</section>',
      '</div></div>'
    ].join('');
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
    catalog.addEventListener('click', function (event) {
      if (!event.target.closest('[data-gwr-reset-filters]')) return;
      form.reset();
      update();
    });
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
    function setImage(index) {
      if (!thumbs.length || !currentImage) return;
      index = ((index % thumbs.length) + thumbs.length) % thumbs.length;
      var img = qs(thumbs[index], 'img');
      if (img) currentImage.src = img.src;
      thumbs.forEach(function (button, i) { button.classList.toggle('is-active', i === index); });
    }
    if (event.target.closest('[data-gwr-gallery-prev]')) { setImage(active - 1); return; }
    if (event.target.closest('[data-gwr-gallery-next]')) { setImage(active + 1); return; }
    var thumb = event.target.closest('[data-gwr-gallery-index]');
    if (thumb) setImage(Number(thumb.getAttribute('data-gwr-gallery-index')));
  }, true);

  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') qsa(document, '[data-gwr-modal].is-open').forEach(closeModal); });

  function boot() { qsa(document, '[data-gwr-catalog]').forEach(initCatalog); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();

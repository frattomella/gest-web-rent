(function () {
  function qs(root, selector) { return root ? root.querySelector(selector) : null; }
  function qsa(root, selector) { return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : []; }
  function escapeHtml(value) { return String(value || '').replace(/[&<>"']/g, function (char) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]; }); }
  function formDataObject(form) { var data = {}; if (!form) return data; new FormData(form).forEach(function (value, key) { data[key] = value; }); return data; }
  function catalogConfig() { return window.gwrCatalog || { ajaxUrl: '', nonce: '', i18n: {}, contact: {} }; }
  var lastModalTrigger = null;

  function validDateParts(year, month, day) {
    var date = new Date(Date.UTC(year, month - 1, day));
    return year > 0 && date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day;
  }

  function parseIsoDate(value) {
    var match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;
    var parts = { year: Number(match[1]), month: Number(match[2]), day: Number(match[3]) };
    return validDateParts(parts.year, parts.month, parts.day) ? parts : null;
  }

  function parseItalianDate(value) {
    var match = String(value || '').trim().match(/^(\d{2})-(\d{2})-(\d{4})$/);
    if (!match) return null;
    var parts = { year: Number(match[3]), month: Number(match[2]), day: Number(match[1]) };
    return validDateParts(parts.year, parts.month, parts.day) ? parts : null;
  }

  function padDatePart(value) {
    return String(value).padStart(2, '0');
  }

  function italianToIsoDate(value) {
    if (!value) return '';
    var isoParts = parseIsoDate(value);
    if (isoParts) return String(isoParts.year) + '-' + padDatePart(isoParts.month) + '-' + padDatePart(isoParts.day);
    var parts = parseItalianDate(value);
    return parts ? String(parts.year) + '-' + padDatePart(parts.month) + '-' + padDatePart(parts.day) : '';
  }

  function isoToItalianDate(value) {
    if (!value) return '';
    var italianParts = parseItalianDate(value);
    if (italianParts) return padDatePart(italianParts.day) + '-' + padDatePart(italianParts.month) + '-' + String(italianParts.year);
    var parts = parseIsoDate(value);
    return parts ? padDatePart(parts.day) + '-' + padDatePart(parts.month) + '-' + String(parts.year) : '';
  }

  function isoDateTimestamp(value) {
    var parts = parseIsoDate(value);
    return parts ? Date.UTC(parts.year, parts.month - 1, parts.day) : NaN;
  }

  function showCatalogError(errorNode, message) {
    if (!errorNode) return;
    errorNode.textContent = message;
    errorNode.hidden = false;
  }

  function clearCatalogError(errorNode) {
    if (!errorNode) return;
    errorNode.textContent = '';
    errorNode.hidden = true;
  }

  function syncDateFields(form, errorNode) {
    var cfg = catalogConfig();
    var valid = true;
    qsa(form, '[data-gwr-date-display]').some(function (input) {
      var target = document.getElementById(input.getAttribute('data-gwr-date-target'));
      var value = input.value.trim();
      if (!target) return false;
      if (!value) {
        target.value = '';
        return false;
      }
      var isoValue = italianToIsoDate(value);
      if (!isoValue) {
        showCatalogError(errorNode, cfg.i18n.invalidDate || 'Inserisci una data valida nel formato GG-MM-AAAA.');
        input.setAttribute('aria-invalid', 'true');
        input.focus();
        valid = false;
        return true;
      }
      input.value = isoToItalianDate(isoValue);
      input.removeAttribute('aria-invalid');
      target.value = isoValue;
      return false;
    });
    return valid;
  }

  function validDateRange(data, errorNode) {
    var cfg = catalogConfig();
    var startTime = data.start_date ? isoDateTimestamp(data.start_date) : NaN;
    var endTime = data.end_date ? isoDateTimestamp(data.end_date) : NaN;
    if (data.start_date && data.end_date && endTime < startTime) {
      var message = cfg.i18n.dateError || 'La data fine %2$s non puo precedere la data inizio %1$s.';
      message = message.replace('%1$s', isoToItalianDate(data.start_date)).replace('%2$s', isoToItalianDate(data.end_date));
      showCatalogError(errorNode, message);
      return false;
    }
    clearCatalogError(errorNode);
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
    var contact = cfg.contact || {};
    var start = isoToItalianDate(dates.start_date || dates.end_date) || cfg.i18n.datesGeneric || 'Date da definire';
    var end = isoToItalianDate(dates.end_date || dates.start_date) || cfg.i18n.datesGeneric || 'Date da definire';
    var currentUrl = window.location.href;
    var map = {'{vehicle_title}': vehicle.title || '', '{start_date}': start, '{end_date}': end, '{site_url}': currentUrl, '{vehicle_url}': currentUrl, '{dealer_name}': contact.dealerName || contact.dealer_name || '', '{brand}': vehicle.brand || '', '{model}': vehicle.model || '', '{version}': vehicle.version || '', '{daily_price}': vehicle.daily_price || '', '{weekly_price}': vehicle.weekly_price || '', '{monthly_price}': vehicle.monthly_price || ''};
    return String(template || '').replace(/\{[a-z_]+\}/g, function (token) { return Object.prototype.hasOwnProperty.call(map, token) ? map[token] : token; });
  }

  function contactLinks(vehicle, dates) {
    var cfg = catalogConfig();
    var contact = cfg.contact || {};
    var whatsappNumber = String(contact.whatsappNumber || contact.whatsapp_number || '').replace(/\D+/g, '');
    var contactEmail = String(contact.contactEmail || contact.contact_email || '').trim();
    var emailIsValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactEmail);
    var hasDates = !!(dates.start_date || dates.end_date);
    var whatsappTemplate = hasDates ? (contact.whatsappTemplate || 'Ciao, vorrei informazioni sul noleggio del veicolo {vehicle_title}. Date di interesse: {start_date} - {end_date}. Link: {site_url}') : 'Ciao, vorrei informazioni sul noleggio del veicolo {vehicle_title}. Link: {site_url}';
    var emailBody = hasDates ? (contact.emailBody || 'Buongiorno,\nvorrei ricevere informazioni sul noleggio del veicolo {vehicle_title}.\n\nDate di interesse:\nDal {start_date} al {end_date}\n\nGrazie.') : 'Buongiorno,\nvorrei ricevere informazioni sul noleggio del veicolo {vehicle_title}.\n\nDate di interesse: Date da definire.\n\nGrazie.';
    var subject = replaceTokens(contact.emailSubject || 'Richiesta noleggio {vehicle_title}', vehicle, dates);
    var body = replaceTokens(emailBody, vehicle, dates);
    var links = {};
    if (whatsappNumber) {
      links.whatsapp = 'https://wa.me/' + whatsappNumber + '?' + new URLSearchParams({ text: replaceTokens(whatsappTemplate, vehicle, dates) }).toString();
    }
    if (emailIsValid) {
      links.email = 'mailto:' + contactEmail + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
    }
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
      mail: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="6" width="16" height="12" rx="2"/><path d="M4 8l8 6 8-6"/></svg>',
      whatsapp: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.5 19l1-3A7 7 0 1 1 9 18.3L5.5 19z"/><path d="M9.5 9.5c.5 2 2 3.5 4 4l1.1-1.1 1.9.5c.3.1.5.4.5.7v1.2c0 .4-.3.7-.7.7A8.8 8.8 0 0 1 8.5 7.7c0-.4.3-.7.7-.7h1.2c.3 0 .6.2.7.5l.5 1.9-1.1 1.1z"/></svg>'
    };
    return icons[name] || '';
  }

  function modalStat(label, value) {
    if (!hasValue(value)) {
      return '';
    }
    return '<article class="gwr-stat-item"><span class="gwr-stat-label">' + escapeHtml(label) + '</span><strong class="gwr-stat-value">' + escapeHtml(value) + '</strong></article>';
  }

  function modalPrice(label, value) {
    if (!hasValue(value)) {
      return '';
    }
    return '<article class="gwr-price-card"><span class="gwr-price-card__label">' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></article>';
  }

  function modalChip(label) {
    if (!hasValue(label)) {
      return '';
    }
    return '<span class="gwr-feature-chip">' + escapeHtml(label) + '</span>';
  }

  function modalSection(title, content, extraClass) {
    if (!content || !String(content).trim()) {
      return '';
    }
    return '<section class="gwr-modal-section ' + escapeHtml(extraClass || '') + '"><h3>' + escapeHtml(title) + '</h3>' + content + '</section>';
  }

  function selectedDatesLabel(dates) {
    if (dates.start_date && dates.end_date) {
      return 'Dal ' + isoToItalianDate(dates.start_date) + ' al ' + isoToItalianDate(dates.end_date);
    }
    if (dates.start_date) {
      return 'Data richiesta: ' + isoToItalianDate(dates.start_date);
    }
    if (dates.end_date) {
      return 'Data richiesta: ' + isoToItalianDate(dates.end_date);
    }
    return 'Date da definire';
  }

  function uniqueImages(images) {
    var seen = {};
    return (images || []).filter(function (image) {
      var url = image && image.url ? String(image.url) : '';
      if (!url || seen[url]) return false;
      seen[url] = true;
      return true;
    });
  }

  function renderModalContent(vehicle, dates) {
    var cfg = catalogConfig();
    dates = dates || {};
    var images = uniqueImages(vehicle.images);
    var links = contactLinks(vehicle, dates);
    var mainImage = images[0] ? images[0].url : '';
    var metaLine = [vehicle.brand, vehicle.model, vehicle.version].filter(Boolean).join(' ');
    var thumbs = images.map(function (image, index) {
      var imageAlt = (vehicle.title || 'Veicolo') + ' - foto ' + (index + 1);
      return '<button type="button" data-gwr-gallery-index="' + index + '" data-gwr-image-alt="' + escapeHtml(imageAlt) + '"' + (index === 0 ? ' class="is-active" aria-current="true"' : '') + ' aria-label="Mostra foto ' + (index + 1) + '"><img src="' + escapeHtml(image.url) + '" alt="" /></button>';
    }).join('');
    var gallery = [
      '<div class="gwr-modal-gallery">',
      '<div class="gwr-modal-gallery-frame">',
      mainImage ? '<img class="gwr-modal-main-image" src="' + escapeHtml(mainImage) + '" alt="' + escapeHtml((vehicle.title || 'Veicolo') + ' - foto 1') + '" data-gwr-modal-image />' : '<div class="gwr-modal-image-empty">Foto veicolo non disponibile</div>',
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
      modalStat('Alimentazione', vehicle.fuel),
      modalStat('Cambio', vehicle.transmission),
      modalStat('Posti', valueWithUnit(vehicle.seats, 'posti')),
      modalStat('Porte', valueWithUnit(vehicle.doors, 'porte')),
      modalStat('Anno', vehicle.year),
      modalStat('Colore', vehicle.color),
      modalStat('Sede', vehicle.location)
    ].join('');
    var rentalTerms = [
      modalStat('Km inclusi al giorno', valueWithUnit(vehicle.included_km_daily, 'km')),
      modalStat('Km inclusi a settimana', valueWithUnit(vehicle.included_km_weekly, 'km')),
      modalStat('Km inclusi al mese', valueWithUnit(vehicle.included_km_monthly, 'km')),
      modalStat('Patente richiesta', vehicle.required_license),
      modalStat('Eta minima', valueWithUnit(vehicle.min_driver_age, 'anni')),
      modalStat('Anni minimi di patente', valueWithUnit(vehicle.min_license_years, 'anni')),
      modalStat('Durata minima', valueWithUnit(vehicle.min_rental_days, 'giorni')),
      modalStat('Durata massima', valueWithUnit(vehicle.max_rental_days, 'giorni')),
      modalStat('Franchigia', vehicle.deductible),
      modalStat('Assicurazione inclusa', compactBooleanLabel(vehicle.insurance_included)),
      modalStat('Secondo conducente', compactBooleanLabel(vehicle.second_driver_included)),
      modalStat('Consegna a domicilio', compactBooleanLabel(vehicle.home_delivery))
    ].join('');
    var features = vehicle.features && vehicle.features.length
      ? '<div class="gwr-feature-chips">' + vehicle.features.filter(function (item, index, list) { return list.indexOf(item) === index; }).map(modalChip).join('') + '</div>'
      : '<p class="gwr-muted">Dotazioni da definire.</p>';
    var privacyNote = (cfg.contact && cfg.contact.privacyNote) || 'La disponibilita e indicativa: contatta il concessionario per conferma e dettagli.';
    var contactActions = [
      links.whatsapp ? '<a class="gwr-contact-action is-whatsapp" target="_blank" rel="noopener noreferrer" href="' + escapeHtml(links.whatsapp) + '">' + iconSvg('whatsapp') + '<span>WhatsApp</span></a>' : '',
      links.email ? '<a class="gwr-contact-action is-email" href="' + escapeHtml(links.email) + '">' + iconSvg('mail') + '<span>Email</span></a>' : ''
    ].join('');
    var priceSection = prices ? modalSection('Tariffe noleggio', '<div class="gwr-price-grid">' + prices + '</div>') : '';
    var overviewSection = overview ? modalSection('Caratteristiche principali', '<div class="gwr-stat-grid">' + overview + '</div>') : '';
    var rentalSection = rentalTerms ? modalSection('Condizioni di noleggio', '<div class="gwr-stat-grid">' + rentalTerms + '</div>') : '';
    var selectedPeriod = '<section class="gwr-selected-period"><span>Periodo richiesto</span><strong>' + escapeHtml(selectedDatesLabel(dates)) + '</strong>' + (dates.start_date || dates.end_date ? '<p>Disponibile per il periodo selezionato, salvo conferma del concessionario.</p>' : '<p>Seleziona le date nel catalogo per verificare la disponibilita.</p>') + '</section>';

    return [
      '<div class="gwr-modal-layout">',
      '<div class="gwr-modal-media-panel">' + gallery + '</div>',
      '<div class="gwr-modal-info-panel">',
      '<header class="gwr-modal-header">',
      '<span class="gwr-modal-badge">' + escapeHtml(vehicle.category || 'Noleggio') + '</span>',
      '<h2 id="gwr-modal-title">' + escapeHtml(vehicle.title) + '</h2>',
      metaLine ? '<p class="gwr-modal-subtitle">' + escapeHtml(metaLine) + '</p>' : '',
      vehicle.location ? '<p class="gwr-modal-location">' + escapeHtml(vehicle.location) + '</p>' : '',
      '</header>',
      priceSection,
      selectedPeriod,
      overviewSection,
      rentalSection,
      modalSection('Dotazioni', features),
      vehicle.description ? modalSection('Descrizione', '<div class="gwr-modal-text">' + vehicle.description + '</div>') : '',
      vehicle.rental_notes ? modalSection('Note noleggio', '<div class="gwr-modal-text">' + vehicle.rental_notes + '</div>') : '',
      '<section class="gwr-contact-panel">',
      '<div><span class="gwr-contact-panel__eyebrow">Contatto concessionario</span><h3>Richiedi disponibilita</h3><p class="gwr-contact-panel__dates">' + escapeHtml(selectedDatesLabel(dates)) + '</p></div>',
      '<p class="gwr-contact-panel__note">' + escapeHtml(privacyNote) + '</p>',
      contactActions ? '<div class="gwr-modal-contact__actions">' + contactActions + '</div>' : '<p class="gwr-contact-panel__missing">' + escapeHtml(cfg.i18n.contactsMissing || 'Contatti non configurati.') + '</p>',
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
    lastModalTrigger = trigger;
    content.innerHTML = renderModalContent(vehicle, dates);
    content.scrollTop = 0;
    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open');
    document.documentElement.classList.add('gwr-modal-open');
    document.body.classList.add('gwr-modal-open');
    var closeButton = qs(modal, '.gwr-modal__close');
    if (closeButton) closeButton.focus();

    var galleryFrame = qs(modal, '.gwr-modal-gallery-frame');
    if (galleryFrame) {
      var touchStartX = null;
      galleryFrame.addEventListener('touchstart', function (event) {
        touchStartX = event.changedTouches.length ? event.changedTouches[0].clientX : null;
      }, { passive: true });
      galleryFrame.addEventListener('touchend', function (event) {
        if (touchStartX === null || !event.changedTouches.length) return;
        var distance = event.changedTouches[0].clientX - touchStartX;
        if (Math.abs(distance) > 48) setGalleryImage(modal, galleryActiveIndex(modal) + (distance < 0 ? 1 : -1));
        touchStartX = null;
      }, { passive: true });
    }
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
    if (lastModalTrigger && document.contains(lastModalTrigger)) lastModalTrigger.focus();
    lastModalTrigger = null;
  }

  function galleryActiveIndex(modal) {
    return qsa(modal, '[data-gwr-gallery-index]').findIndex(function (button) { return button.classList.contains('is-active'); });
  }

  function setGalleryImage(modal, index) {
    var currentImage = qs(modal, '[data-gwr-modal-image]');
    var thumbs = qsa(modal, '[data-gwr-gallery-index]');
    if (!thumbs.length || !currentImage) return;
    index = ((index % thumbs.length) + thumbs.length) % thumbs.length;
    var img = qs(thumbs[index], 'img');
    if (img) currentImage.src = img.src;
    currentImage.alt = thumbs[index].getAttribute('data-gwr-image-alt') || currentImage.alt;
    thumbs.forEach(function (button, buttonIndex) {
      var isActive = buttonIndex === index;
      button.classList.toggle('is-active', isActive);
      if (isActive) button.setAttribute('aria-current', 'true'); else button.removeAttribute('aria-current');
    });
  }

  function initCatalog(catalog) {
    var form = qs(catalog, '[data-gwr-filter-form]');
    var results = qs(catalog, '[data-gwr-results]');
    var count = qs(catalog, '[data-gwr-count]');
    var summary = qs(catalog, '[data-gwr-query-summary]');
    var errorNode = qs(catalog, '[data-gwr-error]');
    var submitButton = qs(form, '[data-gwr-search-submit]');
    var submitLabel = qs(form, '[data-gwr-search-label]');
    var filterToggle = qs(form, '[data-gwr-filter-toggle]');
    var advancedFilters = qs(form, '[data-gwr-filter-advanced]');
    var requestInProgress = false;
    if (!form || !results) return;

    function setAdvancedFilters(open) {
      if (!filterToggle || !advancedFilters) return;
      filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      advancedFilters.hidden = !open;
      advancedFilters.classList.toggle('is-open', open);
    }

    function setLoading(loading) {
      var cfg = catalogConfig();
      requestInProgress = loading;
      catalog.classList.toggle('is-loading', loading);
      form.setAttribute('aria-busy', loading ? 'true' : 'false');
      if (submitButton) submitButton.disabled = loading;
      if (submitLabel) submitLabel.textContent = loading ? (cfg.i18n.searchingLabel || 'Ricerca in corso...') : (cfg.i18n.searchLabel || 'Cerca veicoli');
    }

    function update() {
      var cfg = catalogConfig();
      if (!cfg.ajaxUrl || !cfg.nonce || requestInProgress) return;
      if (!syncDateFields(form, errorNode)) return;
      var data = formDataObject(form);
      var body = new FormData();
      if (!validDateRange(data, errorNode)) return;
      body.append('action', 'gwr_filter_catalog'); body.append('nonce', cfg.nonce);
      Object.keys(data).forEach(function (key) { body.append(key, data[key]); });
      setLoading(true);
      return window.fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (response) { return response.json(); }).then(function (json) {
        if (!json || !json.success) throw new Error('AJAX error');
        results.innerHTML = json.data.html;
        if (json.data.error && errorNode) { errorNode.textContent = json.data.error; errorNode.hidden = false; } else if (errorNode) { errorNode.hidden = true; }
        if (count) count.textContent = json.data.count === 1 ? (cfg.i18n.countOne || '1 veicolo disponibile') : (cfg.i18n.countMany || '%d veicoli disponibili').replace('%d', json.data.count);
        if (summary) summary.textContent = json.data.summary || selectedDatesLabel(data);
      }).catch(function (error) { console.error('Gest Web Rent filter error:', error); showCatalogError(errorNode, 'Errore durante la ricerca dei veicoli. Riprova.'); }).finally(function () { setLoading(false); });
    }

    setAdvancedFilters(window.matchMedia && window.matchMedia('(min-width: 769px)').matches);
    if (filterToggle) filterToggle.addEventListener('click', function () { setAdvancedFilters(filterToggle.getAttribute('aria-expanded') !== 'true'); });
    qsa(form, '[data-gwr-date-display]').forEach(function (input) {
      input.addEventListener('blur', function () {
        var isoValue = italianToIsoDate(input.value);
        var target = document.getElementById(input.getAttribute('data-gwr-date-target'));
        if (isoValue && target) {
          input.value = isoToItalianDate(isoValue);
          input.removeAttribute('aria-invalid');
          target.value = isoValue;
        }
      });
    });
    form.addEventListener('submit', function (event) { event.preventDefault(); update(); });
    catalog.addEventListener('click', function (event) {
      if (!event.target.closest('[data-gwr-reset-filters]')) return;
      qsa(form, 'input').forEach(function (input) { input.value = ''; input.removeAttribute('aria-invalid'); });
      qsa(form, 'select').forEach(function (select) { select.selectedIndex = 0; });
      clearCatalogError(errorNode);
      if (summary) summary.textContent = 'Seleziona le date e premi Cerca veicoli.';
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
    var active = galleryActiveIndex(modal);
    if (event.target.closest('[data-gwr-gallery-prev]')) { setGalleryImage(modal, active - 1); return; }
    if (event.target.closest('[data-gwr-gallery-next]')) { setGalleryImage(modal, active + 1); return; }
    var thumb = event.target.closest('[data-gwr-gallery-index]');
    if (thumb) setGalleryImage(modal, Number(thumb.getAttribute('data-gwr-gallery-index')));
  }, true);

  document.addEventListener('keydown', function (event) {
    var openModal = qs(document, '[data-gwr-modal].is-open');
    if (!openModal) return;
    if (event.key === 'Escape') {
      closeModal(openModal);
      return;
    }
    if (event.key !== 'Tab') return;
    var focusable = qsa(openModal, 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  function boot() { qsa(document, '[data-gwr-catalog]').forEach(initCatalog); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();

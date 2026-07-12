(function () {
  function qs(root, selector) { return root ? root.querySelector(selector) : null; }
  function qsa(root, selector) { return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : []; }
  function escapeHtml(value) { return String(value || '').replace(/[&<>"']/g, function (char) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]; }); }
  function formDataObject(form) { var data = {}; if (!form) return data; new FormData(form).forEach(function (value, key) { data[key] = value; }); return data; }
  function catalogConfig() { return window.gwrCatalog || { ajaxUrl: '', nonce: '', i18n: {}, contact: {} }; }
  var lastModalTrigger = null;
  var galleryTouchStartX = null;
  var galleryTouchModal = null;
  var lastContactActivation = 0;

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

  function maskItalianDateInput(value) {
    var digits = String(value || '').replace(/\D/g, '').slice(0, 8);
    if (digits.length <= 2) return digits;
    if (digits.length <= 4) return digits.slice(0, 2) + '-' + digits.slice(2);
    return digits.slice(0, 2) + '-' + digits.slice(2, 4) + '-' + digits.slice(4);
  }

  function fieldErrorNode(input) {
    return input ? document.getElementById(input.getAttribute('data-gwr-error-target')) : null;
  }

  function showFieldError(input, message) {
    var node = fieldErrorNode(input);
    if (!input) return;
    input.setAttribute('aria-invalid', 'true');
    if (node) {
      node.textContent = message;
      node.hidden = false;
    }
  }

  function clearFieldError(input) {
    var node = fieldErrorNode(input);
    if (!input) return;
    input.removeAttribute('aria-invalid');
    if (node) {
      node.textContent = '';
      node.hidden = true;
    }
  }

  function clearFormErrors(form, formError) {
    qsa(form, '[aria-invalid="true"]').forEach(clearFieldError);
    if (formError) {
      formError.textContent = '';
      formError.hidden = true;
    }
  }

  function syncDateField(input) {
    var target = input ? document.getElementById(input.getAttribute('data-gwr-date-target')) : null;
    var value = input ? input.value.trim() : '';
    if (!target) return '';
    if (!value) {
      target.value = '';
      return '';
    }
    var isoValue = italianToIsoDate(value);
    if (!isoValue) {
      target.value = '';
      return '';
    }
    input.value = isoToItalianDate(isoValue);
    target.value = isoValue;
    return isoValue;
  }

  function isValidTime(value) {
    return /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(String(value || ''));
  }

  function dateTimeTimestamp(dateValue, timeValue) {
    var parts = parseIsoDate(dateValue);
    if (!parts || !isValidTime(timeValue)) return NaN;
    var timeParts = timeValue.split(':');
    return Date.UTC(parts.year, parts.month - 1, parts.day, Number(timeParts[0]), Number(timeParts[1]));
  }

  function todayTimestamp() {
    var today = new Date();
    return Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
  }

  function validateSearchForm(form, formError) {
    var cfg = catalogConfig();
    var i18n = cfg.i18n || {};
    var firstInvalid = null;
    var pickupDate = qs(form, '[data-gwr-date-role="pickup"]');
    var returnDate = qs(form, '[data-gwr-date-role="return"]');
    var pickupTime = qs(form, '[data-gwr-time-role="pickup"]');
    var returnTime = qs(form, '[data-gwr-time-role="return"]');
    var pickupLocation = qs(form, '[data-gwr-pickup-location]');
    var returnLocation = qs(form, '[data-gwr-return-location-field]');
    var differentReturn = qs(form, '[data-gwr-different-return]');
    clearFormErrors(form, formError);

    function invalidate(input, message) {
      showFieldError(input, message);
      if (!firstInvalid) firstInvalid = input;
    }

    if (pickupLocation && pickupLocation.getAttribute('data-gwr-required') === '1' && !pickupLocation.value) {
      invalidate(pickupLocation, i18n.pickupLocation || 'Seleziona la localita di ritiro.');
    }
    if (differentReturn && differentReturn.checked && returnLocation && !returnLocation.disabled && !returnLocation.value) {
      invalidate(returnLocation, i18n.returnLocation || 'Seleziona la localita di riconsegna.');
    }

    var pickupIso = syncDateField(pickupDate);
    var returnIso = syncDateField(returnDate);
    if (!pickupDate.value.trim()) invalidate(pickupDate, i18n.pickupDateRequired || 'Inserisci la data di ritiro.');
    else if (!pickupIso) invalidate(pickupDate, i18n.invalidDate || 'Inserisci una data valida nel formato GG-MM-AAAA.');
    if (!returnDate.value.trim()) invalidate(returnDate, i18n.returnDateRequired || 'Inserisci la data di riconsegna.');
    else if (!returnIso) invalidate(returnDate, i18n.invalidDate || 'Inserisci una data valida nel formato GG-MM-AAAA.');

    if (pickupIso && isoDateTimestamp(pickupIso) < todayTimestamp()) {
      invalidate(pickupDate, i18n.pastDate || 'La data di ritiro non puo essere precedente a oggi.');
    }
    if (!pickupTime || !isValidTime(pickupTime.value)) invalidate(pickupTime, i18n.timeRequired || 'Inserisci un orario valido nel formato HH:MM.');
    if (!returnTime || !isValidTime(returnTime.value)) invalidate(returnTime, i18n.timeRequired || 'Inserisci un orario valido nel formato HH:MM.');

    var pickupTimestamp = dateTimeTimestamp(pickupIso, pickupTime ? pickupTime.value : '');
    var returnTimestamp = dateTimeTimestamp(returnIso, returnTime ? returnTime.value : '');
    if (!Number.isNaN(pickupTimestamp) && !Number.isNaN(returnTimestamp) && returnTimestamp <= pickupTimestamp) {
      invalidate(returnDate, i18n.returnAfterPickup || 'La riconsegna deve essere successiva al ritiro.');
    }

    if (firstInvalid) {
      if (formError) {
        formError.textContent = i18n.formError || 'Controlla i campi evidenziati.';
        formError.hidden = false;
      }
      firstInvalid.focus();
      return false;
    }
    return true;
  }

  function decodeVehiclePayload(trigger) {
    var payloadNode = trigger.hasAttribute('data-gwr-vehicle-b64') ? trigger : trigger.closest('[data-gwr-vehicle-b64]');
    var b64 = payloadNode ? payloadNode.getAttribute('data-gwr-vehicle-b64') : '';
    if (b64) { return JSON.parse(window.atob(b64)); }
    var raw = payloadNode ? payloadNode.getAttribute('data-gwr-vehicle') : '';
    if (raw) { return JSON.parse(raw); }
    throw new Error('Missing vehicle payload');
  }

  function replaceTokens(template, vehicle, dates) {
    var cfg = catalogConfig();
    var contact = cfg.contact || {};
    var start = isoToItalianDate(dates.start_date || dates.end_date) || cfg.i18n.datesGeneric || 'Date da definire';
    var end = isoToItalianDate(dates.end_date || dates.start_date) || cfg.i18n.datesGeneric || 'Date da definire';
    var pickupLocation = dates.pickup_location || vehicle.location || 'Da definire';
    var returnLocation = dates.different_return === '1' && dates.return_location ? dates.return_location : pickupLocation;
    var currentUrl = window.location.href.split('#')[0];
    var map = {
      '{vehicle_title}': vehicle.title || [vehicle.brand, vehicle.model].filter(Boolean).join(' '),
      '{start_date}': start,
      '{end_date}': end,
      '{pickup_time}': dates.pickup_time || 'Da definire',
      '{return_time}': dates.return_time || 'Da definire',
      '{pickup_location}': pickupLocation,
      '{return_location}': returnLocation,
      '{site_url}': currentUrl,
      '{vehicle_url}': currentUrl,
      '{dealer_name}': contact.dealerName || contact.dealer_name || '',
      '{brand}': vehicle.brand || '',
      '{model}': vehicle.model || '',
      '{version}': vehicle.version || '',
      '{daily_price}': vehicle.daily_price || 'Su richiesta',
      '{weekly_price}': vehicle.weekly_price || '',
      '{monthly_price}': vehicle.monthly_price || ''
    };
    return String(template || '').replace(/\{[a-z_]+\}/g, function (token) { return Object.prototype.hasOwnProperty.call(map, token) ? map[token] : token; });
  }

  function ensureContactContext(template) {
    template = String(template || '');
    var additions = [];
    if (template.indexOf('{vehicle_title}') === -1) additions.push('Veicolo: {vehicle_title}');
    if (template.indexOf('{pickup_location}') === -1 || template.indexOf('{return_location}') === -1) additions.push('Localita: {pickup_location} - {return_location}');
    if (template.indexOf('{start_date}') === -1 || template.indexOf('{end_date}') === -1 || template.indexOf('{pickup_time}') === -1 || template.indexOf('{return_time}') === -1) additions.push('Periodo: {start_date} alle {pickup_time} - {end_date} alle {return_time}');
    if (template.indexOf('{daily_price}') === -1) additions.push('Tariffa giornaliera: {daily_price}');
    if (template.indexOf('{site_url}') === -1 && template.indexOf('{vehicle_url}') === -1) additions.push('Link: {site_url}');
    return additions.length ? template.replace(/\s+$/, '') + '\n\n' + additions.join('\n') : template;
  }

  function contactLinks(vehicle, dates) {
    var cfg = catalogConfig();
    var contact = cfg.contact || {};
    var whatsappNumber = String(contact.whatsappNumber || contact.whatsapp_number || '').replace(/\D+/g, '');
    var contactEmail = String(contact.contactEmail || contact.contact_email || '').trim();
    var emailIsValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactEmail);
    var whatsappTemplate = ensureContactContext(contact.whatsappTemplate || 'Ciao, vorrei informazioni sul noleggio del veicolo {vehicle_title}.');
    var emailBody = ensureContactContext(contact.emailBody || 'Buongiorno,\nvorrei ricevere informazioni sul noleggio del veicolo {vehicle_title}.\n\nGrazie.');
    var subject = replaceTokens(contact.emailSubject || 'Richiesta noleggio {vehicle_title}', vehicle, dates);
    var body = replaceTokens(emailBody, vehicle, dates);
    var links = {};
    if (whatsappNumber) {
      links.whatsapp = 'https://wa.me/' + whatsappNumber + '?text=' + encodeURIComponent(replaceTokens(whatsappTemplate, vehicle, dates));
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

  function iconSvg(name) {
    var icons = {
      mail: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="6" width="16" height="12" rx="2"/><path d="M4 8l8 6 8-6"/></svg>',
      whatsapp: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.5 19l1-3A7 7 0 1 1 9 18.3L5.5 19z"/><path d="M9.5 9.5c.5 2 2 3.5 4 4l1.1-1.1 1.9.5c.3.1.5.4.5.7v1.2c0 .4-.3.7-.7.7A8.8 8.8 0 0 1 8.5 7.7c0-.4.3-.7.7-.7h1.2c.3 0 .6.2.7.5l.5 1.9-1.1 1.1z"/></svg>'
    };
    return icons[name] || '';
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

  function safeHttpUrl(value) {
    try {
      var url = new URL(String(value || ''), window.location.href);
      return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
    } catch (error) {
      return '';
    }
  }

  function plainText(value) {
    var source = String(value || '').replace(/<br\s*\/?>/gi, '\n').replace(/<\/p>/gi, '\n');
    if (typeof window.DOMParser === 'function') {
      return new window.DOMParser().parseFromString(source, 'text/html').body.textContent.trim();
    }
    return source.replace(/<[^>]*>/g, ' ').replace(/\s+\n/g, '\n').trim();
  }

  function detailItem(label, value, extraClass) {
    if (!hasValue(value) || value === 0 || value === '0') return '';
    return '<div class="gwr-config-detail ' + escapeHtml(extraClass || '') + '"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>';
  }

  function includedItem(title, description) {
    if (!hasValue(title)) return '';
    return '<li><span aria-hidden="true">&#10003;</span><div><strong>' + escapeHtml(title) + '</strong>' + (description ? '<p>' + escapeHtml(description) + '</p>' : '') + '</div></li>';
  }

  function configuratorSection(id, title, content, open) {
    if (!content || !String(content).trim()) return '';
    var contentId = 'gwr-config-content-' + id;
    return '<section id="gwr-config-' + escapeHtml(id) + '" class="gwr-config-section" data-gwr-config-section><h3><button type="button" data-gwr-config-toggle aria-expanded="' + (open === false ? 'false' : 'true') + '" aria-controls="' + contentId + '"><span>' + escapeHtml(title) + '</span><i aria-hidden="true"></i></button></h3><div id="' + contentId + '" class="gwr-config-section__content" data-gwr-config-content' + (open === false ? ' hidden' : '') + '>' + content + '</div></section>';
  }

  function bookingPoint(label, location, dateValue, timeValue) {
    var dateLabel = isoToItalianDate(dateValue) || 'Data da definire';
    var timeLabel = timeValue || 'Ora da definire';
    return '<div class="gwr-booking-point"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(location || 'Localita da definire') + '</strong><small>' + escapeHtml(dateLabel + ' \u00b7 ' + timeLabel) + '</small></div>';
  }

  function renderModalContent(vehicle, dates) {
    var cfg = catalogConfig();
    dates = dates || {};
    var images = uniqueImages(vehicle.images).map(function (image) {
      return { url: safeHttpUrl(image.url), id: image.id || 0 };
    }).filter(function (image) { return image.url; });
    var links = contactLinks(vehicle, dates);
    var mainImage = images[0] ? images[0].url : '';
    var title = [vehicle.brand, vehicle.model].filter(Boolean).join(' ') || vehicle.title || 'Veicolo';
    var metaLine = [vehicle.category, vehicle.version, vehicle.transmission].filter(Boolean).join(' \u00b7 ');
    var pickupLocation = dates.pickup_location || vehicle.location || '';
    var returnLocation = dates.different_return === '1' && dates.return_location ? dates.return_location : pickupLocation;
    var duration = rentalDurationLabel(dates).replace(/^Durata:\s*/, '') || 'Da definire';
    var hasPeriod = !!(dates.start_date && dates.end_date);
    var availability = hasPeriod ? 'Disponibile salvo conferma' : 'Disponibilita da verificare';
    var featureList = (vehicle.features || []).filter(function (item, index, list) {
      return hasValue(item) && list.indexOf(item) === index;
    });
    var thumbs = images.map(function (image, index) {
      var imageAlt = title + ' - foto ' + (index + 1);
      return '<button type="button" data-gwr-gallery-index="' + index + '" data-gwr-image-url="' + escapeHtml(image.url) + '" data-gwr-image-alt="' + escapeHtml(imageAlt) + '"' + (index === 0 ? ' class="is-active" aria-current="true"' : '') + ' aria-label="Mostra foto ' + (index + 1) + ' di ' + images.length + '"><img src="' + escapeHtml(image.url) + '" alt="" loading="lazy" width="104" height="76" /></button>';
    }).join('');
    var gallery = [
      '<section class="gwr-config-gallery" aria-label="Galleria di ' + escapeHtml(title) + '">',
      '<div class="gwr-modal-gallery-frame"' + (images.length > 1 ? ' tabindex="0" aria-label="Galleria immagini, usa le frecce per navigare"' : '') + '>',
      mainImage ? '<img class="gwr-modal-main-image" src="' + escapeHtml(mainImage) + '" alt="' + escapeHtml(title + ' - foto 1') + '" data-gwr-modal-image width="960" height="720" />' : '<div class="gwr-modal-image-empty"><span aria-hidden="true"></span><strong>Immagine non disponibile</strong></div>',
      images.length > 1 ? '<button type="button" class="gwr-gallery-arrow is-prev" data-gwr-gallery-prev aria-label="Foto precedente">&#8249;</button><button type="button" class="gwr-gallery-arrow is-next" data-gwr-gallery-next aria-label="Foto successiva">&#8250;</button>' : '',
      images.length > 1 ? '<span class="gwr-gallery-counter" data-gwr-gallery-counter aria-live="polite">1 / ' + images.length + '</span>' : '',
      '</div>',
      images.length > 1 ? '<div class="gwr-gallery-thumbs">' + thumbs + '</div>' : '',
      '</section>'
    ].join('');

    var overview = [
      detailItem('Posti', valueWithUnit(vehicle.seats, 'posti')),
      detailItem('Porte', valueWithUnit(vehicle.doors, 'porte')),
      detailItem('Cambio', vehicle.transmission),
      detailItem('Alimentazione', vehicle.fuel),
      featureList.some(function (item) { return String(item).toLowerCase().indexOf('climat') !== -1; }) ? detailItem('Comfort', 'Climatizzatore') : '',
      detailItem('Anno', vehicle.year)
    ].join('');

    var inclusions = [
      vehicle.included_km_daily ? includedItem(valueWithUnit(vehicle.included_km_daily, 'km al giorno inclusi'), 'Il limite indicato deriva dalla scheda del veicolo.') : '',
      vehicle.insurance_included ? includedItem('Assicurazione inclusa', 'Copertura indicata come inclusa nella scheda di noleggio.') : '',
      vehicle.second_driver_included ? includedItem('Secondo conducente incluso', '') : ''
    ].join('');

    var coverage = [
      vehicle.insurance_included ? detailItem('Copertura', 'Assicurazione inclusa', 'is-positive') : '',
      detailItem('Franchigia indicata', vehicle.deductible)
    ].join('');

    var driverRequirements = [
      detailItem('Eta minima', valueWithUnit(vehicle.min_driver_age, 'anni')),
      detailItem('Patente posseduta da', valueWithUnit(vehicle.min_license_years, 'anni')),
      detailItem('Patente richiesta', vehicle.required_license),
      detailItem('Durata minima', valueWithUnit(vehicle.min_rental_days, 'giorni')),
      detailItem('Durata massima', valueWithUnit(vehicle.max_rental_days, 'giorni'))
    ].join('');

    var mileage = [
      detailItem('Limite giornaliero', valueWithUnit(vehicle.included_km_daily, 'km')),
      detailItem('Limite settimanale', valueWithUnit(vehicle.included_km_weekly, 'km')),
      detailItem('Limite mensile', valueWithUnit(vehicle.included_km_monthly, 'km')),
      detailItem('Costo km eccedente', vehicle.extra_km_price)
    ].join('');

    var pickupDetails = [
      detailItem('Sede veicolo', vehicle.location),
      vehicle.home_delivery ? detailItem('Consegna', 'Consegna a domicilio disponibile') : ''
    ].join('');

    var features = featureList.map(function (item, index) {
      return '<span class="gwr-config-feature' + (index >= 8 ? ' is-extra' : '') + '"' + (index >= 8 ? ' hidden' : '') + '>' + escapeHtml(item) + '</span>';
    }).join('');
    if (featureList.length > 8) features += '<button type="button" class="gwr-config-features__toggle" data-gwr-toggle-features aria-expanded="false">Mostra tutte (' + featureList.length + ')</button>';

    var sections = [];
    function addSection(id, sectionTitle, content, open) {
      if (!content || !String(content).trim()) return;
      sections.push({ id: id, title: sectionTitle, content: content, open: open });
    }
    addSection('overview', 'Panoramica veicolo', overview ? '<div class="gwr-config-grid">' + overview + '</div>' : '', true);
    addSection('included', 'Incluso nel prezzo', inclusions ? '<ul class="gwr-config-included">' + inclusions + '</ul>' : '', true);
    addSection('coverage', 'Coperture assicurative', coverage ? '<div class="gwr-config-grid">' + coverage + '</div>' + (vehicle.deductible ? '<p class="gwr-config-note">La franchigia e l\'importo indicato nelle condizioni del noleggio che puo restare a carico del cliente nei casi previsti.</p>' : '') : '', true);
    addSection('deposit', 'Deposito cauzionale', vehicle.deposit ? '<div class="gwr-config-highlight"><span>Importo richiesto</span><strong>' + escapeHtml(vehicle.deposit) + '</strong><p>Modalita di blocco e rilascio da confermare con il concessionario.</p></div>' : '', true);
    addSection('driver', 'Requisiti del conducente', driverRequirements ? '<div class="gwr-config-grid">' + driverRequirements + '</div>' : '', true);
    addSection('mileage', 'Chilometraggio', mileage ? '<div class="gwr-config-grid">' + mileage + '</div>' : '', true);
    addSection('pickup', 'Ritiro e riconsegna', pickupDetails ? '<div class="gwr-config-grid">' + pickupDetails + '</div>' : '', true);
    addSection('features', 'Dotazioni', features ? '<div class="gwr-config-features">' + features + '</div>' : '', true);
    addSection('description', 'Descrizione del veicolo', vehicle.description ? '<div class="gwr-config-copy">' + escapeHtml(plainText(vehicle.description)) + '</div>' : '', true);
    addSection('terms', 'Condizioni di noleggio', vehicle.rental_notes ? '<div class="gwr-config-copy">' + escapeHtml(plainText(vehicle.rental_notes)) + '</div>' : '', true);

    var sectionNav = sections.length > 3 ? '<nav class="gwr-config-nav" aria-label="Sezioni del dettaglio">' + sections.map(function (section) {
      return '<button type="button" data-gwr-scroll-section="gwr-config-' + escapeHtml(section.id) + '">' + escapeHtml(section.title) + '</button>';
    }).join('') + '</nav>' : '';
    var sectionMarkup = sections.map(function (section) {
      return configuratorSection(section.id, section.title, section.content, section.open);
    }).join('');

    var privacyNote = (cfg.contact && cfg.contact.privacyNote) || 'La disponibilita e indicativa: contatta il concessionario per conferma e dettagli.';
    var primaryHref = links.whatsapp || links.email || '';
    var primaryChannel = links.whatsapp ? 'WhatsApp' : (links.email ? 'Email' : '');
    var primaryAttrs = links.whatsapp ? ' target="_blank" rel="noopener noreferrer"' : '';
    var primaryAction = primaryHref ? '<a class="gwr-config-primary-action" href="' + escapeHtml(primaryHref) + '"' + primaryAttrs + ' data-gwr-primary-contact><span>Richiedi disponibilita</span><small>via ' + escapeHtml(primaryChannel) + '</small></a>' : '<button type="button" class="gwr-config-primary-action" disabled>Contatti non configurati</button>';
    var secondaryContact = links.whatsapp && links.email ? '<a class="gwr-contact-action is-email" href="' + escapeHtml(links.email) + '">' + iconSvg('mail') + '<span>Email</span></a>' : '';
    var contactPanel = '<section class="gwr-config-contact"><h3>Contatta il concessionario</h3><p>' + escapeHtml(privacyNote) + '</p>' + primaryAction + secondaryContact + '</section>';

    var priceRows = (vehicle.daily_price ? '<div><span>Tariffa giornaliera</span><strong>' + escapeHtml(vehicle.daily_price) + '</strong></div>' : '') + '<div><span>Totale</span><strong>Su richiesta</strong></div>';
    var priceBreakdown = '<div class="gwr-config-price-detail"><button type="button" data-gwr-price-toggle aria-expanded="false" aria-controls="gwr-price-detail-content"><span>Dettaglio prezzo</span><i aria-hidden="true"></i></button><div id="gwr-price-detail-content" data-gwr-price-content hidden>' + priceRows + '<p>Il totale commerciale viene confermato dal concessionario e non e calcolato automaticamente dal plugin.</p></div></div>';
    var summary = [
      '<aside class="gwr-vehicle-configurator__summary" aria-label="Riepilogo richiesta">',
      '<span class="gwr-config-summary__eyebrow">Riepilogo noleggio</span>',
      bookingPoint('Ritiro', pickupLocation, dates.start_date, dates.pickup_time),
      bookingPoint('Riconsegna', returnLocation, dates.end_date, dates.return_time),
      '<div class="gwr-config-duration"><span>Durata</span><strong>' + escapeHtml(duration) + '</strong></div>',
      '<div class="gwr-config-price" aria-live="polite"><span>Totale</span><strong>Su richiesta</strong>' + (vehicle.daily_price ? '<small>' + escapeHtml(vehicle.daily_price) + ' / giorno</small>' : '<small>Tariffa da confermare</small>') + '</div>',
      priceBreakdown,
      '<span class="gwr-config-availability">' + escapeHtml(availability) + '</span>',
      contactPanel,
      '</aside>'
    ].join('');

    var mobileAction = '<div class="gwr-config-mobile-bar"><div><span>Totale</span><strong>Su richiesta</strong><small>' + escapeHtml(duration) + '</small></div>' + (primaryHref ? '<a href="' + escapeHtml(primaryHref) + '"' + primaryAttrs + ' data-gwr-primary-contact>Richiedi disponibilita</a>' : '<button type="button" disabled>Contatti non configurati</button>') + '</div>';

    return [
      '<div class="gwr-vehicle-configurator" data-gwr-configurator data-gwr-vehicle-id="' + escapeHtml(vehicle.id || '') + '">',
      '<header class="gwr-vehicle-configurator__header">',
      '<div><span class="gwr-config-category">' + escapeHtml(vehicle.category || 'Noleggio veicolo') + '</span><h2 id="gwr-modal-title">' + escapeHtml(title) + '</h2>',
      '<p id="gwr-modal-description">' + escapeHtml(metaLine || 'Dettagli e condizioni del veicolo selezionato') + '</p></div>',
      '<span class="gwr-config-header-status">' + escapeHtml(availability) + '</span>',
      '</header>',
      '<div class="gwr-vehicle-configurator__body">',
      '<main class="gwr-vehicle-configurator__main">',
      gallery,
      sectionNav,
      sectionMarkup,
      '</main>',
      summary,
      '</div>',
      mobileAction,
      '</div>'
    ].join('');
  }

  function openModalFromTrigger(trigger) {
    var catalog = trigger.closest('[data-gwr-catalog]') || document;
    var modal = qs(catalog, '[data-gwr-modal]') || qs(document, '[data-gwr-modal]');
    var content = modal ? qs(modal, '[data-gwr-modal-content]') : null;
    if (!modal || !content) throw new Error('Missing modal shell');
    if (modal.classList.contains('is-open')) return;
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
    if (window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
      qsa(modal, '[data-gwr-config-section]').forEach(function (section, index) {
        if (index < 2) return;
        var toggle = qs(section, '[data-gwr-config-toggle]');
        var sectionContent = qs(section, '[data-gwr-config-content]');
        if (toggle && sectionContent) {
          toggle.setAttribute('aria-expanded', 'false');
          sectionContent.hidden = true;
        }
      });
    }
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
    galleryTouchStartX = null;
    galleryTouchModal = null;
    lastContactActivation = 0;
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
    var imageUrl = thumbs[index].getAttribute('data-gwr-image-url');
    if (imageUrl) currentImage.src = imageUrl; else if (img) currentImage.src = img.src;
    currentImage.alt = thumbs[index].getAttribute('data-gwr-image-alt') || currentImage.alt;
    thumbs.forEach(function (button, buttonIndex) {
      var isActive = buttonIndex === index;
      button.classList.toggle('is-active', isActive);
      if (isActive) button.setAttribute('aria-current', 'true'); else button.removeAttribute('aria-current');
    });
    var counter = qs(modal, '[data-gwr-gallery-counter]');
    if (counter) counter.textContent = (index + 1) + ' / ' + thumbs.length;
    if (thumbs[index] && typeof thumbs[index].scrollIntoView === 'function') thumbs[index].scrollIntoView({ block: 'nearest', inline: 'nearest' });
  }

  function selectedOptionLabel(select) {
    if (!select || select.selectedIndex < 0) return '';
    return select.options[select.selectedIndex].textContent.trim();
  }

  function activeSecondaryFilterCount(form) {
    return qsa(form, '[data-gwr-secondary-filter]').filter(function (field) {
      if (field.type === 'checkbox' || field.type === 'radio') return field.checked;
      if (field.type === 'number') return String(field.value || '').trim() !== '' && Number(field.value) > 0;
      return String(field.value || '').trim() !== '';
    }).length;
  }

  function rentalDurationLabel(data) {
    var start = dateTimeTimestamp(data.start_date, data.pickup_time);
    var end = dateTimeTimestamp(data.end_date, data.return_time);
    if (Number.isNaN(start) || Number.isNaN(end) || end <= start) return '';
    var minutes = Math.floor((end - start) / 60000);
    var days = Math.floor(minutes / 1440);
    var hours = Math.floor((minutes % 1440) / 60);
    var remainingMinutes = minutes % 60;
    var parts = [];
    if (days) parts.push(days + (days === 1 ? ' giorno' : ' giorni'));
    if (hours) parts.push(hours + (hours === 1 ? ' ora' : ' ore'));
    if (remainingMinutes) parts.push(remainingMinutes + ' min');
    return 'Durata: ' + (parts.join(' e ') || '0 min');
  }

  function requestFormSubmit(form) {
    if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  }

  function compareVehicleCards(mode, left, right) {
    if (mode === 'recommended') return Number(left.getAttribute('data-gwr-original-index')) - Number(right.getAttribute('data-gwr-original-index'));
    if (mode === 'brand' || mode === 'category') {
      var attribute = mode === 'brand' ? 'data-gwr-brand' : 'data-gwr-category-name';
      return String(left.getAttribute(attribute) || '').localeCompare(String(right.getAttribute(attribute) || ''), 'it', { sensitivity: 'base' });
    }
    var leftPrice = Number(left.getAttribute('data-gwr-price'));
    var rightPrice = Number(right.getAttribute('data-gwr-price'));
    var leftMissing = !Number.isFinite(leftPrice) || leftPrice <= 0;
    var rightMissing = !Number.isFinite(rightPrice) || rightPrice <= 0;
    if (leftMissing !== rightMissing) return leftMissing ? 1 : -1;
    if (leftMissing && rightMissing) return 0;
    return mode === 'price_desc' ? rightPrice - leftPrice : leftPrice - rightPrice;
  }

  function initCatalog(catalog) {
    var form = qs(catalog, '[data-gwr-filter-form]');
    var secondaryForm = qs(catalog, '[data-gwr-secondary-form]');
    var results = qs(catalog, '[data-gwr-results]');
    var resultsShell = results ? results.parentNode : null;
    var counts = qsa(catalog, '[data-gwr-count]');
    var searchSummary = qs(catalog, '[data-gwr-search-summary]');
    var summaryLocation = qs(catalog, '[data-gwr-summary-location]');
    var summaryPeriod = qs(catalog, '[data-gwr-summary-period]');
    var summaryDuration = qs(catalog, '[data-gwr-summary-duration]');
    var toolbarPeriod = qs(catalog, '[data-gwr-toolbar-period]');
    var resultsToolbar = qs(catalog, '[data-gwr-results-toolbar]');
    var categorySlot = qs(catalog, '[data-gwr-category-slot]');
    var activeFilters = qs(catalog, '[data-gwr-active-filters]');
    var sortSelect = qs(catalog, '[data-gwr-sort]');
    var errorNode = qs(catalog, '[data-gwr-error]');
    var formError = qs(form, '[data-gwr-form-error]');
    var submitButton = qs(form, '[data-gwr-search-submit]');
    var submitLabel = qs(form, '[data-gwr-search-label]');
    var filterToggles = qsa(catalog, '[data-gwr-filter-toggle]');
    var advancedFilters = qs(catalog, '[data-gwr-filter-advanced]');
    var filterCounts = qsa(catalog, '[data-gwr-filter-count]');
    var differentReturn = qs(form, '[data-gwr-different-return]');
    var returnLocationPanel = qs(form, '[data-gwr-return-location]');
    var requestInProgress = false;
    var lastRequestData = null;
    if (!form || !secondaryForm || !results) return;

    function setAdvancedFilters(open) {
      if (!advancedFilters) return;
      filterToggles.forEach(function (toggle) { toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); });
      advancedFilters.hidden = !open;
      advancedFilters.classList.toggle('is-open', open);
    }

    function isDesktopFilters() {
      return window.matchMedia && window.matchMedia('(min-width: 1024px)').matches;
    }

    function updateReturnLocation() {
      if (!differentReturn || !returnLocationPanel) return;
      var open = differentReturn.checked;
      differentReturn.setAttribute('aria-expanded', open ? 'true' : 'false');
      returnLocationPanel.hidden = !open;
      var returnField = qs(returnLocationPanel, '[data-gwr-return-location-field]');
      if (returnField) returnField.setAttribute('aria-required', open && !returnField.disabled ? 'true' : 'false');
      if (!open) clearFieldError(qs(returnLocationPanel, '[data-gwr-return-location-field]'));
    }

    function updateFilterCount() {
      var total = activeSecondaryFilterCount(secondaryForm);
      filterCounts.forEach(function (node) {
        node.textContent = node.closest('.gwr-toolbar-filter') ? '(' + total + ')' : '(' + total + ')';
        node.hidden = total === 0;
      });
    }

    function updateResultCount(total) {
      var cfg = catalogConfig();
      var label = total === 1 ? (cfg.i18n.countOne || '1 veicolo disponibile') : (cfg.i18n.countMany || '%d veicoli disponibili').replace('%d', total);
      counts.forEach(function (node) { node.textContent = label; });
    }

    function updateSearchSummary(data) {
      var pickupSelect = qs(form, '[data-gwr-pickup-location]');
      var returnSelect = qs(form, '[data-gwr-return-location-field]');
      var pickupLabel = selectedOptionLabel(pickupSelect) || 'Localita da definire';
      var returnLabel = data.different_return === '1' ? (selectedOptionLabel(returnSelect) || pickupLabel) : pickupLabel;
      if (summaryLocation) summaryLocation.textContent = pickupLabel === returnLabel ? pickupLabel : pickupLabel + ' \u2192 ' + returnLabel;
      if (summaryPeriod) summaryPeriod.textContent = isoToItalianDate(data.start_date) + ', ' + data.pickup_time + ' \u2192 ' + isoToItalianDate(data.end_date) + ', ' + data.return_time;
      if (summaryDuration) summaryDuration.textContent = rentalDurationLabel(data);
      if (toolbarPeriod) toolbarPeriod.textContent = isoToItalianDate(data.start_date) + ' \u2192 ' + isoToItalianDate(data.end_date);
      if (searchSummary) searchSummary.hidden = false;
    }

    function focusSearch() {
      var target = qs(form, '[data-gwr-pickup-location]');
      if (!target || target.disabled) target = qs(form, '[data-gwr-date-role="pickup"]');
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      window.setTimeout(function () { if (target) target.focus(); }, 250);
    }

    function setLoading(loading) {
      var cfg = catalogConfig();
      requestInProgress = loading;
      catalog.classList.toggle('is-loading', loading);
      form.setAttribute('aria-busy', loading ? 'true' : 'false');
      secondaryForm.setAttribute('aria-busy', loading ? 'true' : 'false');
      if (resultsShell) resultsShell.setAttribute('aria-busy', loading ? 'true' : 'false');
      qsa(form, 'input, select, button').concat(qsa(secondaryForm, 'input, select, button')).forEach(function (control) {
        if (loading) {
          control.setAttribute('data-gwr-disabled-before-loading', control.disabled ? '1' : '0');
          control.disabled = true;
        } else if (control.hasAttribute('data-gwr-disabled-before-loading')) {
          control.disabled = control.getAttribute('data-gwr-disabled-before-loading') === '1';
          control.removeAttribute('data-gwr-disabled-before-loading');
        }
      });
      qsa(catalog, '[data-gwr-category], [data-gwr-remove-filter], [data-gwr-clear-active-filters]').forEach(function (button) { button.disabled = loading; });
      if (submitLabel) submitLabel.textContent = loading ? (cfg.i18n.searchingLabel || 'Ricerca in corso...') : (cfg.i18n.searchLabel || 'Cerca veicoli');
    }

    function currentData() {
      return Object.assign({}, formDataObject(form), formDataObject(secondaryForm));
    }

    function clearSecondaryFilters() {
      qsa(secondaryForm, '[data-gwr-secondary-filter]').forEach(function (field) {
        if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
        else field.value = '';
      });
      updateFilterCount();
    }

    function secondaryField(name) {
      return qs(secondaryForm, '[name="' + name + '"]');
    }

    function filterLabel(field) {
      if (!field || !field.value) return '';
      if (field.name === 'max_price') {
        var price = Number(field.value);
        return price > 0 ? 'Massimo ' + new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(price) + ' / giorno' : '';
      }
      if (field.name === 'seats') return 'Almeno ' + field.value + ' posti';
      if (field.name === 'search') return '\u201c' + field.value.trim() + '\u201d';
      if (field.tagName === 'SELECT' && field.selectedIndex >= 0) return field.options[field.selectedIndex].textContent.trim();
      return field.value;
    }

    function renderActiveFilters() {
      if (!activeFilters) return;
      activeFilters.textContent = '';
      var fields = qsa(secondaryForm, '[data-gwr-secondary-filter]').filter(function (field) { return filterLabel(field) !== ''; });
      if (!fields.length) {
        activeFilters.hidden = true;
        return;
      }
      var heading = document.createElement('span');
      heading.className = 'gwr-active-filters__label';
      heading.textContent = 'Filtri attivi';
      activeFilters.appendChild(heading);
      fields.forEach(function (field) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'gwr-filter-chip';
        button.setAttribute('data-gwr-remove-filter', field.name);
        button.setAttribute('aria-label', 'Rimuovi filtro ' + filterLabel(field));
        button.textContent = filterLabel(field) + ' \u00d7';
        activeFilters.appendChild(button);
      });
      var clear = document.createElement('button');
      clear.type = 'button';
      clear.className = 'gwr-active-filters__clear';
      clear.setAttribute('data-gwr-clear-active-filters', '');
      clear.textContent = 'Rimuovi tutti';
      activeFilters.appendChild(clear);
      activeFilters.hidden = false;
    }

    function renderSkeletons() {
      var skeleton = '';
      for (var index = 0; index < 3; index += 1) {
        skeleton += '<article class="gwr-vehicle-card gwr-vehicle-card--skeleton" aria-hidden="true"><span></span><div><i></i><i></i><i></i><i></i></div><div><i></i><i></i></div></article>';
      }
      results.innerHTML = skeleton;
    }

    function renderTechnicalError() {
      var cfg = catalogConfig();
      results.innerHTML = '<div class="gwr-technical-state" role="alert"><span class="gwr-technical-state__icon" aria-hidden="true">!</span><h3>Non e stato possibile caricare i veicoli</h3><p>' + escapeHtml(cfg.i18n.technicalError || 'Riprova tra qualche istante.') + '</p><div class="gwr-technical-state__actions"><button type="button" class="gwr-button" data-gwr-retry-search>Riprova</button></div></div>';
    }

    function applySort() {
      if (!sortSelect) return;
      var mode = sortSelect.value;
      var cards = qsa(results, '[data-gwr-card]').slice();
      if (!cards.length) return;
      cards.sort(function (left, right) { return compareVehicleCards(mode, left, right); });
      var fragment = document.createDocumentFragment();
      cards.forEach(function (card) { fragment.appendChild(card); });
      results.appendChild(fragment);
    }

    function setView(view, persist) {
      view = view === 'grid' ? 'grid' : 'list';
      results.classList.toggle('is-grid-view', view === 'grid');
      results.classList.toggle('is-list-view', view === 'list');
      qsa(catalog, '[data-gwr-view]').forEach(function (button) { button.setAttribute('aria-pressed', button.getAttribute('data-gwr-view') === view ? 'true' : 'false'); });
      if (persist) {
        try { window.localStorage.setItem('gwr_catalog_view', view); } catch (error) { /* Storage can be disabled. */ }
      }
    }

    function updateCardDurations(data) {
      var label = rentalDurationLabel(data) || 'Seleziona il periodo';
      qsa(results, '[data-gwr-card-duration]').forEach(function (node) { node.textContent = label; });
    }

    function runRequest(data) {
      var cfg = catalogConfig();
      if (!cfg.ajaxUrl || !cfg.nonce || requestInProgress) return;
      var body = new FormData();
      clearCatalogError(errorNode);
      if (data.different_return !== '1') delete data.return_location;
      data.pickup_date = data.start_date;
      data.return_date = data.end_date;
      lastRequestData = Object.assign({}, data);
      body.append('action', 'gwr_filter_catalog');
      body.append('nonce', cfg.nonce);
      Object.keys(data).forEach(function (key) { body.append(key, data[key]); });
      setLoading(true);
      renderSkeletons();
      return window.fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      }).then(function (json) {
        if (!json || !json.success) throw new Error('AJAX error');
        results.innerHTML = json.data.html;
        if (categorySlot) categorySlot.innerHTML = json.data.categories || '';
        if (json.data.error) showCatalogError(errorNode, json.data.error); else clearCatalogError(errorNode);
        updateResultCount(Number(json.data.count) || 0);
        updateSearchSummary(data);
        updateCardDurations(data);
        applySort();
        renderActiveFilters();
        if (resultsToolbar) {
          try { resultsToolbar.focus({ preventScroll: true }); } catch (error) { resultsToolbar.focus(); }
        }
      }).catch(function (error) {
        console.error('Gest Web Rent filter error:', error);
        clearCatalogError(errorNode);
        renderTechnicalError();
      }).finally(function () { setLoading(false); });
    }

    function update() {
      if (requestInProgress || !validateSearchForm(form, formError)) return;
      return runRequest(currentData());
    }

    setAdvancedFilters(isDesktopFilters());
    updateReturnLocation();
    updateFilterCount();
    renderActiveFilters();
    updateCardDurations(currentData());
    var savedView = 'list';
    try { savedView = window.localStorage.getItem('gwr_catalog_view') || 'list'; } catch (error) { savedView = 'list'; }
    setView(savedView, false);
    filterToggles.forEach(function (toggle) { toggle.addEventListener('click', function () { setAdvancedFilters(toggle.getAttribute('aria-expanded') !== 'true'); }); });
    if (differentReturn) differentReturn.addEventListener('change', updateReturnLocation);
    qsa(form, '[data-gwr-date-display]').forEach(function (input) {
      input.addEventListener('input', function () {
        input.value = maskItalianDateInput(input.value);
        clearFieldError(input);
      });
      input.addEventListener('blur', function () {
        var cfg = catalogConfig();
        var role = input.getAttribute('data-gwr-date-role');
        if (!input.value.trim()) {
          showFieldError(input, role === 'pickup' ? (cfg.i18n.pickupDateRequired || 'Inserisci la data di ritiro.') : (cfg.i18n.returnDateRequired || 'Inserisci la data di riconsegna.'));
        } else if (!syncDateField(input)) {
          showFieldError(input, cfg.i18n.invalidDate || 'Inserisci una data valida nel formato GG-MM-AAAA.');
        } else {
          clearFieldError(input);
        }
      });
    });
    qsa(form, '[data-gwr-time-role], [data-gwr-location-field]').forEach(function (input) {
      input.addEventListener('change', function () { clearFieldError(input); });
    });
    qsa(secondaryForm, '[data-gwr-secondary-filter]').forEach(function (field) {
      field.addEventListener('input', updateFilterCount);
      field.addEventListener('change', updateFilterCount);
    });
    form.addEventListener('submit', function (event) { event.preventDefault(); update(); });
    secondaryForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!isDesktopFilters()) {
        setAdvancedFilters(false);
        if (filterToggles[0]) filterToggles[0].focus();
      }
      update();
    });
    if (sortSelect) sortSelect.addEventListener('change', applySort);
    catalog.addEventListener('click', function (event) {
      if (event.target.closest('[data-gwr-reset-filters]')) {
        clearSecondaryFilters();
        return;
      }
      if (event.target.closest('[data-gwr-reset-and-search]')) {
        clearSecondaryFilters();
        requestFormSubmit(form);
        return;
      }
      if (event.target.closest('[data-gwr-close-filters]')) {
        setAdvancedFilters(false);
        if (filterToggles[0]) filterToggles[0].focus();
        return;
      }
      var sectionToggle = event.target.closest('[data-gwr-sidebar-section-toggle]');
      if (sectionToggle) {
        var sectionContent = document.getElementById(sectionToggle.getAttribute('aria-controls'));
        var sectionOpen = sectionToggle.getAttribute('aria-expanded') !== 'true';
        sectionToggle.setAttribute('aria-expanded', sectionOpen ? 'true' : 'false');
        if (sectionContent) sectionContent.hidden = !sectionOpen;
        return;
      }
      var categoryButton = event.target.closest('[data-gwr-category]');
      if (categoryButton) {
        var categoryField = secondaryField('category');
        if (categoryField) categoryField.value = categoryButton.getAttribute('data-gwr-category') || '';
        updateFilterCount();
        requestFormSubmit(form);
        return;
      }
      var removeFilter = event.target.closest('[data-gwr-remove-filter]');
      if (removeFilter) {
        var field = secondaryField(removeFilter.getAttribute('data-gwr-remove-filter'));
        if (field) field.value = '';
        updateFilterCount();
        requestFormSubmit(form);
        return;
      }
      if (event.target.closest('[data-gwr-clear-active-filters]')) {
        clearSecondaryFilters();
        requestFormSubmit(form);
        return;
      }
      var viewButton = event.target.closest('[data-gwr-view]');
      if (viewButton) {
        setView(viewButton.getAttribute('data-gwr-view'), true);
        return;
      }
      if (event.target.closest('[data-gwr-edit-search]')) {
        focusSearch();
        return;
      }
      if (event.target.closest('[data-gwr-retry-search]') && lastRequestData) runRequest(Object.assign({}, lastRequestData));
    });
    catalog.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && advancedFilters && !advancedFilters.hidden && !isDesktopFilters()) {
        setAdvancedFilters(false);
        if (filterToggles[0]) filterToggles[0].focus();
      }
    });
    if (window.matchMedia) {
      var filtersMedia = window.matchMedia('(min-width: 1024px)');
      var handleFilterBreakpoint = function () { setAdvancedFilters(filtersMedia.matches); };
      if (typeof filtersMedia.addEventListener === 'function') filtersMedia.addEventListener('change', handleFilterBreakpoint);
      else if (typeof filtersMedia.addListener === 'function') filtersMedia.addListener(handleFilterBreakpoint);
    }
  }

  document.addEventListener('click', function (event) {
    var contactActivation = event.target.closest('[data-gwr-primary-contact]');
    if (contactActivation) {
      var activationTime = Date.now();
      if (activationTime - lastContactActivation < 800) event.preventDefault();
      else lastContactActivation = activationTime;
      return;
    }
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
    if (thumb) { setGalleryImage(modal, Number(thumb.getAttribute('data-gwr-gallery-index'))); return; }
    var configToggle = event.target.closest('[data-gwr-config-toggle]');
    if (configToggle) {
      var configContent = document.getElementById(configToggle.getAttribute('aria-controls'));
      var configOpen = configToggle.getAttribute('aria-expanded') !== 'true';
      configToggle.setAttribute('aria-expanded', configOpen ? 'true' : 'false');
      if (configContent) configContent.hidden = !configOpen;
      return;
    }
    var priceToggle = event.target.closest('[data-gwr-price-toggle]');
    if (priceToggle) {
      var priceContent = document.getElementById(priceToggle.getAttribute('aria-controls'));
      var priceOpen = priceToggle.getAttribute('aria-expanded') !== 'true';
      priceToggle.setAttribute('aria-expanded', priceOpen ? 'true' : 'false');
      if (priceContent) priceContent.hidden = !priceOpen;
      return;
    }
    var featureToggle = event.target.closest('[data-gwr-toggle-features]');
    if (featureToggle) {
      var featuresOpen = featureToggle.getAttribute('aria-expanded') !== 'true';
      featureToggle.setAttribute('aria-expanded', featuresOpen ? 'true' : 'false');
      qsa(modal, '.gwr-config-feature.is-extra').forEach(function (feature) { feature.hidden = !featuresOpen; });
      featureToggle.textContent = featuresOpen ? 'Mostra meno' : 'Mostra tutte';
      return;
    }
    var sectionLink = event.target.closest('[data-gwr-scroll-section]');
    if (sectionLink) {
      var section = document.getElementById(sectionLink.getAttribute('data-gwr-scroll-section'));
      if (section) {
        var sectionButton = qs(section, '[data-gwr-config-toggle]');
        var sectionContent = qs(section, '[data-gwr-config-content]');
        if (sectionButton && sectionContent && sectionButton.getAttribute('aria-expanded') !== 'true') {
          sectionButton.setAttribute('aria-expanded', 'true');
          sectionContent.hidden = false;
        }
        section.scrollIntoView({ behavior: window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        if (sectionButton) sectionButton.focus({ preventScroll: true });
      }
    }
  }, true);

  document.addEventListener('touchstart', function (event) {
    var frame = event.target.closest ? event.target.closest('.gwr-modal-gallery-frame') : null;
    if (!frame || !event.changedTouches.length) return;
    galleryTouchStartX = event.changedTouches[0].clientX;
    galleryTouchModal = frame.closest('[data-gwr-modal]');
  }, { passive: true });

  document.addEventListener('touchend', function (event) {
    if (galleryTouchStartX === null || !galleryTouchModal || !event.changedTouches.length) return;
    var distance = event.changedTouches[0].clientX - galleryTouchStartX;
    if (Math.abs(distance) > 48) setGalleryImage(galleryTouchModal, galleryActiveIndex(galleryTouchModal) + (distance < 0 ? 1 : -1));
    galleryTouchStartX = null;
    galleryTouchModal = null;
  }, { passive: true });

  document.addEventListener('keydown', function (event) {
    var openModal = qs(document, '[data-gwr-modal].is-open');
    if (!openModal) return;
    if (event.key === 'Escape') {
      closeModal(openModal);
      return;
    }
    var gallery = document.activeElement && document.activeElement.closest ? document.activeElement.closest('.gwr-config-gallery') : null;
    if (gallery && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
      event.preventDefault();
      setGalleryImage(openModal, galleryActiveIndex(openModal) + (event.key === 'ArrowRight' ? 1 : -1));
      return;
    }
    if (event.key !== 'Tab') return;
    var focusable = qsa(openModal, 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])').filter(function (element) {
      return !element.hidden && element.getAttribute('aria-hidden') !== 'true' && element.getClientRects().length > 0;
    });
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  function boot() { qsa(document, '[data-gwr-catalog]').forEach(initCatalog); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();

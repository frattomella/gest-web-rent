(function () {
  function qs(root, selector) {
    return root ? root.querySelector(selector) : null;
  }

  function qsa(root, selector) {
    return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : [];
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[char];
    });
  }

  function debounce(fn, delay) {
    var timer;
    return function () {
      var args = arguments;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        fn.apply(null, args);
      }, delay);
    };
  }

  function formDataObject(form) {
    var data = {};
    if (!form) {
      return data;
    }
    new FormData(form).forEach(function (value, key) {
      data[key] = value;
    });
    return data;
  }

  function validDateRange(data, errorNode) {
    if (data.start_date && data.end_date && data.end_date < data.start_date) {
      if (errorNode) {
        errorNode.textContent = gwrCatalog.i18n.dateError;
        errorNode.hidden = false;
      }
      return false;
    }
    if (errorNode) {
      errorNode.hidden = true;
      errorNode.textContent = '';
    }
    return true;
  }

  function decodeVehiclePayload(trigger) {
    var b64 = trigger.getAttribute('data-gwr-vehicle-b64');
    if (b64) {
      return JSON.parse(window.atob(b64));
    }
    var raw = trigger.getAttribute('data-gwr-vehicle');
    if (raw) {
      return JSON.parse(raw);
    }
    throw new Error('Missing vehicle payload');
  }

  function replaceTokens(template, vehicle, dates) {
    var start = dates.start_date || gwrCatalog.i18n.datesGeneric;
    var end = dates.end_date || dates.start_date || gwrCatalog.i18n.datesGeneric;
    var map = {
      '{vehicle_title}': vehicle.title || '',
      '{start_date}': start,
      '{end_date}': end,
      '{site_url}': gwrCatalog.contact.siteUrl || window.location.href,
      '{vehicle_url}': gwrCatalog.contact.siteUrl || window.location.href,
      '{dealer_name}': gwrCatalog.contact.dealerName || '',
      '{brand}': vehicle.brand || '',
      '{model}': vehicle.model || '',
      '{version}': vehicle.version || '',
      '{daily_price}': vehicle.daily_price || '',
      '{weekly_price}': vehicle.weekly_price || '',
      '{monthly_price}': vehicle.monthly_price || ''
    };

    return String(template || '').replace(/\{[a-z_]+\}/g, function (token) {
      return Object.prototype.hasOwnProperty.call(map, token) ? map[token] : token;
    });
  }

  function contactLinks(vehicle, dates) {
    var hasDates = !!(dates.start_date || dates.end_date);
    var whatsappTemplate = hasDates
      ? (gwrCatalog.contact.whatsappTemplate || 'Ciao, vorrei informazioni sul noleggio del veicolo {vehicle_title}. Date di interesse: {start_date} - {end_date}. Link: {site_url}')
      : 'Ciao, vorrei informazioni sul noleggio del veicolo {vehicle_title}. Link: {site_url}';
    var emailBody = hasDates
      ? (gwrCatalog.contact.emailBody || 'Buongiorno,\nvorrei ricevere informazioni sul noleggio del veicolo {vehicle_title}.\n\nDate di interesse:\nDal {start_date} al {end_date}\n\nGrazie.')
      : 'Buongiorno,\nvorrei ricevere informazioni sul noleggio del veicolo {vehicle_title}.\n\nDate di interesse: Date da definire.\n\nGrazie.';
    var subject = replaceTokens(gwrCatalog.contact.emailSubject || 'Richiesta noleggio {vehicle_title}', vehicle, dates);
    var body = replaceTokens(emailBody, vehicle, dates);
    var links = {};

    if (gwrCatalog.contact.whatsappNumber) {
      links.whatsapp = 'https://wa.me/' + encodeURIComponent(gwrCatalog.contact.whatsappNumber) + '?text=' + encodeURIComponent(replaceTokens(whatsappTemplate, vehicle, dates));
    }
    if (gwrCatalog.contact.contactEmail) {
      links.email = 'mailto:' + encodeURIComponent(gwrCatalog.contact.contactEmail) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
    }
    return links;
  }

  function detailRow(label, value) {
    if (value === undefined || value === null || value === '' || value === false) {
      return '';
    }
    if (value === true) {
      value = 'Si';
    }
    return '<div><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>';
  }

  function renderModalContent(vehicle, dates) {
    var images = vehicle.images && vehicle.images.length ? vehicle.images : [];
    var links = contactLinks(vehicle, dates || {});
    var mainImage = images[0] ? images[0].url : '';
    var thumbs = images.map(function (image, index) {
      return '<button type="button" data-gwr-gallery-index="' + index + '"' + (index === 0 ? ' class="is-active"' : '') + '><img src="' + escapeHtml(image.url) + '" alt="" /></button>';
    }).join('');
    var features = vehicle.features && vehicle.features.length
      ? '<ul class="gwr-modal-features">' + vehicle.features.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') + '</ul>'
      : '<p class="gwr-muted">Dotazioni da definire.</p>';

    return [
      '<div class="gwr-modal-grid">',
      '<div class="gwr-modal-gallery">',
      mainImage ? '<img class="gwr-modal-main-image" src="' + escapeHtml(mainImage) + '" alt="' + escapeHtml(vehicle.title) + '" data-gwr-modal-image />' : '<div class="gwr-modal-image-empty">Foto veicolo</div>',
      images.length > 1 ? '<button type="button" class="gwr-gallery-arrow is-prev" data-gwr-gallery-prev>&#8249;</button><button type="button" class="gwr-gallery-arrow is-next" data-gwr-gallery-next>&#8250;</button><div class="gwr-gallery-thumbs">' + thumbs + '</div>' : '',
      '</div>',
      '<div class="gwr-modal-copy">',
      '<span class="gwr-kicker">' + escapeHtml(vehicle.category || 'Noleggio') + '</span>',
      '<h2 id="gwr-modal-title">' + escapeHtml(vehicle.title) + '</h2>',
      '<p class="gwr-modal-subtitle">' + escapeHtml([vehicle.brand, vehicle.model, vehicle.version].filter(Boolean).join(' ')) + '</p>',
      '<div class="gwr-modal-price-strip">',
      detailRow('Giorno', vehicle.daily_price),
      detailRow('Weekend', vehicle.weekend_price),
      detailRow('Settimana', vehicle.weekly_price),
      detailRow('Mese', vehicle.monthly_price),
      '</div>',
      '<div class="gwr-modal-details">',
      detailRow('Km/giorno', vehicle.included_km_daily),
      detailRow('Km/settimana', vehicle.included_km_weekly),
      detailRow('Km/mese', vehicle.included_km_monthly),
      detailRow('Costo km extra', vehicle.extra_km_price),
      detailRow('Cauzione', vehicle.deposit),
      detailRow('Franchigia', vehicle.deductible),
      detailRow('Eta minima', vehicle.min_driver_age),
      detailRow('Anni patente', vehicle.min_license_years),
      detailRow('Patente', vehicle.required_license),
      detailRow('Durata minima', vehicle.min_rental_days),
      detailRow('Durata massima', vehicle.max_rental_days),
      detailRow('Assicurazione inclusa', vehicle.insurance_included),
      detailRow('Secondo conducente', vehicle.second_driver_included),
      detailRow('Consegna domicilio', vehicle.home_delivery),
      detailRow('Sede', vehicle.location),
      '</div>',
      '<section class="gwr-modal-section"><h3>Dotazioni</h3>' + features + '</section>',
      vehicle.description ? '<section class="gwr-modal-section"><h3>Descrizione</h3><div class="gwr-modal-text">' + vehicle.description + '</div></section>' : '',
      vehicle.rental_notes ? '<section class="gwr-modal-section"><h3>Note noleggio</h3><div class="gwr-modal-text">' + vehicle.rental_notes + '</div></section>' : '',
      '<div class="gwr-modal-contact"><p>' + escapeHtml(gwrCatalog.contact.privacyNote || '') + '</p><div class="gwr-modal-contact__actions">',
      links.whatsapp ? '<a class="gwr-button" target="_blank" rel="noopener noreferrer" href="' + escapeHtml(links.whatsapp) + '">WhatsApp</a>' : '',
      links.email ? '<a class="gwr-button-secondary" href="' + escapeHtml(links.email) + '">Email</a>' : '',
      '</div></div>',
      '</div></div>'
    ].join('');
  }

  function initModal(catalog) {
    var modal = qs(catalog, '[data-gwr-modal]');
    var content = qs(catalog, '[data-gwr-modal-content]');
    var currentImages = [];
    var currentIndex = 0;

    if (!modal || !content) {
      return;
    }

    function setImage(index) {
      var image = qs(modal, '[data-gwr-modal-image]');
      if (!currentImages.length) {
        return;
      }
      currentIndex = (index + currentImages.length) % currentImages.length;
      if (image && currentImages[currentIndex]) {
        image.src = currentImages[currentIndex].url;
      }
      qsa(modal, '[data-gwr-gallery-index]').forEach(function (button) {
        button.classList.toggle('is-active', Number(button.getAttribute('data-gwr-gallery-index')) === currentIndex);
      });
    }

    function open(vehicle) {
      var dates = formDataObject(qs(catalog, '[data-gwr-filter-form]'));
      currentImages = vehicle.images || [];
      currentIndex = 0;
      content.innerHTML = renderModalContent(vehicle, dates);
      modal.hidden = false;
      document.body.classList.add('gwr-modal-open');
      var closeButton = qs(modal, '.gwr-modal__close');
      if (closeButton) {
        closeButton.focus();
      }
    }

    function close() {
      modal.hidden = true;
      document.body.classList.remove('gwr-modal-open');
      content.innerHTML = '';
      currentImages = [];
    }

    catalog.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-gwr-open-modal]');
      var closeTrigger = event.target.closest('[data-gwr-close-modal]');
      var thumb = event.target.closest('[data-gwr-gallery-index]');

      if (trigger) {
        event.preventDefault();
        try {
          open(decodeVehiclePayload(trigger));
        } catch (error) {
          console.error('Gest Web Rent modal error:', error);
          var errorNode = qs(catalog, '[data-gwr-error]');
          if (errorNode) {
            errorNode.textContent = (gwrCatalog.i18n && gwrCatalog.i18n.modalError) || 'Impossibile aprire i dettagli del veicolo.';
            errorNode.hidden = false;
          }
        }
        return;
      }

      if (closeTrigger) {
        close();
        return;
      }

      if (event.target.closest('[data-gwr-gallery-prev]') && currentImages.length) {
        setImage(currentIndex - 1);
        return;
      }

      if (event.target.closest('[data-gwr-gallery-next]') && currentImages.length) {
        setImage(currentIndex + 1);
        return;
      }

      if (thumb && currentImages.length) {
        setImage(Number(thumb.getAttribute('data-gwr-gallery-index')));
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !modal.hidden) {
        close();
      }
    });
  }

  function initCatalog(catalog) {
    var form = qs(catalog, '[data-gwr-filter-form]');
    var results = qs(catalog, '[data-gwr-results]');
    var count = qs(catalog, '[data-gwr-count]');
    var errorNode = qs(catalog, '[data-gwr-error]');

    if (!form || !results) {
      return;
    }

    function update() {
      var data = formDataObject(form);
      var body = new FormData();

      if (!validDateRange(data, errorNode)) {
        return;
      }

      body.append('action', 'gwr_filter_catalog');
      body.append('nonce', gwrCatalog.nonce);
      Object.keys(data).forEach(function (key) {
        body.append(key, data[key]);
      });

      catalog.classList.add('is-loading');
      return window.fetch(gwrCatalog.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: body
      }).then(function (response) {
        return response.json();
      }).then(function (json) {
        if (!json || !json.success) {
          throw new Error('AJAX error');
        }
        results.innerHTML = json.data.html;
        if (json.data.error && errorNode) {
          errorNode.textContent = json.data.error;
          errorNode.hidden = false;
        } else if (errorNode) {
          errorNode.hidden = true;
        }
        if (count) {
          count.textContent = json.data.count === 1 ? gwrCatalog.i18n.countOne : gwrCatalog.i18n.countMany.replace('%d', json.data.count);
        }
      }).catch(function (error) {
        console.error('Gest Web Rent filter error:', error);
        if (errorNode) {
          errorNode.textContent = 'Errore durante il filtro catalogo.';
          errorNode.hidden = false;
        }
      }).finally(function () {
        catalog.classList.remove('is-loading');
      });
    }

    var debouncedUpdate = debounce(update, 320);
    form.addEventListener('input', debouncedUpdate);
    form.addEventListener('change', debouncedUpdate);
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      update();
    });

    catalog.addEventListener('click', function (event) {
      if (!event.target.closest('[data-gwr-reset-filters]')) {
        return;
      }
      form.reset();
      update();
    });

    initModal(catalog);
  }

  function boot() {
    if (typeof window.gwrCatalog === 'undefined') {
      return;
    }
    qsa(document, '[data-gwr-catalog]').forEach(initCatalog);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
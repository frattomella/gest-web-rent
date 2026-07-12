(function ($) {
  function escapeAttr(value) {
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

  function imageTemplate(id, url, checked) {
    return [
      '<div class="gwr-media-item" data-gwr-image-id="' + escapeAttr(id) + '">',
      '<img src="' + escapeAttr(url) + '" alt="" />',
      '<input type="hidden" name="gwr_images[]" value="' + escapeAttr(id) + '" />',
      '<label><input type="radio" name="gwr_cover_id" value="' + escapeAttr(id) + '"' + (checked ? ' checked' : '') + ' /> ' + escapeAttr(gwrAdmin.cover || 'Copertina') + '</label>',
      '<div class="gwr-media-item__actions">',
      '<button type="button" class="button-link" data-gwr-move-image="up">' + escapeAttr(gwrAdmin.moveUp || 'Su') + '</button>',
      '<button type="button" class="button-link" data-gwr-move-image="down">' + escapeAttr(gwrAdmin.moveDown || 'Giu') + '</button>',
      '<button type="button" class="button-link-delete" data-gwr-remove-image>' + escapeAttr(gwrAdmin.remove || 'Rimuovi') + '</button>',
      '</div></div>'
    ].join('');
  }

  function availabilityTemplate(index) {
    return [
      '<div class="gwr-availability-row" data-gwr-availability-row>',
      '<label><span>Data inizio</span><input type="date" name="gwr_availability[' + index + '][start_date]" value="" /></label>',
      '<label><span>Data fine</span><input type="date" name="gwr_availability[' + index + '][end_date]" value="" /></label>',
      '<label><span>Stato</span><select name="gwr_availability[' + index + '][status]"><option value="busy">Occupato</option><option value="maintenance">Manutenzione</option><option value="reserved">Riservato</option><option value="unavailable">Non disponibile</option></select></label>',
      '<label><span>Riferimento esterno</span><input type="text" name="gwr_availability[' + index + '][external_reference]" value="" /></label>',
      '<label class="gwr-availability-row__note"><span>Nota interna</span><input type="text" name="gwr_availability[' + index + '][internal_note]" value="" /></label>',
      '<button type="button" class="button-link-delete" data-gwr-remove-availability>Rimuovi</button>',
      '</div>'
    ].join('');
  }

  function ensureCover(list) {
    if (!list || list.querySelector('input[name="gwr_cover_id"]:checked')) {
      return;
    }
    var first = list.querySelector('input[name="gwr_cover_id"]');
    if (first) {
      first.checked = true;
    }
  }

  $(document).on('click', '[data-gwr-add-images]', function () {
    var manager = this.closest('[data-gwr-media-manager]');
    var list = manager ? manager.querySelector('[data-gwr-media-list]') : null;
    var frame;

    if (!list || !window.wp || !wp.media) {
      return;
    }

    frame = wp.media({
      title: gwrAdmin.mediaTitle || 'Seleziona foto veicolo',
      button: { text: gwrAdmin.mediaButton || 'Usa foto selezionate' },
      library: { type: 'image' },
      multiple: true
    });

    frame.on('select', function () {
      var existing = Array.prototype.slice.call(list.querySelectorAll('[data-gwr-image-id]')).map(function (item) {
        return item.getAttribute('data-gwr-image-id');
      });

      frame.state().get('selection').each(function (attachment) {
        var data = attachment.toJSON();
        var url = data.sizes && data.sizes.medium ? data.sizes.medium.url : data.url;
        if (existing.indexOf(String(data.id)) !== -1) {
          return;
        }
        list.insertAdjacentHTML('beforeend', imageTemplate(data.id, url, !list.querySelector('[data-gwr-image-id]')));
        existing.push(String(data.id));
      });
      ensureCover(list);
    });

    frame.open();
  });

  $(document).on('click', '[data-gwr-remove-image]', function () {
    var item = this.closest('[data-gwr-image-id]');
    var list = item ? item.parentNode : null;
    if (item) {
      item.remove();
    }
    ensureCover(list);
  });

  $(document).on('click', '[data-gwr-move-image]', function () {
    var direction = this.getAttribute('data-gwr-move-image');
    var item = this.closest('[data-gwr-image-id]');
    if (!item) {
      return;
    }
    if (direction === 'up' && item.previousElementSibling) {
      item.parentNode.insertBefore(item, item.previousElementSibling);
    }
    if (direction === 'down' && item.nextElementSibling) {
      item.parentNode.insertBefore(item.nextElementSibling, item);
    }
  });

  $(document).on('click', '[data-gwr-add-availability]', function () {
    var editor = this.closest('[data-gwr-availability-editor]');
    var rows = editor ? editor.querySelector('[data-gwr-availability-rows]') : null;
    if (rows) {
      rows.insertAdjacentHTML('beforeend', availabilityTemplate(Date.now()));
    }
  });

  $(document).on('click', '[data-gwr-remove-availability]', function () {
    var row = this.closest('[data-gwr-availability-row]');
    var list = row ? row.parentNode : null;
    if (row && list && list.querySelectorAll('[data-gwr-availability-row]').length > 1) {
      row.remove();
      return;
    }
    if (row) {
      row.querySelectorAll('input').forEach(function (input) {
        input.value = '';
      });
    }
  });

  function updateRepeaterState(repeater) {
    if (!repeater) return;
    var hasRows = !!repeater.querySelector('[data-gwr-repeater-row]');
    var empty = repeater.querySelector('[data-gwr-repeater-empty]');
    if (empty) empty.hidden = hasRows;
  }

  function updateInheritance(section, inherited) {
    if (!section) return;
    var fields = section.querySelector('[data-gwr-section-fields]');
    var badge = section.querySelector('[data-gwr-inherit-badge]');
    section.classList.toggle('is-inherited', inherited);
    if (fields) {
      fields.setAttribute('aria-disabled', inherited ? 'true' : 'false');
      fields.inert = inherited;
    }
    if (badge) badge.textContent = inherited ? (gwrAdmin.global || 'Globale') : (gwrAdmin.custom || 'Personalizzato');
  }

  function setConditionalState(field, inactive) {
    var label = field ? field.closest('.gwr-field, .gwr-check-card') : null;
    if (!label) return;
    label.classList.toggle('is-conditionally-inactive', inactive);
    label.inert = inactive;
    label.setAttribute('aria-disabled', inactive ? 'true' : 'false');
  }

  function updateConditionalFields(editor) {
    if (!editor) return;
    var depositRequired = editor.querySelector('[name*="[security_deposit][required]"]');
    if (depositRequired) {
      editor.querySelectorAll('[name*="[security_deposit]"]').forEach(function (field) {
        if (field !== depositRequired) setConditionalState(field, !depositRequired.checked);
      });
    }
    var mileageType = editor.querySelector('[name*="[mileage_policy][type]"]');
    if (mileageType) {
      ['included_km', 'extra_km_cost'].forEach(function (key) {
        setConditionalState(editor.querySelector('[name*="[mileage_policy][' + key + ']"]'), mileageType.value === 'unlimited');
      });
    }
    var cancellationAllowed = editor.querySelector('[name*="[cancellation_policy][allowed]"]');
    if (cancellationAllowed) {
      editor.querySelectorAll('[name*="[cancellation_policy]"]').forEach(function (field) {
        if (field !== cancellationAllowed && !field.name.endsWith('[description]') && !field.name.endsWith('[notes]')) setConditionalState(field, !cancellationAllowed.checked);
      });
    }
    editor.querySelectorAll('[data-gwr-repeater-section="extras"] [name$="[price_mode]"]').forEach(function (mode) {
      var row = mode.closest('[data-gwr-repeater-row]');
      setConditionalState(row ? row.querySelector('[name$="[price]"]') : null, mode.value === 'free');
    });
  }

  document.querySelectorAll('[data-gwr-inherit-toggle]').forEach(function (toggle) {
    updateInheritance(toggle.closest('[data-gwr-terms-section]'), toggle.checked);
  });
  document.querySelectorAll('[data-gwr-terms-editor]').forEach(updateConditionalFields);

  $(document).on('change', '[data-gwr-inherit-toggle]', function () {
    updateInheritance(this.closest('[data-gwr-terms-section]'), this.checked);
  });

  $(document).on('click', '[data-gwr-add-repeater]', function () {
    var repeater = this.closest('[data-gwr-repeater]');
    var rows = repeater ? repeater.querySelector('[data-gwr-repeater-rows]') : null;
    var template = repeater ? repeater.querySelector('[data-gwr-repeater-template]') : null;
    if (!rows || !template) return;
    var index = String(Date.now()) + String(Math.floor(Math.random() * 1000));
    rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, index));
    updateRepeaterState(repeater);
    var added = rows.lastElementChild;
    var firstInput = added ? added.querySelector('input:not([type="hidden"]), select, textarea') : null;
    if (firstInput) firstInput.focus();
    updateConditionalFields(repeater.closest('[data-gwr-terms-editor]'));
  });

  $(document).on('click', '[data-gwr-remove-repeater]', function () {
    if (!window.confirm(gwrAdmin.confirmRemove || 'Eliminare questa voce?')) return;
    var row = this.closest('[data-gwr-repeater-row]');
    var repeater = row ? row.closest('[data-gwr-repeater]') : null;
    if (row) row.remove();
    updateRepeaterState(repeater);
  });

  $(document).on('click', '[data-gwr-move-repeater]', function () {
    var row = this.closest('[data-gwr-repeater-row]');
    if (!row) return;
    if (this.getAttribute('data-gwr-move-repeater') === 'up' && row.previousElementSibling) {
      row.parentNode.insertBefore(row, row.previousElementSibling);
    } else if (this.getAttribute('data-gwr-move-repeater') === 'down' && row.nextElementSibling) {
      row.parentNode.insertBefore(row.nextElementSibling, row);
    }
    row.focus({ preventScroll: true });
  });

  $(document).on('input', '[data-gwr-row-label]', function () {
    var row = this.closest('[data-gwr-repeater-row]');
    var title = row ? row.querySelector('[data-gwr-row-title]') : null;
    if (title) title.textContent = this.value.trim() || (gwrAdmin.newRow || 'Nuova voce');
  });

  $(document).on('change', '[data-gwr-terms-editor] input, [data-gwr-terms-editor] select', function () {
    updateConditionalFields(this.closest('[data-gwr-terms-editor]'));
  });

  $(document).on('click', '.gwr-terms-tabs a', function (event) {
    var target = document.querySelector(this.getAttribute('href'));
    if (!target) return;
    event.preventDefault();
    target.scrollIntoView({ behavior: window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    var focusTarget = target.querySelector('input, select, textarea, button');
    if (focusTarget) window.setTimeout(function () { focusTarget.focus({ preventScroll: true }); }, 250);
  });

  $(document).on('click', '[data-gwr-doc-logo-button]', function () {
    var field = this.closest('[data-gwr-doc-logo-field]');
    var input = field ? field.querySelector('[data-gwr-doc-logo-id]') : null;
    var preview = field ? field.querySelector('[data-gwr-doc-logo-preview]') : null;
    var frame;
    if (!input || !preview || !window.wp || !wp.media) return;
    frame = wp.media({
      title: gwrAdmin.documentLogoTitle || 'Seleziona logo documenti',
      button: { text: gwrAdmin.documentLogoButton || 'Usa questo logo' },
      library: { type: 'image' },
      multiple: false
    });
    frame.on('select', function () {
      var data = frame.state().get('selection').first().toJSON();
      var url = data.sizes && data.sizes.medium ? data.sizes.medium.url : data.url;
      input.value = data.id;
      preview.innerHTML = '<img src="' + escapeAttr(url) + '" alt="" />';
    });
    frame.open();
  });

  function setupSignaturePad(form) {
    var canvas = form.querySelector('.gwr-signature-canvas');
    var output = form.querySelector('[data-gwr-signature-data]');
    var ctx;
    var drawing = false;
    var hasInk = false;
    if (!canvas || !output || canvas.dataset.gwrReady) return;
    canvas.dataset.gwrReady = '1';
    ctx = canvas.getContext('2d');
    ctx.lineWidth = 2.4;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#111827';

    function point(event) {
      var rect = canvas.getBoundingClientRect();
      var source = event.touches && event.touches.length ? event.touches[0] : event;
      return {
        x: (source.clientX - rect.left) * (canvas.width / rect.width),
        y: (source.clientY - rect.top) * (canvas.height / rect.height)
      };
    }

    function start(event) {
      event.preventDefault();
      drawing = true;
      hasInk = true;
      var pos = point(event);
      ctx.beginPath();
      ctx.moveTo(pos.x, pos.y);
    }

    function move(event) {
      if (!drawing) return;
      event.preventDefault();
      var pos = point(event);
      ctx.lineTo(pos.x, pos.y);
      ctx.stroke();
      output.value = canvas.toDataURL('image/png');
    }

    function end() {
      if (!drawing) return;
      drawing = false;
      output.value = hasInk ? canvas.toDataURL('image/png') : '';
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);

    form.addEventListener('submit', function (event) {
      if (!output.value) {
        event.preventDefault();
        window.alert(gwrAdmin.signatureRequired || 'Disegna la firma prima di salvare.');
      }
    });
  }

  document.querySelectorAll('[data-gwr-signature-pad]').forEach(setupSignaturePad);

  $(document).on('toggle', '.gwr-signature-details', function () {
    if (this.open) {
      var form = this.querySelector('[data-gwr-signature-pad]');
      if (form) setupSignaturePad(form);
    }
  });

  $(document).on('click', '[data-gwr-clear-signature]', function () {
    var form = this.closest('[data-gwr-signature-pad]');
    var canvas = form ? form.querySelector('.gwr-signature-canvas') : null;
    var output = form ? form.querySelector('[data-gwr-signature-data]') : null;
    if (!canvas || !output) return;
    canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
    output.value = '';
  });
})(jQuery);

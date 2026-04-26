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
})(jQuery);

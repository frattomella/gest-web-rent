(function () {
  function rowTemplate(index) {
    return [
      '<div class="gwr-availability-row" data-gwr-availability-row>',
      '<label><span>Data inizio</span><input type="date" name="gwr_availability[' + index + '][start_date]" value="" /></label>',
      '<label><span>Data fine</span><input type="date" name="gwr_availability[' + index + '][end_date]" value="" /></label>',
      '<input type="hidden" name="gwr_availability[' + index + '][id]" value="0" />',
      '<label><span>Stato</span><select name="gwr_availability[' + index + '][status]"><option value="occupied">Occupato</option><option value="maintenance">Manutenzione</option><option value="reserved">Riservato</option><option value="unavailable">Non disponibile</option></select></label>',
      '<label><span>Riferimento esterno</span><input type="text" name="gwr_availability[' + index + '][external_reference]" value="" placeholder="Pratica, contratto, cliente" /></label>',
      '<label class="gwr-availability-row__note"><span>Nota interna</span><input type="text" name="gwr_availability[' + index + '][internal_note]" value="" /></label>',
      '<button type="button" class="button-link-delete" data-gwr-remove-availability>Rimuovi</button>',
      '</div>'
    ].join('');
  }

  document.addEventListener('click', function (event) {
    var addButton = event.target.closest('[data-gwr-add-availability]');
    if (addButton) {
      var editor = addButton.closest('[data-gwr-availability-editor]');
      var rows = editor ? editor.querySelector('[data-gwr-availability-rows]') : null;
      if (rows) {
        var index = Date.now();
        rows.insertAdjacentHTML('beforeend', rowTemplate(index));
      }
    }

    var removeButton = event.target.closest('[data-gwr-remove-availability]');
    if (removeButton) {
      var row = removeButton.closest('[data-gwr-availability-row]');
      var list = row ? row.parentNode : null;
      if (row && list && list.querySelectorAll('[data-gwr-availability-row]').length > 1) {
        row.remove();
      } else if (row) {
        row.querySelectorAll('input').forEach(function (input) {
          input.value = '';
        });
      }
    }
  });
})();

(function () {
  function rowTemplate(index) {
    return [
      '<div class="gwr-availability-row" data-gwr-availability-row>',
      '<label><span>Da</span><input type="date" name="gwr_availability[' + index + '][date_from]" value="" /></label>',
      '<label><span>A</span><input type="date" name="gwr_availability[' + index + '][date_to]" value="" /></label>',
      '<label><span>Stato</span><select name="gwr_availability[' + index + '][status]"><option value="booked">Impegnato</option><option value="maintenance">Manutenzione</option><option value="unavailable">Non disponibile</option></select></label>',
      '<label><span>Riferimento</span><input type="text" name="gwr_availability[' + index + '][label]" value="" placeholder="Cliente, pratica, motivo" /></label>',
      '<label class="gwr-availability-row__note"><span>Note</span><input type="text" name="gwr_availability[' + index + '][note]" value="" /></label>',
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

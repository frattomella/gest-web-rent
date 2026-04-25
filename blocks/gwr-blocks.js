(function (blocks, element, components, serverSideRender) {
  var el = element.createElement;
  var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
  var useBlockProps = wp.blockEditor ? wp.blockEditor.useBlockProps : function () { return {}; };
  var PanelBody = components.PanelBody;
  var RangeControl = components.RangeControl;
  var ToggleControl = components.ToggleControl;
  var TextControl = components.TextControl;
  var SelectControl = components.SelectControl;
  var Disabled = components.Disabled || function (props) {
    return el('div', { style: { pointerEvents: 'none' } }, props.children);
  };
  var ServerSideRender = serverSideRender;
  var data = window.gwrBlockData || { vehicles: [] };

  function preview(props, label, description, controls) {
    var blockProps = useBlockProps({ className: 'gwr-block-preview' });
    return el('div', blockProps, [
      el(InspectorControls, {}, controls || []),
      el('div', { className: 'gwr-block-label' }, label),
      description ? el('p', { className: 'gwr-block-description' }, description) : null,
      el('div', { className: 'gwr-editor-frame' }, [
        el(Disabled, {}, el(ServerSideRender, {
          block: props.name,
          attributes: props.attributes,
          httpMethod: 'POST',
          LoadingResponsePlaceholder: function () {
            return el('div', { className: 'gwr-editor-empty' }, 'Caricamento anteprima Gest Web Rent...');
          },
          EmptyResponsePlaceholder: function () {
            return el('div', { className: 'gwr-editor-empty' }, 'Anteprima non disponibile. Aggiungi almeno un veicolo.');
          },
          ErrorResponsePlaceholder: function () {
            return el('div', { className: 'gwr-editor-empty gwr-editor-empty--error' }, 'Anteprima temporaneamente non disponibile.');
          }
        }))
      ])
    ]);
  }

  blocks.registerBlockType('gest-web-rent/catalog', {
    title: 'Gest Web Rent - Catalogo veicoli',
    icon: 'car',
    category: 'widgets',
    attributes: {
      limit: { type: 'number', default: -1 },
      columns: { type: 'number', default: 3 },
      filters: { type: 'boolean', default: true },
      title: { type: 'string', default: 'Veicoli a noleggio' }
    },
    edit: function (props) {
      return preview(props, 'Catalogo veicoli', 'Card veicoli con filtri data, marca, posti e prezzo.', [
        el(PanelBody, { title: 'Impostazioni catalogo', initialOpen: true }, [
          el(TextControl, {
            label: 'Titolo',
            value: props.attributes.title,
            onChange: function (value) { props.setAttributes({ title: value }); }
          }),
          el(RangeControl, {
            label: 'Colonne',
            min: 1,
            max: 4,
            value: props.attributes.columns,
            onChange: function (value) { props.setAttributes({ columns: value }); }
          }),
          el(RangeControl, {
            label: 'Limite veicoli (-1 mostra tutto)',
            min: -1,
            max: 24,
            value: props.attributes.limit,
            onChange: function (value) { props.setAttributes({ limit: value }); }
          }),
          el(ToggleControl, {
            label: 'Mostra filtri',
            checked: !!props.attributes.filters,
            onChange: function (value) { props.setAttributes({ filters: value }); }
          })
        ])
      ]);
    },
    save: function () {
      return null;
    }
  });

  blocks.registerBlockType('gest-web-rent/availability', {
    title: 'Gest Web Rent - Calendario disponibilita',
    icon: 'calendar-alt',
    category: 'widgets',
    attributes: {
      columns: { type: 'number', default: 3 },
      title: { type: 'string', default: 'Verifica disponibilita' }
    },
    edit: function (props) {
      return preview(props, 'Calendario disponibilita', 'Filtro date per mostrare soltanto i veicoli disponibili.', [
        el(PanelBody, { title: 'Impostazioni calendario', initialOpen: true }, [
          el(TextControl, {
            label: 'Titolo',
            value: props.attributes.title,
            onChange: function (value) { props.setAttributes({ title: value }); }
          }),
          el(RangeControl, {
            label: 'Colonne risultati',
            min: 1,
            max: 4,
            value: props.attributes.columns,
            onChange: function (value) { props.setAttributes({ columns: value }); }
          })
        ])
      ]);
    },
    save: function () {
      return null;
    }
  });

  blocks.registerBlockType('gest-web-rent/vehicle', {
    title: 'Gest Web Rent - Scheda veicolo',
    icon: 'id',
    category: 'widgets',
    attributes: {
      vehicleId: { type: 'number', default: 0 }
    },
    edit: function (props) {
      return preview(props, 'Scheda veicolo', 'Foto, condizioni noleggio, disponibilita e box contatto.', [
        el(PanelBody, { title: 'Veicolo', initialOpen: true }, [
          el(SelectControl, {
            label: 'Seleziona veicolo',
            value: props.attributes.vehicleId,
            options: data.vehicles || [],
            onChange: function (value) { props.setAttributes({ vehicleId: parseInt(value, 10) || 0 }); }
          })
        ])
      ]);
    },
    save: function () {
      return null;
    }
  });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.serverSideRender);

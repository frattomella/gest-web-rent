(function (blocks, element, serverSideRender) {
    var el = element.createElement;

    blocks.registerBlockType('gest-web-rent/catalog', {
        apiVersion: 2,
        title: 'Gest Web Rent Catalogo',
        description: 'Catalogo veicoli a noleggio con filtri date, card e dettaglio in overlay.',
        icon: 'car',
        category: 'widgets',
        supports: {
            html: false,
            align: ['wide', 'full']
        },
        attributes: {
            title: {
                type: 'string',
                default: 'Catalogo noleggio'
            },
            subtitle: {
                type: 'string',
                default: 'Scegli le date, filtra il parco veicoli e apri i dettagli senza cambiare pagina.'
            },
            max_width: {
                type: 'string',
                default: '1380'
            }
        },

        edit: function (props) {
            return el(serverSideRender, {
                block: 'gest-web-rent/catalog',
                attributes: props.attributes || {}
            });
        },

        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.serverSideRender);
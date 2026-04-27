(function (blocks, element, components, blockEditor, serverSideRender) {
    var el = element.createElement;

    blocks.registerBlockType('gest-web-rent/catalog', {
        title: 'Gest Web Rent Catalogo',
        icon: 'car',
        category: 'widgets',

        edit: function (props) {
            return el(serverSideRender, {
                block: 'gest-web-rent/catalog',
                attributes: props.attributes
            });
        },

        save: function () {
            return null; // dynamic block
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender);
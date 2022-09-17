var app = app || {};
app.comprobante = (function () {
    const ROUTES = Object.freeze({
        ADD: 'comprobante/add/',
        EDIT: 'comprobante/edit/',
        DELETE: 'comprobante/delete/',
        EXPORT: 'comprobante/exportar/',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let init = function () {
        let fields = [
            {
                type: window.FormType.TEXT,
                label: 'Código',
                name: 'codigo',
                required: true,
                isIdentity: true,
                visible: true
            },
            {
                type: window.FormType.TEXT,
                label: 'Descripción',
                name: 'descripcion',
                required: true,
                width: 200,
                visible: true
            },
            {
                type: window.FormType.SWITCH,
                label: 'Activo',
                name: 'estado',
                required: true,
                visible: true,
                formatter: activeFormatter,
                defaultValue: true
            }
        ];

        dataGrid = new window.DataGrid('wrapper', fields,
            {
                title: 'Tipos de comprobantes SUNAT',
                url: 'comprobante/get',
                routes: ROUTES,
            });

        dialog = new window.Dialog('wrapper');

        form = new window.Form(dialog.id, fields);
    };

    return {
        init: init
    };

})();
app.comprobante.init();

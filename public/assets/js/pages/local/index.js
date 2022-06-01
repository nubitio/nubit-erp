var app = app || {};
app.local = (function () {
    const ROUTES = Object.freeze({
        ADD: 'local/add/',
        EDIT: 'local/edit/',
        DELETE: 'local/delete/',
        EXPORT: 'local/exportar/',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let init = async function () {
        let empresaName = await loadData('empresa/lista/1/0', 'empresa_id', 'razon_social');

        let fields = [
            {
                type: window.FormType.HIDDEN,
                name: 'local_id',
                isKey: true,
                visible: false
            },
            {
                type: window.FormType.SELECT,
                label: 'Empresa',
                name: 'empresa_id',
                data: empresaName,
                required: true,
                width: 200,
                visible: false
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Empresa',
                name: 'empresa',
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
                type: window.FormType.TEXTAREA,
                label: 'Dirección',
                name: 'direccion',
                required: false,
                width: 300,
                visible: true
            },
            {
                type: window.FormType.TEXT,
                label: 'Teléfono',
                name: 'telefono',
                required: false,
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

        dataGrid = new window.DataGrid('wrapper', fields, {
            title: 'Locales',
            url: 'local/get',
            routes: ROUTES,
        });

        dialog = new window.Dialog('wrapper');

        form = new window.Form(dialog.id, dataGrid.id, dialog, fields);
    };

    return {
        init: init
    };
})();
app.local.init();

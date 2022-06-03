var app = app || {};
app.local_comprobante = (function () {
    const ROUTES = Object.freeze({
        ADD: 'local-comprobante/add/',
        EDIT: 'local-comprobante/edit/',
        DELETE: 'local-comprobante/delete/',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let init = async function () {
        let empresaName = await loadData('empresa/lista/1/0', 'empresa_id', 'razon_social');
        let localName = await loadData('local/lista/1/0', 'local_id', 'descripcion');
        let comprobanteName=await loadData('comprobante/lista/1/0', 'codigo', 'descripcion');

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
                width: 200,
                visible: true
            },
            {
                type: window.FormType.SELECT,
                label: 'Local',
                name: 'local_id',
                data: localName,
                required: true,
                width: 200,
                visible: false
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Local',
                name: 'local',
                width: 200,
                visible: true
            },
            {
                type: window.FormType.SELECT,
                label: 'Tipo',
                name: 'tipo_comprobante_id',
                data: comprobanteName,
                required: true,
                width: 200,
                visible: false
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Tipo',
                name: 'tipo_comprobante_id',
                width: 200,
                visible: true
            },
            {
                type: window.FormType.TEXT,
                label: 'Serie',
                name: 'serie',
                width: 200,
                required: false,
                visible: true
            },
            {
                type: window.FormType.TEXT,
                label: 'Serie asociada',
                name: 'serie_asociada',
                width: 200,
                required: false,
                visible: true
            }

        ];

        dataGrid = new window.DataGrid('wrapper', fields, {
            title: 'local comprobante ',
            url: 'local-comprobante/get',
            routes: ROUTES,
        });

        dialog = new window.Dialog('wrapper');

        form = new window.Form(dialog.id, dataGrid.id, dialog, fields);
    };

    return {
        init: init
    };
})();
app.local_comprobante.init();
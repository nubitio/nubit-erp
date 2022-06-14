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
                type: window.FormType.HIDDEN,
                name: 'empresa',
                label: 'Empresa',
                sortable: true,
                width: 200,
                visible: true
            },
            {
                type: window.FormType.HIDDEN,
                name: 'local',
                label: 'Local',
                sortable: true,
                width: 200,
                visible: true
            },
            {
                type: window.FormType.HIDDEN,
                name: 'tipo_comprobante',
                label: 'Tipo',
                sortable: true,
                width: 200,
                visible: true
            },
            //START FORM
            {
                type: window.FormType.SELECT,
                label: 'Empresa',
                name: 'empresa',
                data: empresaName,
                required: true,
                visible: false
            },
            {
                type: window.FormType.SELECT,
                label: 'Local',
                name: 'local_id',
                data: localName,
                required: true,
                visible: false
            },
            {
                type: window.FormType.SELECT,
                label: 'Tipo Comprobante',
                name: 'tipo_comprobante_id',
                data: comprobanteName,
                required: true,
                visible: false
            },
            {
                type: window.FormType.TEXT,
                name: 'serie',
                label: 'Serie',
                sortable: true,
                width: 200,
                visible: true
            },
            {
                type: window.FormType.TEXT,
                name: 'serie_asociada',
                label: 'Serie asociada',
                sortable: true,
                width: 100,
                visible: true
            }
            //FINISH FORM
        ];

        dataGrid = new window.DataGrid('wrapper', fields, {
            title: 'local comprobante ',
            url: 'local-comprobante/get',
            routes: ROUTES,
        });

        dialog = new window.Dialog('wrapper');

        form = new window.Form(dialog, fields);
    };

    return {
        init: init
    };
})();
app.local_comprobante.init();
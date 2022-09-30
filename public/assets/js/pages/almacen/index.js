var app = app || {};
app.almacen = (function () {
    const ROUTES = Object.freeze({
        ADD: 'almacen/add/',
        EDIT: 'almacen/edit/',
        DELETE: 'almacen/delete/',
        EXPORT: 'almacen/exportar/',
    });

    const EVENTS = Object.freeze({
        ADD: 'almacen:add',
        EDIT: 'almacen:edit',
        DELETE: 'almacen:delete',
        SAVE: 'almacen:save',
        CANCEL: 'almacen:cancel',
        SUCCESS: 'almacen:success',
        LOADING: 'almacen:loading',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let init = async function () {
        let localName = await loadData('local/lista/1/0', 'local_id', 'descripcion');
        let empresaName = await loadData('empresa/lista/1/0', 'empresa_id', 'razon_social');

        let fields = [
            {
                type: window.FormType.HIDDEN,
                name: 'almacen_id',
                isIdentity: true,
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
                label: 'Local Descripción',
                name: 'descripcion',
                required: true,
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
                type: window.FormType.SELECT,
                label: 'Tipo',
                name: 'tipo_id',
                data: [{
                    value: '1',
                    text: 'PRODUCTO'
                }, {
                    value: '2',
                    text: 'INSUMO'
                }],
                formatter: typeWarehouseFormatter,
                required: true,
                width: 200,
                visible: true,
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
            title: 'Almacenes',
            url: 'almacen/get',
            routes: ROUTES,
            events: EVENTS,
        });

        dialog = new window.Dialog('wrapper', {
            events: EVENTS,
        });

        form = new window.Form(dialog.id, fields, {
            routes: ROUTES,
            events: EVENTS,
        });
    };

    return {
        init: init
    };
})();
app.almacen.init();
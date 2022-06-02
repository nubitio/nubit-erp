var app = app || {};
app.operacion = (function () {
    const ROUTES = Object.freeze({
        ADD: 'operacion/add/',
        EDIT: 'operacion/edit/',
        DELETE: 'operacion/delete/',
        EXPORT: 'operacion/exportar/',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let init = function () {
        let fields = [
            {
                type: window.FormType.HIDDEN,
                name: 'operacion_id',
                isKey: true,
                visible: false
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
                    text: 'INGRESO'
                }, {
                    value: '2',
                    text: 'SALIDA'
                }],
                required: true,
                width: 200,
                formatter: typeFormatter,
                align: 'center',
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
                title: 'Operaciones',
                url: 'operacion/get',
                routes: ROUTES,
            });

        dialog = new window.Dialog('wrapper');

        form = new window.Form(dialog.id, dataGrid.id, dialog, fields);
    };
    return {
        init: init
    };

})();
app.operacion.init();
var app = app || {};
app.customer = (function () {
    const ROUTES = Object.freeze({
        ADD: 'cliente/add/',
        EDIT: 'cliente/edit/',
        DELETE: 'cliente/delete/',
        EXPORT: 'cliente/exportar/',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let init = function () {
        let fields = [
            {
                type: window.FormType.TEXT,
                label: 'RUC/DNI',
                name: 'ruc',
                required: true,
                validators: ['validCustomerId'],
                isKey: true,
                visible: true
            },
            {
                type: window.FormType.TEXTAREA,
                label: 'Cliente',
                name: 'razon_social',
                required: true,
                width: 100,
                visible: true
            },
            {
                type: window.FormType.TEXTAREA,
                label: 'Domicilio',
                name: 'domicilio_fiscal',
                required: false,
                visible: true
            },
            {type: window.FormType.TEXT, label: 'Teléfono', name: 'telefono', required: false, visible: true},
            {type: window.FormType.TEXT, label: 'Correo', name: 'email', required: false, visible: true},
            {
                type: window.FormType.SWITCH,
                label: 'Activo',
                name: 'estado',
                required: true,
                visible: true,
                formatter: activeFormatter,
                defaultValue: true
            },
        ];

        dataGrid = new window.DataGrid('wrapper', fields, {
            title: 'Clientes',
            url: 'cliente/get',
            routes: ROUTES,
        });

        dialog = new window.Dialog('wrapper');

        form = new window.Form(dialog.id, fields);

        let rucField = form.field.ruc.textbox({
            icons: [{
                iconCls: ICON_SEACH,
                handler: function (e) {
                    consult()
                }
            }]
        });

        rucField.textbox('textbox').bind('keyup', function (e) {
            if (e.keyCode === 13) {
                consult()
            }
        });
    };

    function consult() {
        let doc = form.field.ruc.textbox('getValue');
        let apiUrl = ''
        let dialogTitle = ''
        if (doc.length === 11) {
            dialogTitle = 'Consultando RUC';
            apiUrl = 'api/ruc/';
        } else {
            dialogTitle = 'Consultando DNI';
            apiUrl = 'api/dni/'
        }
        dialog.setLoading(true, dialogTitle);
        fetch(apiUrl + doc)
            .then(response => response.json())
            .then(data => {
                    dialog.setLoading(false);
                    if (doc.length === 11) {
                        if (data.estado === 'ACTIVO') {
                            form.field.razon_social.textbox('setValue', data.razonSocial);
                            form.field.domicilio_fiscal.textbox('setValue', data.direccion);
                        } else {
                            show_error('El RUC no está activo');
                        }
                    } else {
                        form.field.razon_social.textbox('setValue', data.nombres + ' ' + data.apellidoPaterno + ' ' + data.apellidoMaterno);
                    }
                }
            )
            .catch(error => {
                dialog.setLoading(false);
                show_error(error.message);
            });
    }

    return {
        init: init
    };
})();
app.customer.init();

var app = app || {};
app.empresa = (function () {
    const ROUTES = Object.freeze({
        ADD: 'empresa/add/',
        EDIT: 'empresa/edit/',
        DELETE: 'empresa/delete/',
        EXPORT: 'empresa/exportar/',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let init = function (){
        let fields = [
            {
                type: window.FormType.TEXT,
                label: 'RUC',
                name: 'ruc',
                required: true,
                validators: ['validCustomerId'],
                isKey: true,
                visible: true
            },
            {
                type: window.FormType.TEXTAREA,
                label: 'Razón Social',
                name: 'razon_social',
                required: true,
                width: 200,
                visible: true
            },
            {
                type: window.FormType.TEXTAREA,
                label: 'Domicilio Fiscal',
                name: 'domicilio_fiscal',
                required: false,
                width: 300,
                visible: true
            },
            {
                type: window.FormType.TEXT,
                label: 'Teléfono',
                name: 'telefono',
                required: false,
                width: 80,
                visible: true
            },
            {
                type: window.FormType.TEXT,
                label: 'Aut. Sunat',
                name: 'aut_sunat',
                required: false,
                width: 150,
                visible: true
            },
            {
                type: window.FormType.SWITCH,
                label: 'Activo',
                name: 'estado',
                required: true,
                visible: true,
                align: 'center',
                formatter: activeFormatter,
                defaultValue: true
            },
        ];

        dataGrid = new window.DataGrid('wrapper', fields, {
            title: 'Empresas',
            url: 'empresa/get',
            routes: ROUTES,
        });

        dialog = new window.Dialog('wrapper');

        form = new window.Form(dialog.id, fields);

        form.field.ruc.textbox('textbox').bind('keyup', function (e) {
            console.log(e);
            if (e.keyCode === 13) {
                consult();
            }
        });

        form.field.ruc.textbox({
            icons: [{
                iconCls: ICON_SEACH,
                handler: function (e) {
                    consult()
                }
            }]
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

app.empresa.init();
var app = app || {};
app.proveedor = (function () {
    let dg = $('#proveedor_dg');
    let txt_ruc = $('#proveedor_ruc');
    let txt_razon_social = $('#proveedor_razon_social');
    let txt_domicilio_fiscal = $('#proveedor_domicilio_fiscal');
    let txt_telefono = $('#proveedor_telefono');
    let cbo_estado = $('#proveedor_estado');
    let enter_pressed = false;

    let dialog = null;
    let form = null;

    const ADD_ROUTE = '/proveedor/add/';
    const EDIT_ROUTE = '/proveedor/edit/';
    const DELETE_ROUTE = '/proveedor/delete/';
    const EXPORT_ROUTE = '/proveedor/export/';
    const ENTITY_ID = 'proveedor_id';

    let init = function () {
        dialog = new Dialog('proveedor_dlg', 'provider:save');
        form = new Form('proveedor_fm', 'proveedor_dg',dialog)

        mainObserver.subscribe('provider:save', () => {
            form.save();
        })

        dg.datagrid({
            title: 'Proveedores',
            url: 'proveedor/get',
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    form.add(ADD_ROUTE);
                    cbo_estado.combobox('select', 1);
                }
            }, '-', {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    form.update(EDIT_ROUTE, ENTITY_ID)
                }
            }, '-', {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    form.delete(DELETE_ROUTE, ENTITY_ID)
                }
            }, '-', {
                text: TEXT_EXPORTAR,
                iconCls: ICON_XLS,
                handler: function () {
                    exportar();
                }
            }],
            columns: [[
                {field: 'ruc', title: 'Ruc', sortable: true, align: 'center'},
                {field: 'razon_social', title: 'Razón Social', sortable: true, width: 200},
                {field: 'domicilio_fiscal', title: 'Domicilio Fiscal', sortable: true, width: 300},
                {field: 'telefono', title: 'Telefono', sortable: true,},
                {
                    field: 'estado',
                    title: 'Activo',
                    sortable: true,
                    align: 'center',
                    formatter: activeFormatter,
                }
            ]],
            onDblClickRow: function () {
                form.update(EDIT_ROUTE,ENTITY_ID);
            }
        });

        dg.datagrid('enableFilter', [{
            field: 'estado',
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: [{
                    value: '',
                    text: 'TODOS'
                }, {
                    value: '1',
                    text: 'ACTIVO'
                }, {
                    value: '2',
                    text: 'INACTIVO'
                }],
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'estado');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'estado',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }]);

        txt_ruc.numberbox({
            width: '100%',
            label: 'RUC',
            labelPosition: 'top',
            min: 0,
            validType: 'length[11,11]',
            invalidMessage: 'Ingrese un RUC valido.',
            icons: [{
                iconCls: ICON_SEACH,
                handler: function (e) {
                    consult();
                }
            }],
            required: true
        });

        txt_ruc.numberbox('textbox').attr('maxlength', '11');

        txt_ruc.textbox('textbox').bind('keydown', function (e) {
            if (e.keyCode === 13 && !enter_pressed) {
                consult();
            }
        });

        txt_razon_social.textbox({
            width: '100%',
            height: 80,
            label: 'Razón social',
            labelPosition: 'top',
            multiline: true,
            required: true
        });

        txt_domicilio_fiscal.textbox({
            width: '100%',
            height: 80,
            label: 'Domicilio fiscal',
            labelPosition: 'top',
            multiline: true
        });

        txt_telefono.numberbox({
            width: '100%',
            label: 'Telefono',
            labelPosition: 'top',
            validType: 'length[6,15]'
        });

        cbo_estado.combobox({
            width: '100%',
            label: 'Estado',
            labelPosition: 'top',
            data: [{
                value: '1',
                text: 'ACTIVO'
            }, {
                value: '2',
                text: 'INACTIVO'
            }],
            panelHeight: 'auto',
            limitToList: true,
            editable: false,
            required: true
        });
    };

    // functions
    function consult() {
        enter_pressed = true;
        dlg.dialog('showMask', 'Consultando RUC');
        $.get('api/ruc/' + txt_ruc.textbox('getValue'))
            .done(function (result) {
                enter_pressed = false;
                if (result.error) {
                    show_error(result.error);
                } else {
                    txt_razon_social.textbox('clear');
                    txt_domicilio_fiscal.textbox('clear');
                    if (result.estado === 'ACTIVO') {
                        txt_razon_social.textbox('setValue', result.razonSocial);
                        txt_domicilio_fiscal.textbox('setValue', result.direccion);
                    } else {
                        show_error(result.razonSocial + '<br>' + result.estado);
                    }
                }
                dlg.dialog('hideMask');
            })
            .fail(function (xhr, status, error) {
                enter_pressed = false;
                dlg.dialog('hideMask');
                show_error(error);
            });
    }

    function exportar() {
        window.open(EXPORT_ROUTE);
    }

    mainObserver.subscribe('proveedores:save', function (data) {
        console.log(data);
    });

    return {
        init: init
    };

})();
app.proveedor.init();

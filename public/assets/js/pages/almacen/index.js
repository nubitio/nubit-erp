var app = app || {};
app.almacen = (function () {
    let route;
    let dg = $('#almacen_dg');
    let dlg = $('#almacen_dlg');
    let fm = $('#almacen_fm');

    let cbo_empresa = $('#almacen_empresa');
    let cbo_local = $('#almacen_local');
    let txt_descripcion = $('#almacen_descripcion');
    let cbo_tipo = $('#almacen_tipo');
    let cbo_estado = $('#almacen_estado');
    let btn_guardar;
    let btn_cancelar;

    let init = function () {
        dg.datagrid({
            title: 'Almacenes',
            url: 'almacen/get',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    nuevo();
                }
            }, '-', {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    editar();
                }
            }, '-', {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    eliminar();
                }
            }, '-', {
                text: TEXT_EXPORTAR,
                iconCls: ICON_XLS,
                handler: function () {
                    exportar();
                }
            }],
            columns: [[{
                field: 'empresa',
                title: 'Empresa',
                sortable: true,
                width: 200
            }, {
                field: 'local',
                title: 'Local',
                sortable: true,
                width: 200
            }, {
                field: 'descripcion',
                title: 'Descripcion',
                sortable: true,
                width: 200
            }, {
                field: 'tipo',
                title: 'Tipo',
                sortable: true,
            }, {
                field: 'estado',
                title: 'Activo',
                sortable: true,
                align: 'center',
                formatter: activeFormatter,
            }]],
            onDblClickRow: function () {
                editar();
            }
        });

        dg.datagrid('enableFilter', []);

        dlg.dialog({
            buttons: [{
                id: 'almacen_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'almacen_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#almacen_guardar');
                btn_cancelar = $('#almacen_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        cbo_empresa.combobox({
            width: '100%',
            label: 'Empresa',
            labelPosition: 'top',
            url: 'empresa/lista/1/0',
            method: 'get',
            valueField: 'empresa_id',
            textField: 'razon_social',
            panelHeight: 200,
            limitToList: true,
            onSelect: function (rec) {
                cbo_local.combobox('reload', 'local/cargar/' + rec.empresa_id);
            },
            required: true
        });

        cbo_local.combobox({
            width: '100%',
            label: 'Local',
            labelPosition: 'top',
            method: 'get',
            valueField: 'local_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        txt_descripcion.textbox({
            width: '100%',
            label: 'Descripcion',
            labelPosition: 'top',
            required: true
        });

        cbo_tipo.combobox({
            width: '100%',
            label: 'Tipo',
            labelPosition: 'top',
            data: [{
                value: '1',
                text: 'INSUMOS'
            }, {
                value: '2',
                text: 'PRODUCTOS'
            }],
            panelHeight: 'auto',
            limitToList: true,
            required: true
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

    //funciones

    function nuevo() {
        fm.form('clear');
        route = 'almacen/add';
        cbo_estado.combobox('select', 1);
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        let row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'almacen/edit/' + row.almacen_id;
        } else {
            show_info('Seleccione un registro');
        }
    }

    function guardar() {
        fm.form('submit', {
            url: route,
            onSubmit: function () {
                valid = fm.form('validate');
                if (valid) {
                    disable_buttons();
                }
                return valid;
            },
            success: function (result) {
                try {
                    result = JSON.parse(result);
                    if (result.success) {
                        dlg.dialog('close');
                        dg.datagrid('reload');
                        show_info(result.msg);
                    } else {
                        show_error(result.msg);
                        show_info('Ya existe el Almacen Ingresado ...');
                    }
                    enable_buttons();
                } catch (e) {
                    show_error(e);
                    enable_buttons();
                }
            }
        });
    }

    function disable_buttons() {
        btn_cancelar.linkbutton('disable');
        btn_guardar.linkbutton('disable');
        btn_guardar.linkbutton({text: 'Guardando...'});
        dlg.dialog('showMask', 'Procesando...');
    }

    function enable_buttons() {
        btn_cancelar.linkbutton('enable');
        btn_guardar.linkbutton('enable');
        btn_guardar.linkbutton({text: 'Guardar'});
        dlg.dialog('hideMask');
    }

    function eliminar() {
        let row = dg.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', '¿Está seguro que desea eliminar este registro?', function (r) {
                if (r) {
                    fetch('almacen/delete/' + row.almacen_id, {
                        method: 'POST'
                    }).then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                dg.datagrid('reload');
                            }
                            show_info(result.msg)
                        })
                        .catch(err => show_error(err));
                }
            });
        } else {
            show_info('Seleccione un registro');
        }
    }

    function exportar() {
        window.open('almacen/exportar');
    }

    return {
        init: init
    };
})();
app.almacen.init();

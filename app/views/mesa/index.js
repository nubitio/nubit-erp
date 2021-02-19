var app = app || {};
app.mesa = (function () {
    var route;
    var dg = $('#mesa_dg');
    //var panel = document.getElementById("mesa_panel");
    var dlg = $('#mesa_dlg');
    var fm = $('#mesa_fm');
    var cbo_empresa = $('#mesa_empresa');
    var cbo_local = $('#mesa_local');
    var cbo_ambiente = $('#mesa_ambiente');
    var txt_descripcion = $('#mesa_descripcion');
    var txt_position = $('#mesa_position');
    var cbo_estado = $('#mesa_estado');
    var btn_guardar;
    var btn_cancelar;

    var init = function () {
        /*dg.datagrid({
            title: 'Mesas',
            url: 'mesa/get',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    mesa_nuevo();
                }
            }, '-', {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    mesa_editar();
                }
            }, '-', {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    mesa_eliminar();
                }
            }, '-', {
                text: TEXT_EXPORTAR,
                iconCls: ICON_XLS,
                handler: function () {
                    mesa_exportar();
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
                field: 'ambiente',
                title: 'Ambiente',
                sortable: true,
                width: 200
            }, {
                field: 'descripcion',
                title: 'Descripcion',
                sortable: true,
                width: 300
            }, {
                field: 'estado',
                title: 'Estado',
                sortable: true,
                width: 100,
                align: 'center',
                formatter: estado_formatter,
                styler: estado_styler
            }]],
            onDblClickRow: function () {
                mesa_editar();
            }
        });

        dg.datagrid('enableFilter', [{
            field: 'empresa',
            type: 'combobox',
            options: {
                url: 'empresa/lista/1/1',
                method: 'get',
                valueField: 'empresa_id',
                textField: 'razon_social',
                panelHeight: 200,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'empresa_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'empresa_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
            field: 'local',
            type: 'combobox',
            options: {
                url: 'local/lista/1/1',
                method: 'get',
                valueField: 'local_id',
                textField: 'descripcion',
                panelHeight: 200,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'local_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'local_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
            field: 'ambiente',
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                url: 'ambiente/lista/1/1',
                method: 'get',
                valueField: 'ambiente_id',
                textField: 'descripcion',
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'ambiente_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'ambiente_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
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
        }]);*/

        let diagram = $("#table_map").dxDiagram({
            simpleView: true,
            editing: {
                allowChangeConnection: false
            },
            viewToolbar: {
                visible: false
            },
            toolbox: {
                shapeIconsPerRow: 3,
                groups: [
                    {title: "Mesas", shapes: ["rectangle", "ellipse", "diamond"]}
                ],
                showSearch: false
            },
            contextMenu: {
                enabled: false,
                commands: ["fontColor", "lineColor", "fillColor"]
            },
            contextToolbox: {
                enabled: false
            },
            propertiesPanel: {
                visibility: 'disabled'
            }
        }).dxDiagram("instance");

        dlg.dialog({
            buttons: [{
                id: 'mesa_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'mesa_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    mesa_guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#mesa_guardar');
                btn_cancelar = $('#mesa_cancelar');
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
            onSelect: function (rec) {
                cbo_ambiente.combobox('reload', 'ambiente/cargar/' + rec.local_id);
            },
            required: true

        });

        cbo_ambiente.combobox({
            width: '100%',
            label: 'Ambiente',
            labelPosition: 'top',
            method: 'get',
            valueField: 'ambiente_id',
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

        txt_position.numberbox({
            width: '100%',
            label: 'Prioridad',
            labelPosition: 'top',
            min: 0,
            precision: 0,
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

    function mesa_nuevo() {
        fm.form('clear');
        route = 'mesa/add';
        cbo_estado.combobox('select', 1);
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function mesa_editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'mesa/edit/' + row.mesa_id;
        } else {
            show_info('Seleccione un registro');
        }
    }

    function mesa_guardar() {
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
                    }
                    enable_buttons();
                } catch (e) {
                    show_error(e);
                    enable_buttons();
                }
            }
        });
    }

    function mesa_eliminar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', '¿Está seguro que desea eliminar este registro?', function (r) {
                if (r) {
                    $.post('mesa/delete/' + row.mesa_id)
                        .done(function (result) {
                            if (result.success) {
                                dg.datagrid('reload');
                                show_info(result.msg);
                            } else {
                                show_error(result.msg);
                            }
                        })
                        .fail(function (xhr, status, error) {
                            show_error(error);
                        });
                }
            });
        } else {
            show_info('Seleccione un registro');
        }
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

    function mesa_exportar() {
        window.open('mesa/exportar');
    }

    var main = function () {
        // Defines an icon for creating new connections in the connection handler.
        // This will automatically disable the highlighting of the source vertex.
        mxConnectionHandler.prototype.connectImage = new mxImage('images/connector.gif', 16, 16);

        // Checks if browser is supported
        if (!mxClient.isBrowserSupported()) {
            // Displays an error message if the browser is
            // not supported.
            mxUtils.error('Browser is not supported!', 200, false);
        } else {
            // Creates the div for the toolbar
            var tbContainer = document.createElement('div');
            tbContainer.style.position = 'absolute';
            tbContainer.style.overflow = 'hidden';
            tbContainer.style.padding = '2px';
            tbContainer.style.left = '0px';
            tbContainer.style.top = '26px';
            tbContainer.style.width = '24px';
            tbContainer.style.bottom = '0px';

            panel.appendChild(tbContainer);

            // Creates new toolbar without event processing
            var toolbar = new mxToolbar(tbContainer);
            toolbar.enabled = false

            // Creates the div for the graph
            container = document.createElement('div');
            container.style.position = 'absolute';
            container.style.overflow = 'hidden';
            container.style.left = '24px';
            container.style.top = '26px';
            container.style.right = '0px';
            container.style.bottom = '0px';
            container.style.background = 'url("app/assets/js/mxgraph/images/grid.gif")';

            panel.appendChild(container);

            // Workaround for Internet Explorer ignoring certain styles
            if (mxClient.IS_QUIRKS) {
                panel.style.overflow = 'hidden';
                new mxDivResizer(tbContainer);
                new mxDivResizer(container);
            }

            // Creates the model and the graph inside the container
            // using the fastest rendering available on the browser
            var model = new mxGraphModel();
            var graph = new mxGraph(container, model);
            graph.dropEnabled = true;

            // Matches DnD inside the graph
            mxDragSource.prototype.getDropTarget = function (graph, x, y) {
                var cell = graph.getCellAt(x, y);

                if (!graph.isValidDropTarget(cell)) {
                    cell = null;
                }

                return cell;
            };

            // Enables new connections in the graph
            graph.setConnectable(true);
            graph.setMultigraph(false);

            // Stops editing on enter or escape keypress
            var keyHandler = new mxKeyHandler(graph);
            var rubberband = new mxRubberband(graph);

            var addVertex = function (icon, w, h, style) {
                var vertex = new mxCell(null, new mxGeometry(0, 0, w, h), style);
                vertex.setVertex(true);

                addToolbarItem(graph, toolbar, vertex, icon);
            };

            addVertex('editors/images/swimlane.gif', 120, 160, 'shape=swimlane;startSize=20;');
            addVertex('editors/images/rectangle.gif', 100, 40, '');
            addVertex('editors/images/rounded.gif', 100, 40, 'shape=rounded');
            addVertex('editors/images/ellipse.gif', 40, 40, 'shape=ellipse');
            addVertex('editors/images/rhombus.gif', 40, 40, 'shape=rhombus');
            addVertex('editors/images/triangle.gif', 40, 40, 'shape=triangle');
            addVertex('editors/images/cylinder.gif', 40, 40, 'shape=cylinder');
            addVertex('editors/images/actor.gif', 30, 40, 'shape=actor');
            toolbar.addLine();
        }
    };

    function addToolbarItem(graph, toolbar, prototype, image) {
        // Function that is executed when the image is dropped on
        // the graph. The cell argument points to the cell under
        // the mousepointer if there is one.
        var funct = function (graph, evt, cell) {
            graph.stopEditing(false);

            var pt = graph.getPointForEvent(evt);
            var vertex = graph.getModel().cloneCell(prototype);
            vertex.geometry.x = pt.x;
            vertex.geometry.y = pt.y;

            graph.setSelectionCells(graph.importCells([vertex], 0, 0, cell));
        };

        // Creates the image which is used as the drag icon (preview)
        var img = toolbar.addMode(null, image, funct);
        mxUtils.makeDraggable(img, graph, funct);
    }

    return {
        init: init,
        main: main
    };
})();
app.mesa.init();
//app.mesa.main();
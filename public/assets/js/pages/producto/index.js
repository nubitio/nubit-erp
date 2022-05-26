var app = app || {};
app.producto = (function () {
    let dg = $('#producto_dg');
    let txt_imagen = $('#producto_imagen');
    let txt_codigo = $('#producto_codigo');
    let txt_descripcion = $('#producto_descripcion');
    let cbo_categoria = $('#producto_categoria');
    let txt_precio = $('#producto_precio');
    let cbo_estado = $('#producto_estado');
    let dialog = null;
    let form = null;

    const ADD_ROUTE = '/producto/add/';
    const EDIT_ROUTE = '/producto/edit/';
    const DELETE_ROUTE = '/producto/delete/';
    const EXPORT_ROUTE = '/producto/export/';
    const ENTITY_ID = 'producto_id';

    let init = function () {
        dialog = new Dialog('producto_dlg', 'product:save');
        form = new Form('producto_fm', 'producto_dg', dialog);

        mainObserver.subscribe('product:save', () => {
            form.save();
        });

        dg.datagrid({
            title: 'Productos',
            url: 'producto/get',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    form.add(ADD_ROUTE);
                    cbo_estado.switchbutton('check');
                },
            }, '-', {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    form.update(EDIT_ROUTE, ENTITY_ID);
                },
            }, '-', {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    form.delete(DELETE_ROUTE, ENTITY_ID);
                },
            }, '-', {
                text: TEXT_EXPORTAR,
                iconCls: ICON_XLS,
                handler: function () {
                    exportar();
                }
            }],
            columns: [[{
                field: 'codigo',
                title: 'Código',
                sortable: true,
                width: 100
            }, {
                field: 'descripcion',
                title: 'Nombre',
                sortable: true,
                width: 200
            }, {
                field: 'image_path',
                title: 'Imagen',
                sortable: false,
                align: 'center',
                width: 50,
                formatter: imageFormatter,
            }, {
                field: 'categoria',
                title: 'Categoria',
                sortable: true,
                width: 180
            }, {
                field: 'precio',
                title: 'Precio',
                sortable: true,
                width: 80,
                align: 'right'
            }, {
                field: 'estado',
                title: 'Activo',
                sortable: true,
                align: 'center',
                formatter: activeFormatter,
            }]],
            onDblClickRow: function () {
                form.update(EDIT_ROUTE, ENTITY_ID);
            }
        });

        dg.datagrid('enableFilter', []);

        txt_imagen.filebox({
            width: '100%',
            label: 'Imagen',
            labelPosition: 'top',
            buttonText: 'Seleccionar',
        });

        txt_codigo.textbox({
            width: '100%',
            label: 'Código',
            labelPosition: 'top',
        });

        txt_descripcion.textbox({
            width: '100%',
            label: 'Nombre',
            labelPosition: 'top',
            required: true
        });

        cbo_categoria.combobox({
            width: '100%',
            label: 'Categoria',
            labelPosition: 'top',
            url: 'categoria/lista/1',
            method: 'get',
            valueField: 'categoria_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        txt_precio.numberbox({
            width: '100%',
            label: 'Precio',
            labelPosition: 'top',
            min: 0,
            precision: 2,
            required: true
        });

        cbo_estado.switchbutton({
            label: "Activo",
            labelPosition: "top",
            onText: "SI",
            offText: "NO",
            value: "1",
            required: true
        });
    };

    function exportar() {
        window.open(EXPORT_ROUTE);
    }

    return {
        init: init
    };
})();
app.producto.init();

var app = app || {};
app.producto = (function () {
    let dataGrid = null;
    let dialog = null;
    let form = null;

    const ADD_ROUTE = 'producto/add/';
    const EDIT_ROUTE = 'producto/edit/';
    const DELETE_ROUTE = 'producto/delete/';
    const EXPORT_ROUTE = 'producto/exportar/';
    const ENTITY_ID = 'producto_id';

    let init = async function () {
        let categories = await loadData('categoria/lista/1', 'categoria_id', 'descripcion');

        const formFields = [
            {
                type: FORM_TYPE.FILE,
                label: 'Imagen',
                name: 'imagen',
                required: false,
            },
            {type: FORM_TYPE.TEXT, label: 'Código', name: 'codigo', required: false, visible: true},
            {
                type: FORM_TYPE.TEXT,
                label: 'Descripción',
                name: 'descripcion',
                required: true,
                width: 100,
                visible: true
            },
            {type: FORM_TYPE.HIDDEN, label: 'Imagen', name: 'image_path', visible: true, formatter: imageFormatter},
            {
                type: FORM_TYPE.SELECT,
                label: 'Categoría',
                name: 'categoria_id',
                data: categories,
                required: true,
                visible: false
            },
            {type: FORM_TYPE.HIDDEN, label: 'Categoría', name: 'categoria', visible: true},
            {type: FORM_TYPE.NUMBER, label: 'Precio', name: 'precio', required: true, visible: true},
            {
                type: FORM_TYPE.SWITCH,
                label: 'Activo',
                name: 'estado',
                required: true,
                visible: true,
                formatter: activeFormatter
            },
        ];

        dataGrid = new DataGrid('wrapper', formFields, {
            title: 'Productos',
            url: 'producto/get',
        });

        dialog = new Dialog('wrapper');

        form = new Form(dialog.id, dataGrid.id, dialog, formFields);

        mainObserver.subscribe('on:add', () => {
            form.add(ADD_ROUTE, ENTITY_ID)
            form.field.estado.switchbutton('check');
        });
        mainObserver.subscribe('on:edit', () => {
            form.edit(EDIT_ROUTE, ENTITY_ID)
        });
        mainObserver.subscribe('on:save', () => {
            form.save();
        });
        mainObserver.subscribe('on:delete', () => {
            form.delete(DELETE_ROUTE, ENTITY_ID);
        });
        mainObserver.subscribe('on:export', () => {
            window.open(EXPORT_ROUTE);
        });
    };

    return {
        init: init
    };
})();
app.producto.init();

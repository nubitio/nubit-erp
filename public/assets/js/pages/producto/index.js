var app = app || {};
app.producto = (function () {
    const ROUTES = Object.freeze({
        ADD: 'producto/add/',
        EDIT: 'producto/edit/',
        DELETE: 'producto/delete/',
        EXPORT: 'producto/exportar/',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let init = async function () {
        let categories = await loadData('categoria/lista/1', 'categoria_id', 'descripcion');

        const formFields = [
            {
                type: window.FormType.HIDDEN,
                name: 'producto_id',
                isKey: true,
                visible: false
            },
            {
                type: window.FormType.FILE,
                label: 'Imagen',
                name: 'imagen',
                required: false,
            },
            {type: window.FormType.TEXT, label: 'Código', name: 'codigo', required: false, visible: true},
            {
                type: window.FormType.TEXT,
                label: 'Descripción',
                name: 'descripcion',
                required: true,
                width: 100,
                visible: true
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Imagen',
                name: 'image_path',
                visible: true,
                formatter: imageFormatter
            },
            {
                type: window.FormType.SELECT,
                label: 'Categoría',
                name: 'categoria_id',
                data: categories,
                required: true,
                visible: false
            },
            {type: window.FormType.HIDDEN, label: 'Categoría', name: 'categoria', visible: true},
            {type: window.FormType.NUMBER, label: 'Precio', name: 'precio', required: true, visible: true},
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

        dataGrid = new window.DataGrid('wrapper', formFields, {
            title: 'Productos',
            url: 'producto/get',
            routes: ROUTES,
        });

        dialog = new window.Dialog('wrapper');

        form = new window.Form(dialog.id, dataGrid.id, dialog, formFields);
    };

    return {
        init: init
    };
})();
app.producto.init();

var app = app || {};
app.categoria = (function () {
    const ROUTES = Object.freeze({
        ADD: 'categoria/add/',
        EDIT: 'categoria/edit/',
        DELETE: 'categoria/delete/',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let init = async function () {
        let categoriaPadre = await loadData('categoria/lista/0', 'categoria_id', 'descripcion');

        let fields = [
            {
                type: window.FormType.HIDDEN,
                name: 'categoria_id',
                isKey: true,
                visible: false
            },
            {
                type: window.FormType.TEXT,
                label: 'Categoria',
                name: 'descripcion',
                required: true,
                width: 200,
                visible: true
            },
            {
                type: window.FormType.SELECT,
                label: 'Padre',
                name: 'parent_id',
                data: categoriaPadre,
                visible: false
            },
            {
                type: window.FormType.FILE,
                label: 'Imagen',
                name: 'imagen',
                required: false
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Padre',
                name: 'padre',
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

        dataGrid = new window.DataGrid('wrapper', fields, {
            title: 'Categorias',
            url: 'categoria/get',
            routes: ROUTES,
        });

        dialog = new window.Dialog('wrapper');

        form = new window.Form(dialog.id, fields);
    };

    return {
        init: init
    };
})();
app.categoria.init();

var app = app || {};
app.options = (function () {
    const ROUTES = Object.freeze({
        ADD: 'option/add/',
        EDIT: 'option/edit/',
        DELETE: 'option/delete/',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let init = function () {
        let fields = [
            {
                type: window.FormType.HIDDEN,
                label: 'Id',
                name: 'id',
                isKey: true,
                visible: true
            },
            {
                type: window.FormType.TEXT,
                label: 'Name',
                name: 'name',
                required: true,
                visible: true
            },
            {
                type: window.FormType.TEXT,
                label: 'Value',
                name: 'value',
                required: true,
                width: 200,
                visible: true
            }
        ];

        dataGrid = new window.DataGrid('wrapper', fields,
            {
                title: 'Options',
                url: 'option/get',
                routes: ROUTES,
            });

        dialog = new window.Dialog('wrapper');

        form = new window.Form(dialog.id, fields);
    };

    return {
        init: init
    };
})();
app.options.init();

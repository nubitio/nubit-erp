var app = app || {};
app.options = (function () {
    const ROUTES = Object.freeze({
        ADD: 'option/add/',
        EDIT: 'option/edit/',
        DELETE: 'option/delete/',
    });

    const EVENTS = Object.freeze({
        ADD: 'option:add',
        EDIT: 'option:edit',
        DELETE: 'option:delete',
        SAVE: 'option:save',
        CANCEL: 'option:cancel',
        SUCCESS: 'option:success',
        LOADING: 'option:loading',
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
                isIdentity: true,
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

        dataGrid = new window.DataGrid('wrapper', fields, {
            title: 'Options',
            url: 'option/get',
            routes: ROUTES,
            events: EVENTS,
        });

        dialog = new window.Dialog('wrapper', {
            events: EVENTS,
        });

        form = new window.Form(dialog.id, fields, {
            routes: ROUTES,
            events: EVENTS,
        });
    };

    return {
        init: init
    };
})();
app.options.init();

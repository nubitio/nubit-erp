var app = app || {};
app.usuario = (function () {
    const ROUTES = Object.freeze({
        ADD: 'usuario/add/',
        EDIT: 'usuario/edit/',
        DELETE: 'usuario/delete/',
    });

    const EVENTS = Object.freeze({
        ADD: 'user:add',
        EDIT: 'user:edit',
        DELETE: 'user:delete',
        SAVE: 'user:save',
        CANCEL: 'user:cancel',
        SUCCESS: 'user:success',
        LOADING: 'user:loading',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let init = async function () {
        let rolName = await loadData('rol/lista/1/0', 'rol_id', 'descripcion');

        let fields = [
            {
                type: window.FormType.HIDDEN,
                name: 'usuario_id',
                isIdentity: true,
                visible: false
            },
            {
                type: window.FormType.TEXT,
                label: 'Usuario',
                name: 'username',
                visible: true,
                required: true,
                width: 200
            },
            {
                type: window.FormType.TEXT,
                label: 'Contraseña',
                name: 'password',
                required: true,
            },
            {
                type: window.FormType.SELECT,
                label: 'RolController',
                name: 'rol_id',
                data: rolName,
                required: true,
                visible: false
            },
            {
                type: window.FormType.HIDDEN,
                label: 'RolController',
                name: 'rol',
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
            title: 'Usuarios',
            url: 'usuario/get',
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
app.usuario.init();
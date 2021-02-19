var app = app || {};
var accounting = app.accounting || {}
accounting.summary = (function () {
    let type = 0;
    let dlg = $('#dlgSummary');
    let btnCancel;

    let dg = $('#dgSummary');

    let init = function () {
        dg.datagrid({
            pagination: false,
            toolbar: [{
                text: 'Enviar Resumen',
                iconCls: 'fas fa-paper-plane fa-1x text-warning',
                handler: function () {
                    sendSummary();
                }
            }],
            columns: [[{
                field: 'fecha',
                title: 'Fecha',
                width: 100,
                align: 'center'
            }, {
                field: 'comprobantes',
                title: 'Comprobantes',
                align: 'center'
            }]]
        })

        dlg.dialog({
            height: 500,
            buttons: [{
                id: 'btnCancel',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btnCancel = $('#btnCancel');
                btnCancel.addClass('md-btn-plain');
            }
        })
    }

    function open(value) {
        dlg.dialog('open').dialog('setTitle', 'Pendientes de envio');
        type = value
        loadData()
    }

    function loadData() {
        dlg.dialog('showMask', 'Procesando...')
        fetch('invoice/summary/get/' + type)
            .then(response => response.json())
            .then(result => dg.datagrid('loadData', result.data))
            .catch(error => show_error(error))
            .finally(() => dlg.dialog('hideMask'));
    }

    function sendSummary() {
        let row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('showMask', 'Procesando...');
            fetch('invoice/summary/send/' + type + '/' + row.fecha)
                .then(response => response.json())
                .then(result => (result.success) ? show_info(result.msg) : show_error(result.msg))
                .catch(error => show_error(error))
                .finally(() => {
                    dlg.dialog('hideMask');
                    dlg.dialog('close');
                })
        } else {
            show_info('Seleccione un registro');
        }
    }

    return {
        init: init,
        open: open
    }
})();

accounting.summary.init();

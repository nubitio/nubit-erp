var app = app || {};
app.reporte = (function () {
    var route;
    var dg = $('#reporte_dg');
    var db_fecha_inicio = $('#reporte_fecha_inicio');
    var db_fecha_fin = $('#reporte_fecha_fin');

    var init = function () {
        dg.datagrid({
            title: 'Consulta ventas',
            toolbar: '#reporte_toolbar',
            pagination: false,
            rownumbers: ROW_NUMBERS,
            fitColumns: true,
            fit: true,
            border: BORDER,
            singleSelect: SINGLE_SELECT,
            multiSort: true,
            columns: [[]],
        });


        db_fecha_inicio.datebox({
            prompt: 'Fecha inicio',
            formatter: dateformatter,
            parser: dateparser,
            validType: 'validDate',
            required: true,
            icons: [{
                iconCls: ICON_CLEAR,
                handler: function (e) {
                    $(e.data.target).datebox('clear');
                }
            }]
        });

        db_fecha_fin.datebox({
            prompt: 'Fecha fin',
            formatter: dateformatter,
            parser: dateparser,
            validType: 'validDate',
            required: true,
            icons: [{
                iconCls: ICON_CLEAR,
                handler: function (e) {
                    $(e.data.target).datebox('clear');
                }
            }]
        });
    };

    var rpt_articulo = function reporte_articulo() {
        var fi = db_fecha_inicio.datebox('getValue');
        var ff = db_fecha_fin.datebox('getValue');
        if (fi && ff) {
            window.open('reporte/articulo/' + fi + '/' + ff);
        } else {
            show_info('Ingrese las Fechas');
        }
    };

    var rpt_detallado = function reporte_detallado() {
        var fi = db_fecha_inicio.datebox('getValue');
        var ff = db_fecha_fin.datebox('getValue');
        if (fi && ff) {
            window.open('reporte/detallado/' + fi + '/' + ff);
        } else {
            show_info('Ingrese las Fechas');
        }
    };

    var rpt_dia = function reporte_dia() {
        var fi = db_fecha_inicio.datebox('getValue');
        if (fi) {
            window.open('reporte/dia/' + fi);
        } else {
            show_info('Ingrese las Fechas');
        }
    };

    var rpt_notaventa = function reporte_notaventa() {
        var fi = db_fecha_inicio.datebox('getValue');
        var ff = db_fecha_fin.datebox('getValue');
        if (fi && ff) {
            window.open('reporte/notaventa/' + fi + '/' + ff);
        } else {
            show_info('Ingrese las Fechas');
        }
    };

    var rpt_contable = function reporte_contable() {
        var fi = db_fecha_inicio.datebox('getValue');
        var ff = db_fecha_fin.datebox('getValue');
        if (fi && ff) {
            window.open('reporte/contable/' + fi + '/' + ff);
        } else {
            show_info('Ingrese las Fechas');
        }
    };

    var rpt_aeropuerto = function reporte_aeropuerto() {
        var fecha = db_fecha_inicio.datebox('getValue');
        var ff = db_fecha_fin.datebox('getValue');
        if (fecha && ff) {
            window.open('reporte/aeropuerto/' + fecha);
        } else {
            show_info('Ingrese las Fechas');
        }
    };

    let rpt_ventas_anuladas = function () {
        var fi = db_fecha_inicio.datebox('getValue');
        var ff = db_fecha_fin.datebox('getValue');
        if (fi && ff) {
            window.open('reporte/anulados/' + fi + '/' + ff);
        } else {
            show_info('Ingrese las Fechas');
        }
    };

    let rpt_notas_de_credito = function () {
        var fi = db_fecha_inicio.datebox('getValue');
        var ff = db_fecha_fin.datebox('getValue');
        if (fi && ff) {
            window.open('reporte/notasCredito/' + fi + '/' + ff);
        } else {
            show_info('Ingrese las Fechas');
        }
    };

    return {
        init: init,
        rpt_articulo: rpt_articulo,
        rpt_detallado: rpt_detallado,
        rpt_dia: rpt_dia,
        rpt_notaventa: rpt_notaventa,
        rpt_contable: rpt_contable,
        rpt_aeropuerto: rpt_aeropuerto,
        rpt_ventas_anuladas: rpt_ventas_anuladas,
        rpt_notas_de_credito: rpt_notas_de_credito
    };

})();
app.reporte.init();


var app = app || {};
app.dashboard = (function () {
    let lapseSelector = $('#lapse-selector');
    let chartMostSold = $('#most-sold');
    let dgLessSold = $('#less-sold');
    let dgWithoutRotation = $('#without-rotation');

    let init = function () {
        lapseSelector.combobox({
            data: [{
                value: 'week',
                text: 'Semana'
            }, {
                value: 'month',
                text: 'Mes'
            }, {
                value: 'year',
                text: 'Año'
            }],
            onSelect: function (record) {
                getRotationInfo(record.value);
            },
            panelHeight: 'auto',
            editable: false
        });

        dgWithoutRotation.datagrid({
            title: 'Productos sin rotación',
            pagination: false,
            border: true,
            cls: 'card-panel',
            minHeight: 350,
            columns: [[
                {field: 'producto', title: 'Producto', width: 200, sortable: true},
                {field: 'cantidad', title: 'Cant', align: 'right', sortable: true}
            ]]
        });

        lapseSelector.combobox('select', 'week');
    };

    function drawMostSold(data) {
        Highcharts.chart('most-sold', {
            chart: {
                type: 'lollipop',
                inverted: true
            },
            legend: {
                enabled: false
            },
            title: {
                text: 'Top 10 más vendidos'
            },
            credits: {
                enabled: false
            },
            tooltip: {
                shared: true
            },
            xAxis: {
                type: 'category'
            },

            yAxis: {
                title: {
                    text: 'Cantidad'
                }
            },
            series: [{
                name: 'Cantidad',
                colorByPoint: true,
                data: data
            }]
        });
    }

    function drawLessSold(data) {
        Highcharts.chart('less-sold', {
            chart: {
                type: 'lollipop',
                inverted: true
            },
            legend: {
                enabled: false
            },
            title: {
                text: 'Top 10 menos vendidos'
            },
            credits: {
                enabled: false
            },
            tooltip: {
                shared: true
            },
            xAxis: {
                type: 'category'
            },

            yAxis: {
                title: {
                    text: 'Cantidad'
                }
            },
            series: [{
                name: 'Cantidad',
                colorByPoint: true,
                data: data
            }]
        });
    }

    function getRotationInfo(lapse) {
        fetch('bi/product-rotation/' + lapse)
            .then(response => response.json())
            .then(result => {
                let reversed = result.slice().reverse();
                let mostSold = reversed.slice(0, 10);
                let lessSold = result.filter(item => Number(item.cantidad) > 0).slice(0, 10);
                let withoutRotation = result.filter(item => Number(item.cantidad) === 0);
                let mostSoldData = [];
                mostSold.forEach((obj) => {
                        let item = {
                            name: obj.producto,
                            low: Number(obj.cantidad)
                        };
                        mostSoldData.push(item);
                    }
                );
                let lessSoldData = [];
                lessSold.forEach((obj) => {
                        let item = {
                            name: obj.producto,
                            low: Number(obj.cantidad)
                        };
                        lessSoldData.push(item);
                    }
                );
                drawMostSold(mostSoldData);
                drawLessSold(lessSoldData);
                dgWithoutRotation.datagrid('loadData', withoutRotation);
            })
            .catch(err => show_error(err))
    }

    function draw() {
        // venta semana
        $.get('bi/venta-semana', function (result) {
            var dias = [];
            var data = [];
            for (var i = 0; i < result.length; i++) {
                dias.push(result[i].dia);
                data.push(parseFloat(result[i].venta));
            }
            Highcharts.chart('venta_semana', {
                title: {
                    text: 'Ventas por semana'
                },
                subtitle: {
                    text: ''
                },
                xAxis: {
                    categories: dias
                },
                yAxis: {
                    title: {
                        text: 'Monto en soles'
                    }
                },
                credits: {
                    enabled: false
                },
                series: [{
                    name: 'Total',
                    type: 'column',
                    colorByPoint: true,
                    data: data,
                    showInLegend: false
                }]
            });
        });

        $.get('bi/venta-mes', function (result) {
            let months = [];
            let data = [];
            for (var i = 0; i < result.length; i++) {
                months.push(result[i].mes);
                data.push(parseFloat(result[i].venta));
            }
            Highcharts.chart('venta_mes', {
                title: {
                    text: 'Ventas por mes'
                },
                subtitle: {
                    text: ''
                },
                xAxis: {
                    categories: months
                },
                yAxis: {
                    title: {
                        text: 'Monto en soles'
                    }
                },
                credits: {
                    enabled: false
                },
                series: [{
                    name: 'Total',
                    type: 'column',
                    colorByPoint: true,
                    data: data,
                    showInLegend: false
                }]
            });
        });

        // venta dia
        $.get('bi/venta-dia', function (result) {
            var dias = [];
            var detail = [];
            var data = [];
            for (var i = 0; i < result.length; i++) {
                dias.push(result[i].dia);
                detail.push(parseFloat(result[i].venta));
            }
            data.push({"name": 'Venta', "data": detail});
            Highcharts.chart('venta_dia', {
                chart: {
                    type: 'spline'
                },
                title: {
                    text: 'Venta por día'
                },
                xAxis: {
                    categories: dias
                },
                yAxis: {
                    title: {
                        text: 'Monto en soles'
                    }
                },
                tooltip: {
                    shared: true,
                    valueSuffix: ''
                },
                credits: {
                    enabled: false
                },
                plotOptions: {
                    line: {
                        dataLabels: {
                            enabled: true
                        },
                        enableMouseTracking: false
                    }
                },
                series: data
            });
        });

        // monthly invoicing
        $.get('bi/monthly-invoicing', function (result) {
            let months = [];
            let data = [];
            for (var i = 0; i < result.length; i++) {
                months.push(result[i].month);
                data.push(parseFloat(result[i].total));
            }
            Highcharts.chart('monthly_invoicing', {
                title: {
                    text: 'Facturación por mes'
                },
                subtitle: {
                    text: ''
                },
                xAxis: {
                    categories: months
                },
                yAxis: {
                    title: {
                        text: 'Comprobantes'
                    }
                },
                credits: {
                    enabled: false
                },
                series: [{
                    name: 'Comprobantes',
                    type: 'column',
                    colorByPoint: true,
                    data: data,
                    showInLegend: false
                }]
            });
        });
    }

    return {
        init: init,
        draw: draw
    }
})();
app.dashboard.init();
app.dashboard.draw();

$('.wrapper').animate({"opacity": 1});

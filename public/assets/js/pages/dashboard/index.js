var app = app || {};
app.dashboard = (function () {
    let lapseSelector = $('#lapse-selector');
    let dgWithoutRotation = $('#without-rotation');

    let chartTheme = null;

    let mostSoldProductsData = null;
    let lessSoldProductsData = null;
    let totalSalesPerDayOfCurrentWeekData = null;
    let totalSalesPerDayOfCurrentMonthData = null;
    let totalSalesPerMonthData = null;
    let invoiceCountPerMonthData = null;
    let dataLoaded = false;
    let rotationDataLoaded = false;

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
            cls: 'card-panel',
            minHeight: 350,
            columns: [[
                {field: 'producto', title: 'Producto', width: 200},
                {field: 'cantidad', title: 'Cant', align: 'right'}
            ]]
        });

        setupTheme();
        lapseSelector.combobox('select', 'week');
        fetchData();

        window.observer.subscribe(window.EventType.THEME_CHANGE, () => {
            setupTheme();

            if (dataLoaded) {
                drawCharts();
            } else {
                fetchData();
            }

            if (rotationDataLoaded) {
                drawRotationCharts();
            } else {
                getRotationInfo('week');
            }
        });

        window.observer.subscribe('on:rotation-data:loaded', () => {
            drawRotationCharts();
        });

        window.observer.subscribe('on:bi-data:loaded', () => {
            drawCharts();
        });

        window.setInterval(() => {
            fetchData();
        }, 60000);
    };

    function setupTheme() {
        let bgColor = getComputedStyle(document.body).getPropertyValue('--background-color');
        let primaryColor = getComputedStyle(document.body).getPropertyValue('--primary-color');
        let borderColor = getComputedStyle(document.body).getPropertyValue('--border-color');
        let textColor = getComputedStyle(document.body).getPropertyValue('--text-color');

        chartTheme = {
            colors: ['#2b908f', '#90ee7e', '#f45b5b', '#7798BF', '#aaeeee', '#ff0066',
                '#eeaaee', '#55BF3B', '#DF5353', '#7798BF', '#aaeeee'],
            chart: {
                backgroundColor: bgColor,
                plotBorderColor: borderColor,
            },
            title: {
                style: {
                    color: textColor,
                }
            },
            subtitle: {
                style: {
                    color: textColor,
                }
            },
            xAxis: {
                gridLineColor: borderColor,
                labels: {
                    style: {
                        color: textColor
                    }
                },
                lineColor: borderColor,
                minorGridLineColor: borderColor,
                tickColor: borderColor,
                title: {
                    style: {
                        color: textColor
                    }
                }
            },
            yAxis: {
                gridLineColor: borderColor,
                labels: {
                    style: {
                        color: textColor
                    }
                },
                lineColor: borderColor,
                minorGridLineColor: borderColor,
                tickColor: borderColor,
                title: {
                    style: {
                        color: textColor
                    }
                }
            },
            tooltip: {
                backgroundColor: bgColor,
                style: {
                    color: textColor
                }
            },
            plotOptions: {
                series: {
                    dataLabels: {
                        color: textColor,
                    },
                    marker: {
                        lineColor: borderColor
                    },
                    borderColor: borderColor
                },
                boxplot: {
                    fillColor: bgColor
                },
                candlestick: {
                    lineColor: borderColor
                },
                errorbar: {
                    color: textColor
                }
            },
            legend: {
                backgroundColor: bgColor,
                itemStyle: {
                    color: textColor
                },
                itemHoverStyle: {
                    color: textColor
                },
                itemHiddenStyle: {
                    color: textColor
                },
                title: {
                    style: {
                        color: textColor
                    }
                }
            },
            credits: {
                style: {
                    color: textColor
                }
            },
            labels: {
                style: {
                    color: textColor
                }
            },
            drilldown: {
                activeAxisLabelStyle: {
                    color: textColor
                },
                activeDataLabelStyle: {
                    color: textColor
                }
            },
            navigation: {
                buttonOptions: {
                    symbolStroke: textColor,
                    theme: {
                        fill: bgColor
                    }
                }
            }
        }
        Highcharts.setOptions(chartTheme);
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
                let lessSoldData = [];

                mostSold.forEach((obj) => {
                        let item = {
                            name: obj.producto,
                            low: Number(obj.cantidad)
                        };
                        mostSoldData.push(item);
                    }
                );

                lessSold.forEach((obj) => {
                        let item = {
                            name: obj.producto,
                            low: Number(obj.cantidad)
                        };
                        lessSoldData.push(item);
                    }
                );

                mostSoldProductsData = mostSoldData;
                lessSoldProductsData = lessSoldData;
                rotationDataLoaded = true;

                window.observer.notify('on:rotation-data:loaded');
                dgWithoutRotation.datagrid('loadData', withoutRotation);
            })
            .catch(err => show_error(err))
    }

    function fetchData() {
        const totalSalesPerDayOfCurrentWeekRequest = fetch('bi/venta-semana').then(response => response.json());
        const totalSalesPerDayOfCurrentMonthRequest = fetch('bi/venta-dia').then(response => response.json());
        const totalSalesPerMonthRequest = fetch('bi/venta-mes').then(response => response.json());
        const invoiceCountPerMonthRequest = fetch('bi/monthly-invoicing').then(response => response.json());

        const data = Promise.all([
            totalSalesPerDayOfCurrentWeekRequest,
            totalSalesPerDayOfCurrentMonthRequest,
            totalSalesPerMonthRequest,
            invoiceCountPerMonthRequest
        ]);

        data.then(result => {
            totalSalesPerDayOfCurrentWeekData = result[0];
            totalSalesPerDayOfCurrentMonthData = result[1];
            totalSalesPerMonthData = result[2];
            invoiceCountPerMonthData = result[3];
            dataLoaded = true;

            window.observer.notify('on:bi-data:loaded');
        });
    }

    function drawRotationCharts() {
        drawMostSoldProducts();
        drawLessSoldProducts();
    }

    function drawCharts() {
        drawTotalSalesPerDayOfCurrentWeek();
        drawTotalSalesPerDayOfCurrentMonth();
        drawTotalSalesPerMonth();
        drawInvoiceCountPerMonth();
    }

    function drawMostSoldProducts() {
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
                data: mostSoldProductsData
            }]
        });
    }

    function drawLessSoldProducts() {
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
                data: lessSoldProductsData
            }]
        });
    }

    function drawTotalSalesPerDayOfCurrentWeek() {
        let days = [];
        let totalSales = [];
        totalSalesPerDayOfCurrentWeekData.forEach((item) => {
            days.push(item.dia);
            totalSales.push(parseFloat(item.venta));
        });

        Highcharts.chart('venta_semana', {
            title: {
                text: 'Ventas por semana'
            },
            subtitle: {
                text: ''
            },
            xAxis: {
                categories: days
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
                data: totalSales,
                showInLegend: false
            }]
        });
    }

    function drawTotalSalesPerDayOfCurrentMonth() {
        let days = [];
        let totalSales = [];
        totalSalesPerDayOfCurrentMonthData.forEach((item) => {
            days.push(item.dia);
            totalSales.push(parseFloat(item.venta));
        });

        Highcharts.chart('venta_dia', {
            chart: {
                type: 'spline'
            },
            title: {
                text: 'Venta por día'
            },
            xAxis: {
                categories: days
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
            series: [{
                name: 'Total',
                data: totalSales
            }]
        });
    }

    function drawTotalSalesPerMonth() {
        let months = [];
        let totalSales = [];
        totalSalesPerMonthData.forEach((item) => {
            months.push(item.mes);
            totalSales.push(parseFloat(item.venta));
        });

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
                data: totalSales,
                showInLegend: false
            }]
        });
    }

    function drawInvoiceCountPerMonth() {
        let months = [];
        let data = [];
        invoiceCountPerMonthData.forEach((item) => {
            months.push(item.month);
            data.push(parseFloat(item.total));
        });

        Highcharts.chart('monthly_invoicing', {
            title: {
                text: 'Facturación por mes'
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
    }

    return {
        init: init
    }
})();
app.dashboard.init();

import * as Highcharts from 'highcharts';
import * as Exporting from 'highcharts/modules/exporting';
import * as HighchartsMore from 'highcharts/highcharts-more';
import * as Dumbbell from 'highcharts/modules/dumbbell';
import * as Lollipop from 'highcharts/modules/lollipop';
import {messenger} from "../core/utils/Messenger";
import {bus} from "../core/event/EventBus";
import {EventType} from "../core/event/EventType";
import {Event} from "../core/event/Event";

export default class Dashboard {
    lapseSelector = $('#lapse-selector');
    dgWithoutRotation = $('#without-rotation');

    chartTheme: any;

    mostSoldProductsData: any;
    lessSoldProductsData: any;
    totalSalesPerDayOfCurrentWeekData: any;
    totalSalesPerDayOfCurrentMonthData: any;
    totalSalesPerMonthData: any;
    invoiceCountPerMonthData: any;
    dataLoaded = false;
    rotationDataLoaded = false;

    constructor(container: HTMLElement) {
        (Exporting as any)(Highcharts);
        (HighchartsMore as any)(Highcharts);
        (Dumbbell as any)(Highcharts);
        (Lollipop as any)(Highcharts);
        this.init();
    }

    init() {
        let ref = this;
        this.lapseSelector.combobox({
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
            onSelect: function (record: any) {
                ref.getRotationInfo(record.value);
            },
            panelHeight: 'auto',
            editable: false
        });

        this.dgWithoutRotation.datagrid({
            title: 'Productos sin rotación',
            pagination: false,
            cls: 'card-panel',
            minHeight: 350,
            columns: [[
                {field: 'producto', title: 'Producto', width: 200},
                {field: 'cantidad', title: 'Cant', align: 'right'}
            ]]
        });

        this.lapseSelector.combobox('select', 'week');
        this.setupObservers();
    }

    setupObservers() {
        bus.on(
            action => action.name === EventType.THEME_CHANGE,
            () => {
                this.setupTheme();
                if (this.dataLoaded) {
                    this.drawCharts();
                } else {
                    this.fetchData();
                }

                if (this.rotationDataLoaded) {
                    this.drawRotationCharts();
                } else {
                    this.getRotationInfo('week');
                }
            }
        );

        bus.on(
            action => action.name === 'on:rotation-data:loaded',
            () => {
                this.drawRotationCharts();
            }
        );

        bus.on(
            action => action.name === 'on:bi-data:loaded',
            () => {
                this.drawCharts();
            }
        );

        window.setInterval(() => {
            this.fetchData();
        }, 60000);
    }

    render() {
        this.setupTheme();
        this.fetchData();
    }

    setupTheme() {
        let bgColor = getComputedStyle(document.body).getPropertyValue('--background-color');
        let primaryColor = getComputedStyle(document.body).getPropertyValue('--primary-color');
        let borderColor = getComputedStyle(document.body).getPropertyValue('--border-color');
        let textColor = getComputedStyle(document.body).getPropertyValue('--text-color');

        this.chartTheme = {
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
        Highcharts.setOptions(this.chartTheme);
    }

    fetchData() {
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
            this.totalSalesPerDayOfCurrentWeekData = result[0];
            this.totalSalesPerDayOfCurrentMonthData = result[1];
            this.totalSalesPerMonthData = result[2];
            this.invoiceCountPerMonthData = result[3];
            this.dataLoaded = true;

            bus.emit(new Event('on:bi-data:loaded'));
        });
    }

    getRotationInfo(lapse: string) {
        fetch('bi/product-rotation/' + lapse)
            .then(response => response.json())
            .then(result => {
                let reversed = result.slice().reverse();
                let mostSold = reversed.slice(0, 10);
                let lessSold = result.filter((item: any) => Number(item.cantidad) > 0).slice(0, 10);
                let withoutRotation = result.filter((item: any) => Number(item.cantidad) === 0);
                let mostSoldData: any[] = [];
                let lessSoldData: any[] = [];

                mostSold.forEach((obj: any) => {
                        let item = {
                            name: obj.producto,
                            low: Number(obj.cantidad)
                        };
                        mostSoldData.push(item);
                    }
                );

                lessSold.forEach((obj: any) => {
                        let item = {
                            name: obj.producto,
                            low: Number(obj.cantidad)
                        };
                        lessSoldData.push(item);
                    }
                );

                this.mostSoldProductsData = mostSoldData;
                this.lessSoldProductsData = lessSoldData;
                this.rotationDataLoaded = true;

                bus.emit(new Event('on:rotation-data:loaded'));
                this.dgWithoutRotation.datagrid('loadData', withoutRotation);
            })
            .catch(err => messenger.show('error', 'Error al obtener datos de rotación de productos'));
    }

    drawRotationCharts() {
        this.drawMostSoldProducts();
        this.drawLessSoldProducts();
    }

    drawCharts() {
        this.drawTotalSalesPerDayOfCurrentWeek();
        this.drawTotalSalesPerDayOfCurrentMonth();
        this.drawTotalSalesPerMonth();
        this.drawInvoiceCountPerMonth();
    }

    drawMostSoldProducts() {
        (Highcharts as any).chart('most-sold', {
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
                data: this.mostSoldProductsData
            }]
        });
    }

    drawLessSoldProducts() {
        (Highcharts as any).chart('less-sold', {
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
                data: this.lessSoldProductsData
            }]
        });
    }

    drawTotalSalesPerDayOfCurrentWeek() {
        let days: any = [];
        let totalSales: any = [];
        this.totalSalesPerDayOfCurrentWeekData.forEach((item: any) => {
            days.push(item.dia);
            totalSales.push(parseFloat(item.venta));
        });

        (Highcharts as any).chart('venta_semana', {
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

    drawTotalSalesPerDayOfCurrentMonth() {
        let days: any = [];
        let totalSales: any = [];
        this.totalSalesPerDayOfCurrentMonthData.forEach((item: any) => {
            days.push(item.dia);
            totalSales.push(parseFloat(item.venta));
        });

        (Highcharts as any).chart('venta_dia', {
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

    drawTotalSalesPerMonth() {
        let months: any = [];
        let totalSales: any = [];
        this.totalSalesPerMonthData.forEach((item: any) => {
            months.push(item.mes);
            totalSales.push(parseFloat(item.venta));
        });

        (Highcharts as any).chart('venta_mes', {
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

    drawInvoiceCountPerMonth() {
        let months: any = [];
        let data: any = [];
        this.invoiceCountPerMonthData.forEach((item: any) => {
            months.push(item.month);
            data.push(parseFloat(item.total));
        });

        (Highcharts as any).chart('monthly_invoicing', {
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
}
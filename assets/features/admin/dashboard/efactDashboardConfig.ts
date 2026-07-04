import {
  barChartWidget,
  defineDashboard,
  donutChartWidget,
  statWidget,
  tableWidget,
  type DashboardConfig,
} from '@nubitio/react-admin';

export function buildEfactDashboardConfig(title: string): DashboardConfig {
  return defineDashboard({
    title,
    refreshInterval: 60_000,
    sections: [
      {
        id: 'kpis',
        layout: 'stats',
        widgets: [
          statWidget({
            id: 'sales-today',
            title: 'Ventas hoy',
            valuePath: 'stats.netSalesToday',
            format: 'currency',
            icon: 'ph-currency-dollar',
            iconTone: 'success',
            trend: { valuePath: 'stats.dailyGrowth', label: 'vs ayer' },
          }),
          statWidget({
            id: 'sales-month',
            title: 'Ventas mes',
            valuePath: 'stats.salesThisMonth',
            format: 'currency',
            icon: 'ph-trend-up',
            iconTone: 'warning',
            trend: { valuePath: 'stats.monthGrowth', label: 'vs mes ant.' },
          }),
          statWidget({
            id: 'avg-ticket',
            title: 'Ticket medio',
            valuePath: 'stats.avgTicket',
            format: 'currency',
            icon: 'ph-chart-line-up',
            iconTone: 'accent',
          }),
          statWidget({
            id: 'igv-month',
            title: 'IGV del mes',
            valuePath: 'stats.igvCollected',
            format: 'currency',
            icon: 'ph-receipt',
            iconTone: 'success',
          }),
        ],
      },
      {
        id: 'sales-chart',
        layout: 'charts',
        widgets: [
          barChartWidget({
            id: 'sales-by-day',
            title: 'Ventas por día',
            subtitle: 'Últimos 7 días (neto)',
            dataPath: 'salesByDay',
            xKey: 'label',
            yKey: 'total',
            height: 200,
          }),
          donutChartWidget({
            id: 'sales-by-payment-method',
            title: 'Ventas por método de pago',
            subtitle: 'Distribución del período',
            dataPath: 'paymentMethods',
            labelKey: 'method',
            valueKey: 'total',
            valueFormat: 'currency',
            showLegend: true,
          }),
        ],
      },
      {
        id: 'sales-by-month-full',
        layout: 'full',
        widgets: [
          barChartWidget({
            id: 'sales-by-month',
            title: 'Ventas últimos 12 meses',
            subtitle: 'Últimos 12 meses (neto)',
            dataPath: 'salesByMonth',
            xKey: 'label',
            yKey: 'total',
            height: 200,
          }),
        ],
      },
      {
        id: 'tables',
        layout: 'grid',
        columns: 2,
        widgets: [
          tableWidget({
            id: 'recent-sales',
            title: 'Ventas recientes',
            subtitle: 'Hoy',
            dataPath: 'recentSales',
            viewAll: { to: '/sale', label: 'Ver todas' },
            emptyTitle: 'Sin ventas hoy',
            emptyDescription: 'Abre el POS para registrar la primera.',
            columns: [
              { key: 'issuedAt', label: 'Hora' },
              { key: 'paymentMethod', label: 'Método' },
              { key: 'total', label: 'Total', format: 'currency', align: 'right' },
            ],
          }),
          tableWidget({
            id: 'top-products',
            title: 'Top productos',
            subtitle: 'Últimos 30 días',
            dataPath: 'topProducts',
            emptyTitle: 'Sin ventas',
            emptyDescription: 'Sin productos vendidos en el período.',
            columns: [
              { key: 'name', label: 'Producto' },
              { key: 'quantity', label: 'Cant.', format: 'number', align: 'right' },
              { key: 'total', label: 'Total', format: 'currency', align: 'right' },
            ],
          }),
        ],
      },
    ],
  });
}
// hmr-trigger

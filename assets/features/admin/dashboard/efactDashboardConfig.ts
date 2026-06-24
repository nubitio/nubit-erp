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
          }),
          statWidget({
            id: 'invoices-today',
            title: 'Comprobantes hoy',
            valuePath: 'stats.invoicesToday',
            format: 'number',
            icon: 'ph-receipt',
            iconTone: 'info',
          }),
          statWidget({
            id: 'avg-ticket',
            title: 'Ticket medio (mes)',
            valuePath: 'stats.avgTicket',
            format: 'currency',
            icon: 'ph-chart-line-up',
            iconTone: 'accent',
          }),
          statWidget({
            id: 'sales-month',
            title: 'Ventas mes',
            valuePath: 'stats.salesThisMonth',
            format: 'currency',
            icon: 'ph-trend-up',
            iconTone: 'warning',
          }),
        ],
      },
      {
        id: 'charts',
        layout: 'charts',
        widgets: [
          barChartWidget({
            id: 'sales-by-day',
            title: 'Ventas por día',
            subtitle: 'Últimos 7 días (neto)',
            dataPath: 'salesByDay',
            xKey: 'label',
            yKey: 'total',
            height: 180,
          }),
          donutChartWidget({
            id: 'by-voucher-type',
            title: 'Por comprobante',
            subtitle: 'Este mes',
            dataPath: 'voucherTypes',
            labelKey: 'name',
            valueKey: 'total',
            centerLabel: 'Total',
            centerValuePath: 'stats.salesThisMonth',
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
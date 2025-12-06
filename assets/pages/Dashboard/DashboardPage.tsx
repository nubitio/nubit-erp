import './DashboardPage.scss';
import {useMemo} from 'react';
import {Chart} from 'devextreme-react';
import {ArgumentAxis, Label, Legend, Series, Size, Tooltip, ValueAxis} from 'devextreme-react/chart';
import {
  Label as PieLabel,
  Legend as PieLegend,
  PieChart,
  Series as PieSeries,
  Size as PieSize,
  Tooltip as PieTooltip
} from 'devextreme-react/pie-chart';
import LoadPanel from 'devextreme-react/load-panel';
import {AppToolbar} from '../../components/utils/toolbar/AppToolbar';
import {CardAnalytics} from '../../components/library/card-analytics/CardAnalytics';
import ScrollView from "devextreme-react/scroll-view";
import {StatCard} from '../../components/dashboard/StatCard';
import {currencyFormatter, decimalFormatter} from '../../components/dashboard/formatters';
import {useDashboardOverview} from './hooks/useDashboardOverview';

export const DashboardPage = () => {
  const {data, loading, error, refetch} = useDashboardOverview();
  const summary = useMemo(() => data.summary, [data]);

  return (
    <ScrollView className="view-wrapper-scroll">
      <AppToolbar title="Dashboard" onRefresh={refetch}>
        {error && <div className="dashboard-error">{error}</div>}

        <div className='cards metrics'>
          <StatCard
            title="Venta hoy"
            value={currencyFormatter(summary.salesToday)}
            description={`${decimalFormatter(summary.invoicesToday)} comprobantes`}
            accent="blue"
            delta={summary.dailyGrowth}
            deltaLabel="vs ayer"
          />
          <StatCard
            title="Venta del mes"
            value={currencyFormatter(summary.salesThisMonth)}
            description={`${decimalFormatter(summary.invoicesThisMonth)} comprobantes emitidos`}
            accent="green"
            delta={summary.monthGrowth}
            deltaLabel="vs mes anterior"
          />
          <StatCard
            title="Ticket promedio"
            value={currencyFormatter(summary.avgTicket)}
            description={`Hoy: ${currencyFormatter(summary.avgTicketToday)}`}
            accent="yellow"
          />
          <StatCard
            title="Clientes activos"
            value={decimalFormatter(summary.customersThisMonth)}
            description={`${decimalFormatter(summary.customersWeek)} últimos 7 días`}
            accent="rose"
          />
          <StatCard
            title="Venta semanal"
            value={currencyFormatter(summary.salesWeek)}
            description="Últimos 7 días acumulados"
            accent="purple"
          />
        </div>

        <div className='cards normal'>
          <CardAnalytics title="Evolución mensual (12 meses)" contentClass="chart-card" menuVisible={false}>
            <Chart dataSource={data.salesByMonth} id="sales-by-month">
              <Size height={280}/>
              <ArgumentAxis discreteAxisDivisionMode="betweenLabels"/>
              <Series valueField="total" argumentField="label" type="bar" color="var(--accent-color)"/>
              <ValueAxis>
                <Label customizeText={arg => currencyFormatter(Number(arg.value))}/>
              </ValueAxis>
              <Tooltip enabled customizeTooltip={arg => ({text: currencyFormatter(arg.value)})}/>
              <Legend visible={false}/>
            </Chart>
          </CardAnalytics>

          <CardAnalytics title="Ventas diarias (mes actual)" contentClass="chart-card" menuVisible={false}>
            <Chart dataSource={data.salesByDay} id="sales-by-day">
              <Size height={280}/>
              <ArgumentAxis argumentType="numeric"/>
              <Series valueField="total" argumentField="day" type="splinearea"
                      color="var(--accent-color)"/>
              <ValueAxis>
                <Label customizeText={arg => currencyFormatter(Number(arg.value))}/>
              </ValueAxis>
              <Tooltip enabled customizeTooltip={arg => ({text: currencyFormatter(arg.value)})}/>
              <Legend visible={false}/>
            </Chart>
          </CardAnalytics>

          <CardAnalytics title="Medios de pago (últimos 30 días)" contentClass="chart-card"
                         menuVisible={false}>
            <PieChart dataSource={data.paymentMethods} id="payment-methods" palette="Pastel"
                      resolveLabelOverlapping="shift">
              <PieSeries argumentField="method" valueField="total"/>
              <PieLegend visible horizontalAlignment="center" verticalAlignment="bottom"/>
              <PieTooltip enabled
                          customizeTooltip={arg => ({text: `${arg.argumentText}: ${currencyFormatter(arg.value)}`})}/>
              <PieSize height={280}/>
            </PieChart>
          </CardAnalytics>

          <CardAnalytics title="Top productos (últimos 30 días)" contentClass="list-card" menuVisible={false}>
            <ul className="ranked-list">
              {data.topProducts.map(item => (
                <li key={item.product}>
                  <div className="ranked-list__title">{item.product}</div>
                  <div className="ranked-list__meta">
                    <span>{decimalFormatter(item.quantity)} u.</span>
                    <span>{currencyFormatter(item.total)}</span>
                  </div>
                </li>
              ))}
              {data.topProducts.length === 0 && <div className="empty">Sin datos disponibles</div>}
            </ul>
          </CardAnalytics>

          <CardAnalytics title="Top clientes (últimos 30 días)" contentClass="list-card" menuVisible={false}>
            <ul className="ranked-list">
              {data.topCustomers.map(item => (
                <li key={item.customer}>
                  <div className="ranked-list__title">{item.customer}</div>
                  <div className="ranked-list__meta">
                    <span>{decimalFormatter(item.invoices)} docs</span>
                    <span>{currencyFormatter(item.total)}</span>
                  </div>
                </li>
              ))}
              {data.topCustomers.length === 0 && <div className="empty">Sin datos disponibles</div>}
            </ul>
          </CardAnalytics>

          <CardAnalytics title="Distribución por tipo de comprobante" contentClass="chart-card" menuVisible={false}>
            <PieChart dataSource={data.voucherTypes} id="voucher-types" palette="Soft"
                      resolveLabelOverlapping="shift">
              <PieSeries argumentField="type" valueField="total">
                <PieLabel visible customizeText={arg => `${arg.argumentText}\n${arg.percentText}`}/>
              </PieSeries>
              <PieLegend visible horizontalAlignment="center" verticalAlignment="bottom"/>
              <PieTooltip enabled
                          customizeTooltip={arg => ({text: `${arg.argumentText}: ${currencyFormatter(arg.value)} (${arg.point.data.count} docs)`})}/>
              <PieSize height={280}/>
            </PieChart>
          </CardAnalytics>

          <CardAnalytics title="⚠️ Productos con stock bajo o sin stock" contentClass="list-card" menuVisible={false}>
            <ul className="ranked-list stock-alert">
              {data.lowStockProducts.map((item, idx) => (
                <li key={idx}
                    className={item.stockPercentage < 30 ? 'critical' : item.stockPercentage < 60 ? 'warning' : ''}>
                  <div className="ranked-list__title">{item.product}</div>
                  <div className="ranked-list__meta">
                    <span className="stock-info">
                      {decimalFormatter(item.currentStock)} / {decimalFormatter(item.minStock)} u.
                      <span className="stock-badge">{item.stockPercentage}%</span>
                    </span>
                  </div>
                </li>
              ))}
              {data.lowStockProducts.length === 0 && <div className="empty">✓ Stock en niveles óptimos</div>}
            </ul>
          </CardAnalytics>
        </div>
      </AppToolbar>
      <LoadPanel container='.content' visible={loading} position={{of: '.layout-body'}}/>
    </ScrollView>
  );
};

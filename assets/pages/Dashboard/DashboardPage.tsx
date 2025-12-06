import './DashboardPage.scss';
import {useEffect, useMemo, useState} from 'react';
import {Chart} from 'devextreme-react';
import {ArgumentAxis, Label, Legend, Series, Size, Tooltip, ValueAxis} from 'devextreme-react/chart';
import {
  Legend as PieLegend,
  PieChart,
  Series as PieSeries,
  Size as PieSize,
  Tooltip as PieTooltip
} from 'devextreme-react/pie-chart';
import LoadPanel from 'devextreme-react/load-panel';
import {AppToolbar} from '../../components/utils/toolbar/AppToolbar';
import {CardAnalytics} from '../../components/library/card-analytics/CardAnalytics';
import {httpClient} from '../../core/http/HttpClient';
import {Constants} from '../../core/common/Constants';
import toast from '../../core/utils/SharedUtils';
import ScrollView from "devextreme-react/scroll-view";

type DashboardData = {
  summary: {
    salesToday: number;
    salesYesterday: number;
    salesWeek: number;
    salesThisMonth: number;
    salesLastMonth: number;
    invoicesToday: number;
    invoicesThisMonth: number;
    customersThisMonth: number;
    customersWeek: number;
    avgTicket: number;
    avgTicketToday: number;
    monthGrowth: number | null;
    dailyGrowth: number | null;
  };
  salesByDay: { day: number; label: string; total: number }[];
  salesByMonth: { label: string; total: number }[];
  paymentMethods: { method: string; total: number }[];
  topProducts: { product: string; quantity: number; total: number }[];
  topCustomers: { customer: string; invoices: number; total: number }[];
  voucherTypes: { type: string; count: number; total: number }[];
  lowStockProducts: { product: string; currentStock: number; minStock: number; avgPrice: number; stockPercentage: number }[];
};

const initialData: DashboardData = {
  summary: {
    salesToday: 0,
    salesYesterday: 0,
    salesWeek: 0,
    salesThisMonth: 0,
    salesLastMonth: 0,
    invoicesToday: 0,
    invoicesThisMonth: 0,
    customersThisMonth: 0,
    customersWeek: 0,
    avgTicket: 0,
    avgTicketToday: 0,
    monthGrowth: null,
    dailyGrowth: null,
  },
  salesByDay: [],
  salesByMonth: [],
  paymentMethods: [],
  topProducts: [],
  topCustomers: [],
  voucherTypes: [],
  lowStockProducts: [],
};

const currency = new Intl.NumberFormat('es-PE', {style: 'currency', currency: 'PEN', maximumFractionDigits: 0});
const decimal = new Intl.NumberFormat('es-PE', {minimumFractionDigits: 0, maximumFractionDigits: 0});
const percent = new Intl.NumberFormat('es-PE', {minimumFractionDigits: 1, maximumFractionDigits: 1});

const MetricCard = (
  {
    title,
    value,
    description,
    accent,
    delta,
  }: {
    title: string;
    value: string;
    description?: string;
    accent: 'blue' | 'green' | 'yellow' | 'rose' | 'purple' | 'gray';
    delta?: number | null;
  }) => (
  <div className={`metric-card accent-${accent}`}>
    <div className="metric-card__title">{title}</div>
    <div className="metric-card__value">{value}</div>
    <div className="metric-card__footer">
      <span>{description}</span>
      {delta !== undefined && delta !== null && (
        <span className={`metric-card__delta ${delta >= 0 ? 'up' : 'down'}`}>
          {delta >= 0 ? '▲' : '▼'} {percent.format(Math.abs(delta))} vs mes anterior
        </span>
      )}
    </div>
  </div>
);

export const DashboardPage = () => {
  const [data, setData] = useState<DashboardData>(initialData);
  const [loading, setLoading] = useState(false);

  const normalize = (payload: any): DashboardData => ({
    summary: {...initialData.summary, ...(payload?.summary ?? {})},
    salesByDay: payload?.salesByDay ?? [],
    salesByMonth: payload?.salesByMonth ?? [],
    paymentMethods: payload?.paymentMethods ?? [],
    topProducts: payload?.topProducts ?? [],
    topCustomers: payload?.topCustomers ?? [],
    voucherTypes: payload?.voucherTypes ?? [],
    lowStockProducts: payload?.lowStockProducts ?? [],
  });

  const loadDashboard = () => {
    setLoading(true);
    httpClient.get<any>(Constants.API_BASE_URL + 'bi/overview').subscribe({
      next: response => {
        setData(normalize(response.data));
        setLoading(false);
      },
      error: err => {
        toast('No se pudo cargar el dashboard', 'error');
        console.error(err);
        setLoading(false);
      },
    });
  };

  useEffect(() => {
    loadDashboard();
    const refresh = setInterval(loadDashboard, 5 * 60 * 1000);
    return () => clearInterval(refresh);
  }, []);

  const summary = useMemo(() => data.summary, [data]);

  return (
    <ScrollView className="view-wrapper-scroll">
      <AppToolbar title="Dashboard" onRefresh={loadDashboard}>
        <div className='cards compact'>
          <MetricCard
            title="Venta hoy"
            value={currency.format(summary.salesToday)}
            description={`${decimal.format(summary.invoicesToday)} comprobantes`}
            accent="blue"
            delta={summary.dailyGrowth}
          />
          <MetricCard
            title="Venta del mes"
            value={currency.format(summary.salesThisMonth)}
            description={`${decimal.format(summary.invoicesThisMonth)} comprobantes emitidos`}
            accent="green"
            delta={summary.monthGrowth}
          />
          <MetricCard
            title="Ticket promedio"
            value={currency.format(summary.avgTicket)}
            description={`Hoy: ${currency.format(summary.avgTicketToday)}`}
            accent="yellow"
          />
          <MetricCard
            title="Clientes activos"
            value={decimal.format(summary.customersThisMonth)}
            description={`${decimal.format(summary.customersWeek)} últimos 7 días`}
            accent="rose"
          />
          <MetricCard
            title="Venta semanal"
            value={currency.format(summary.salesWeek)}
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
                <Label customizeText={arg => currency.format(Number(arg.value))}/>
              </ValueAxis>
              <Tooltip enabled customizeTooltip={arg => ({text: currency.format(arg.value)})}/>
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
                <Label customizeText={arg => currency.format(Number(arg.value))}/>
              </ValueAxis>
              <Tooltip enabled customizeTooltip={arg => ({text: currency.format(arg.value)})}/>
              <Legend visible={false}/>
            </Chart>
          </CardAnalytics>

          <CardAnalytics title="Medios de pago (últimos 90 días)" contentClass="chart-card"
                         menuVisible={false}>
            <PieChart dataSource={data.paymentMethods} id="payment-methods" palette="Pastel"
                      resolveLabelOverlapping="shift">
              <PieSeries argumentField="method" valueField="total"/>
              <PieLegend visible horizontalAlignment="center" verticalAlignment="bottom"/>
              <PieTooltip enabled
                          customizeTooltip={arg => ({text: `${arg.argumentText}: ${currency.format(arg.value)}`})}/>
              <PieSize height={280}/>
            </PieChart>
          </CardAnalytics>

          <CardAnalytics title="Top productos (últimos 30 días)" contentClass="list-card" menuVisible={false}>
            <ul className="ranked-list">
              {data.topProducts.map(item => (
                <li key={item.product}>
                  <div className="ranked-list__title">{item.product}</div>
                  <div className="ranked-list__meta">
                    <span>{decimal.format(item.quantity)} u.</span>
                    <span>{currency.format(item.total)}</span>
                  </div>
                </li>
              ))}
              {data.topProducts.length === 0 && <div className="empty">Sin datos disponibles</div>}
            </ul>
          </CardAnalytics>

          <CardAnalytics title="Top clientes (últimos 90 días)" contentClass="list-card" menuVisible={false}>
            <ul className="ranked-list">
              {data.topCustomers.map(item => (
                <li key={item.customer}>
                  <div className="ranked-list__title">{item.customer}</div>
                  <div className="ranked-list__meta">
                    <span>{decimal.format(item.invoices)} docs</span>
                    <span>{currency.format(item.total)}</span>
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
                <Label visible customizeText={arg => `${arg.argumentText}\n${arg.percentText}`}/>
              </PieSeries>
              <PieLegend visible horizontalAlignment="center" verticalAlignment="bottom"/>
              <PieTooltip enabled
                          customizeTooltip={arg => ({text: `${arg.argumentText}: ${currency.format(arg.value)} (${arg.point.data.count} docs)`})}/>
              <PieSize height={280}/>
            </PieChart>
          </CardAnalytics>

          <CardAnalytics title="⚠️ Productos con stock bajo" contentClass="list-card" menuVisible={false}>
            <ul className="ranked-list stock-alert">
              {data.lowStockProducts.map((item, idx) => (
                <li key={idx} className={item.stockPercentage < 30 ? 'critical' : item.stockPercentage < 60 ? 'warning' : ''}>
                  <div className="ranked-list__title">{item.product}</div>
                  <div className="ranked-list__meta">
                    <span className="stock-info">
                      {decimal.format(item.currentStock)} / {decimal.format(item.minStock)} u.
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

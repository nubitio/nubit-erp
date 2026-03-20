import { Chart } from 'devextreme-react';
import {
  ArgumentAxis,
  Label,
  Legend,
  Series,
  Size,
  Tooltip,
  ValueAxis,
} from 'devextreme-react/chart';
import { CardAnalytics } from '@shared/ui/card-analytics/CardAnalytics';
import { SalesByMonth, SalesByDay } from '../types';
import { currencyFormatter } from '@shared/ui/dashboard/formatters';

function SectionHeader({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div className="dash-section-header">
      <span className="dash-section-header__title">{title}</span>
      {subtitle && <span className="dash-section-header__sub">{subtitle}</span>}
    </div>
  );
}

type Props = {
  salesByMonth: SalesByMonth[];
  salesByDay: SalesByDay[];
};

export function DashboardCharts({ salesByMonth, salesByDay }: Props) {
  return (
    <>
      <SectionHeader title="Evolución de ventas" />
      <div className="dash-charts-2col">
        <CardAnalytics
          title="Evolución mensual (12 meses)"
          contentClass="chart-card"
          menuVisible={false}
        >
          <Chart dataSource={salesByMonth} id="sales-by-month">
            <Size height={260} />
            <ArgumentAxis discreteAxisDivisionMode="betweenLabels" />
            <Series
              valueField="total"
              argumentField="label"
              type="bar"
              color="var(--accent-color)"
            />
            <ValueAxis>
              <Label customizeText={(arg) => currencyFormatter(Number(arg.value))} />
            </ValueAxis>
            <Tooltip
              enabled
              customizeTooltip={(arg) => ({
                text: currencyFormatter('value' in arg ? Number(arg.value) : 0),
              })}
            />
            <Legend visible={false} />
          </Chart>
        </CardAnalytics>

        <CardAnalytics
          title="Ventas diarias (mes actual)"
          contentClass="chart-card"
          menuVisible={false}
        >
          <Chart dataSource={salesByDay} id="sales-by-day">
            <Size height={260} />
            <ArgumentAxis argumentType="numeric" />
            <Series
              valueField="total"
              argumentField="day"
              type="splinearea"
              color="var(--accent-color)"
            />
            <ValueAxis>
              <Label customizeText={(arg) => currencyFormatter(Number(arg.value))} />
            </ValueAxis>
            <Tooltip
              enabled
              customizeTooltip={(arg) => ({
                text: currencyFormatter('value' in arg ? Number(arg.value) : 0),
              })}
            />
            <Legend visible={false} />
          </Chart>
        </CardAnalytics>
      </div>
    </>
  );
}

import { KpiMetricRow, type KpiMetricItem } from '@nubitio/ui';
import NumberUtils from '@shared/lib/NumberUtils';
import { useCashRegisterSummary } from './useCashRegisterSummary';

export const CashRegisterSummaryCards = () => {
  const { data, isLoading } = useCashRegisterSummary();

  const items: KpiMetricItem[] = [
    {
      label: 'Cajas abiertas',
      value: data ? `${data.openRegisters}/${data.totalRegisters}` : '—',
      hint: data ? `${data.totalRegisters} caja(s) registradas` : undefined,
      icon: 'ph-cash-register',
      tone: 'default',
    },
    {
      label: 'Ventas hoy',
      value: data ? NumberUtils.formatCurrency(data.todaySalesTotal) : '—',
      hint: 'Todas las cajas',
      icon: 'ph-trend-up',
      tone: 'default',
    },
    {
      label: 'Saldo total',
      value: data ? NumberUtils.formatCurrency(data.balanceTotal) : '—',
      hint: 'Efectivo en cajas abiertas',
      icon: 'ph-coins',
      tone: 'default',
    },
  ];

  return (
    <KpiMetricRow
      layout="strip"
      density="compact"
      items={items}
      loading={isLoading}
      testId="cash-register-summary"
    />
  );
};

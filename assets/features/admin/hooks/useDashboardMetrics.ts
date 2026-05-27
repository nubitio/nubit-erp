import { useMemo } from 'react';
import { DashboardData, CashRegisterStatus, HourlySalesToday } from '../types';

export type DashboardMetrics = {
  openCashCount: number;
  criticalStockCount: number;
  warnStockCount: number;
  totalStockAlerts: number;
  growthUp: boolean;
  monthGrowthUp: boolean;
  purchasesMonthGrowthUp: boolean;
  totalPaymentsToday: number;
  bestHour: HourlySalesToday | null;
  topPaymentMethod: { method: string; total: number } | null;
};

export const useDashboardMetrics = (
  data: DashboardData,
  cashRegisters: CashRegisterStatus[],
): DashboardMetrics => {
  return useMemo(() => {
    const { summary, purchasesSummary, lowStockProducts, paymentMethodsToday, hourlySalesToday } =
      data;

    const openCashCount = cashRegisters.filter((c) => c.status === 'open').length;
    const criticalStockCount = lowStockProducts.filter((p) => p.stockPercentage < 30).length;
    const warnStockCount = lowStockProducts.filter(
      (p) => p.stockPercentage >= 30 && p.stockPercentage < 60,
    ).length;
    const totalStockAlerts = criticalStockCount + warnStockCount;

    const growthUp =
      summary.dailyGrowth !== null && summary.dailyGrowth !== undefined && summary.dailyGrowth >= 0;
    const monthGrowthUp =
      summary.monthGrowth !== null && summary.monthGrowth !== undefined && summary.monthGrowth >= 0;
    const purchasesMonthGrowthUp =
      purchasesSummary.monthGrowth !== null &&
      purchasesSummary.monthGrowth !== undefined &&
      purchasesSummary.monthGrowth >= 0;

    const totalPaymentsToday = paymentMethodsToday.reduce((acc, item) => acc + item.total, 0);

    const bestHour = hourlySalesToday.reduce<HourlySalesToday | null>((best, current) => {
      if (!best || current.total > best.total) return current;
      return best;
    }, null);

    const topPaymentMethod = paymentMethodsToday[0] ?? null;

    return {
      openCashCount,
      criticalStockCount,
      warnStockCount,
      totalStockAlerts,
      growthUp,
      monthGrowthUp,
      purchasesMonthGrowthUp,
      totalPaymentsToday,
      bestHour,
      topPaymentMethod,
    };
  }, [data, cashRegisters]);
};

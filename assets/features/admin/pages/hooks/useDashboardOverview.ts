import { useCallback, useEffect, useRef, useState } from 'react';
import { Subscription } from 'rxjs';
import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@platform/common/Constants';
import toast from '@shared/lib/SharedUtils';
import { DashboardData } from '@features/admin/pages/types';

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
    refundsToday: 0,
    refundsThisMonth: 0,
    netSalesToday: 0,
    netSalesThisMonth: 0,
  },
  salesByDay: [],
  salesByMonth: [],
  paymentMethods: [],
  paymentMethodTrend: {
    methods: [],
    points: [],
  },
  topProducts: [],
  purchasesSummary: {
    purchasesToday: 0,
    purchasesWeek: 0,
    purchasesThisMonth: 0,
    purchasesLastMonth: 0,
    receiptsToday: 0,
    receiptsThisMonth: 0,
    monthGrowth: null,
  },
  purchasesByDay: [],
  topPurchasedProducts: [],
  topCustomers: [],
  voucherTypes: [],
  lowStockProducts: [],
  paymentMethodsToday: [],
  hourlySalesToday: [],
  recentSalesToday: [],
  meta: {
    mode: 'full',
    partial: false,
    failedSegments: [],
  },
};

const normalize = (payload: any): DashboardData => ({
  summary: { ...initialData.summary, ...(payload?.summary ?? {}) },
  salesByDay: payload?.salesByDay ?? [],
  salesByMonth: payload?.salesByMonth ?? [],
  paymentMethods: payload?.paymentMethods ?? [],
  paymentMethodTrend: payload?.paymentMethodTrend ?? initialData.paymentMethodTrend,
  topProducts: payload?.topProducts ?? [],
  purchasesSummary: { ...initialData.purchasesSummary, ...(payload?.purchasesSummary ?? {}) },
  purchasesByDay: payload?.purchasesByDay ?? [],
  topPurchasedProducts: payload?.topPurchasedProducts ?? [],
  topCustomers: payload?.topCustomers ?? [],
  voucherTypes: payload?.voucherTypes ?? [],
  lowStockProducts: payload?.lowStockProducts ?? [],
  paymentMethodsToday: payload?.paymentMethodsToday ?? [],
  hourlySalesToday: payload?.hourlySalesToday ?? [],
  recentSalesToday: payload?.recentSalesToday ?? [],
  meta: payload?.meta ?? initialData.meta,
});

type DashboardOverviewOptions = {
  lite?: boolean;
};

export const useDashboardOverview = (options?: DashboardOverviewOptions) => {
  const [data, setData] = useState<DashboardData>(initialData);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const lastSubscription = useRef<Subscription | null>(null);

  const loadDashboard = useCallback(() => {
    setLoading(true);
    setError(null);
    const query = options?.lite ? '?lite=1&view=mobile' : '';

    lastSubscription.current?.unsubscribe?.();
    const subscription = httpClient
      .get<any>(Constants.API_BASE_URL + 'bi/overview' + query)
      .subscribe({
        next: (response) => {
          setData(normalize(response.data));
          setLoading(false);
        },
        error: (err) => {
          toast('No se pudo cargar el dashboard', 'error');
          console.error(err);
          setError('No se pudo cargar el dashboard');
          setLoading(false);
        },
      });

    lastSubscription.current = subscription;
  }, [options?.lite]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- loadDashboard calls setState; initial fetch + periodic refresh pattern
    loadDashboard();
    const refresh = setInterval(loadDashboard, 5 * 60 * 1000);
    return () => {
      clearInterval(refresh);
      lastSubscription.current?.unsubscribe?.();
    };
  }, [loadDashboard]);

  return { data, loading, error, refetch: loadDashboard };
};

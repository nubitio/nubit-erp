
import { useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { API_BASE_URL } from '@app/constants';
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

// eslint-disable-next-line @typescript-eslint/no-explicit-any -- API response is untyped at this layer
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
  const lite = options?.lite ?? false;
  const queryString = lite ? '?lite=1&view=mobile' : '';

  const {
    data,
    isFetching: loading,
    error: queryError,
    refetch,
  } = useQuery<DashboardData, Error>({
    queryKey: ['dashboard-overview', lite],
    // eslint-disable-next-line @typescript-eslint/no-explicit-any -- normalize handles shape validation
    queryFn: () =>
      httpClient.get<any>(API_BASE_URL + 'bi/overview' + queryString).then((response) => normalize(response.data)),
    staleTime: 5 * 60 * 1000, // 5 minutes — keeps parity with the old setInterval
    refetchInterval: 5 * 60 * 1000,
    placeholderData: initialData,
  });

  const error = queryError ? (queryError.message ?? 'No se pudo cargar el dashboard') : null;

  return {
    data: data ?? initialData,
    loading,
    error,
    refetch: () => void refetch(),
  };
};

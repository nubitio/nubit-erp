import {useCallback, useEffect, useRef, useState} from 'react';
import {Subscription} from 'rxjs';
import {httpClient} from '../../../core/http/HttpClient';
import {Constants} from '../../../core/common/Constants';
import toast from '../../../core/utils/SharedUtils';
import {DashboardData} from '../types';

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

export const useDashboardOverview = () => {
  const [data, setData] = useState<DashboardData>(initialData);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const lastSubscription = useRef<Subscription | null>(null);

  const loadDashboard = useCallback(() => {
    setLoading(true);
    setError(null);

    lastSubscription.current?.unsubscribe?.();
    const subscription = httpClient.get<any>(Constants.API_BASE_URL + 'bi/overview').subscribe({
      next: response => {
        setData(normalize(response.data));
        setLoading(false);
      },
      error: err => {
        toast('No se pudo cargar el dashboard', 'error');
        console.error(err);
        setError('No se pudo cargar el dashboard');
        setLoading(false);
      },
    });

    lastSubscription.current = subscription;
  }, []);

  useEffect(() => {
    loadDashboard();
    const refresh = setInterval(loadDashboard, 5 * 60 * 1000);
    return () => {
      clearInterval(refresh);
      lastSubscription.current?.unsubscribe?.();
    };
  }, [loadDashboard]);

  return {data, loading, error, refetch: loadDashboard};
};

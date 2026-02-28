export type SummaryStats = {
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
  refundsToday: number;
  refundsThisMonth: number;
  netSalesToday: number;
  netSalesThisMonth: number;
};

export type SalesByDay = { day: number; label: string; total: number };
export type SalesByMonth = { label: string; total: number };
export type PaymentMethodShare = { method: string; total: number };
export type PaymentMethodTrendPoint = Record<string, string | number>;
export type TopProduct = { product: string; quantity: number; total: number };
export type PurchasesSummaryStats = {
  purchasesToday: number;
  purchasesWeek: number;
  purchasesThisMonth: number;
  purchasesLastMonth: number;
  receiptsToday: number;
  receiptsThisMonth: number;
  monthGrowth: number | null;
};
export type PurchaseByDay = { day: number; label: string; total: number };
export type TopPurchasedProduct = { product: string; quantity: number; total: number };
export type TopCustomer = { customer: string; invoices: number; total: number };
export type VoucherTypeShare = { type: string; count: number; total: number };
export type LowStockProduct = {
  product: string;
  currentStock: number;
  minStock: number;
  avgPrice: number;
  stockPercentage: number;
};

export type DashboardData = {
  summary: SummaryStats;
  salesByDay: SalesByDay[];
  salesByMonth: SalesByMonth[];
  paymentMethods: PaymentMethodShare[];
  paymentMethodTrend: {
    methods: string[];
    points: PaymentMethodTrendPoint[];
  };
  topProducts: TopProduct[];
  purchasesSummary: PurchasesSummaryStats;
  purchasesByDay: PurchaseByDay[];
  topPurchasedProducts: TopPurchasedProduct[];
  topCustomers: TopCustomer[];
  voucherTypes: VoucherTypeShare[];
  lowStockProducts: LowStockProduct[];
  meta?: {
    mode?: string;
    partial?: boolean;
    failedSegments?: string[];
    generatedAt?: string;
  };
};

export type CashRegisterStatus = {
  id: number;
  name: string;
  cashShiftId: number | null;
  openedAt: string | null;
  closedAt: string | null;
  cashier: string | null;
  balance: number | null;
  status: 'open' | 'closed' | string;
};

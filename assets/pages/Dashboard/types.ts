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
};

export type SalesByDay = { day: number; label: string; total: number };
export type SalesByMonth = { label: string; total: number };
export type PaymentMethodShare = { method: string; total: number };
export type TopProduct = { product: string; quantity: number; total: number };
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
  topProducts: TopProduct[];
  topCustomers: TopCustomer[];
  voucherTypes: VoucherTypeShare[];
  lowStockProducts: LowStockProduct[];
};

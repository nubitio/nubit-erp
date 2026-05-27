// Admin feature barrel — public API
// Pages
export { DashboardPage } from './DashboardPage';
export { BranchPage } from './BranchPage';
export { CompanyPage } from './CompanyPage';
export { ConfigPage } from './ConfigPage';
export { ExchangeRatePage } from './ExchangeRatePage';
export { RolePage } from './RolePage';
export { UserPage } from './UserPage';

// Public types (dashboard domain model)
export type {
  SummaryStats,
  SalesByDay,
  SalesByMonth,
  PaymentMethodShare,
  PaymentMethodTrendPoint,
  TopProduct,
  PurchasesSummaryStats,
  PurchaseByDay,
  TopPurchasedProduct,
  TopCustomer,
  VoucherTypeShare,
  LowStockProduct,
  PaymentMethodToday,
  HourlySalesToday,
  RecentSaleToday,
  DashboardData,
  CashRegisterStatus,
} from './types';

// Routes
export { adminRoutes } from './routes';

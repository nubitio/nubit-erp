// Admin feature barrel — public API
// Pages
export { DashboardPage } from './pages/DashboardPage';
export { BranchPage } from './pages/BranchPage';
export { CompanyPage } from './pages/CompanyPage';
export { ConfigPage } from './pages/ConfigPage';
export { DepartmentPage } from './pages/DepartmentPage';
export { EmployeePage } from './pages/EmployeePage';
export { ExchangeRatePage } from './pages/ExchangeRatePage';
export { PositionPage } from './pages/PositionPage';
export { RolePage } from './pages/RolePage';
export { UserPage } from './pages/UserPage';

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
} from './pages/types';

// Routes
export { adminRoutes } from './routes';

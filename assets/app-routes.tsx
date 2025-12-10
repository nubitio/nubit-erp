import { withNavigationWatcher } from './contexts/navigation';
import { DashboardPage } from './pages/Dashboard/DashboardPage';
import { ProductPage } from './pages/Product/ProductPage';
import { BranchPage } from './pages/Branch/BranchPage';
import { CategoryPage } from './pages/Category/CategoryPage';
import { CompanyPage } from './pages/Company/CompanyPage';
import { ConfigPage } from './pages/Config/ConfigPage';
import { CreditNotePage } from './pages/CreditNote/CreditNotePage';
import { CustomerPage } from './pages/Customer/CustomerPage';
import { DepartmentPage } from './pages/Department/DepartmentPage';
import { EmployeePage } from './pages/Employee/EmployeePage';
import { InboundPage } from './pages/Inbound/InboundPage';
import { InvoicePage } from './pages/Invoice/InvoicePage';
import { KardexPage } from './pages/Kardex/KardexPage';
import { OperationPage } from './pages/Operation/OperationPage';
import { OutboundPage } from './pages/Outbound/OutboundPage';
import { PositionPage } from './pages/Position/PositionPage';
import { RolePage } from './pages/Role/RolePage';
import { SaleConsultPage } from './pages/SaleConsult/SaleConsultPage';
import { TransferPage } from './pages/Transfer/TransferPage';
import { UserPage } from './pages/User/UserPage';
import { VoucherSeriesPage } from './pages/VoucherSeries/VoucherSeriesPage';
import { VoucherTypePage } from './pages/VoucherType/VoucherTypePage';
import { WarehousePage } from './pages/Warehouse/WarehousePage';
import { PriceListPage } from './pages/PriceList/PriceListPage';
import { SupplierPage } from './pages/Supplier/SupplierPage';
import { SalePage } from './pages/Sale/SalePage';
import { UnitOfMeasurePage } from './pages/UnitOfMeasure/UnitOfMeasurePage';
import { CashRegisterDashboard } from './features/cashRegister/pages/CashRegisterDashboard';
import { CashRegisterOpen } from './features/cashRegister/pages/CashRegisterOpen';
import { CashRegisterClose } from './features/cashRegister/pages/CashRegisterClose';
import { CashMovements } from './features/cashRegister/pages/CashMovements';
import { PaymentMethodPage } from './pages/PaymentMethod/PaymentMethodPage';
import Assistant from './features/assistant/Assistant';
import { CashRegisterPage } from './pages/CashRegister/CashRegisterPage';
import { CashMovementCategoryPage } from './pages/CashMovementCategory/CashMovementCategoryPage';
import { CashRegisterShiftsPage } from './pages/CashRegisterShifts/CashRegisterShiftsPage';

const routes = [
  {
    path: '/dashboard',
    element: DashboardPage,
  },
  {
    path: '/product',
    element: ProductPage,
  },
  {
    path: '/branch',
    element: BranchPage,
  },
  {
    path: '/cashbox',
    element: CashRegisterDashboard,
  },
  {
    path: '/cash-register',
    element: CashRegisterPage,
  },
  {
    path: '/cash-register/:id/open',
    element: CashRegisterOpen,
  },
  {
    path: '/cash-register/:id/close',
    element: CashRegisterClose,
  },
  {
    path: '/cash-shift/:id/movements',
    element: CashMovements,
  },
  {
    path: '/cash-register/:id/shifts',
    element: CashRegisterShiftsPage,
  },
  {
    path: '/cash-movement-category',
    element: CashMovementCategoryPage,
  },
  {
    path: '/category',
    element: CategoryPage,
  },
  {
    path: '/company',
    element: CompanyPage,
  },
  {
    path: '/config',
    element: ConfigPage,
  },
  {
    path: '/credit-note',
    element: CreditNotePage,
  },
  {
    path: '/customer',
    element: CustomerPage,
  },
  {
    path: '/department',
    element: DepartmentPage,
  },
  {
    path: '/employee',
    element: EmployeePage,
  },
  {
    path: '/inbound',
    element: InboundPage,
  },
  {
    path: '/invoice',
    element: InvoicePage,
  },
  {
    path: '/kardex',
    element: KardexPage,
  },
  {
    path: '/operation',
    element: OperationPage,
  },
  {
    path: '/outbound',
    element: OutboundPage,
  },
  {
    path: '/position',
    element: PositionPage,
  },
  {
    path: '/payment-method',
    element: PaymentMethodPage,
  },
  {
    path: '/role',
    element: RolePage,
  },
  {
    path: '/sale',
    element: SalePage,
  },
  {
    path: '/sale-consult',
    element: SaleConsultPage,
  },
  {
    path: '/transfer',
    element: TransferPage,
  },
  {
    path: '/user',
    element: UserPage,
  },
  {
    path: '/voucher-series',
    element: VoucherSeriesPage,
  },
  {
    path: '/voucher-type',
    element: VoucherTypePage,
  },
  {
    path: '/warehouse',
    element: WarehousePage,
  },
  {
    path: '/price-list',
    element: PriceListPage,
  },
  {
    path: '/supplier',
    element: SupplierPage,
  },
  {
    path: '/unit-of-measure',
    element: UnitOfMeasurePage,
  },
  {
    path: '/assistant',
    element: Assistant,
  },
];

export const appRoutes = routes.map((route) => {
  return {
    ...route,
    element: withNavigationWatcher(route.element, route.path),
  };
});

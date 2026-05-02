import type { AppRouteDefinition } from '@app/router/types';
import { customerRoutes } from '@features/customer/routes';
import { productRoutes } from '@features/product/routes';
import {
  CategoryPage,
  KardexPage,
  OperationPage,
  PaymentMethodPage,
  PriceListPage,
  ReceiptPage,
  ReorderPage,
  StockAdjustmentPage,
  StockItemPage,
  SunatCatalogPage,
  SupplierPage,
  UnitOfMeasurePage,
  VoucherSeriesPage,
  VoucherTypePage,
  WarehousePage,
} from './route-pages';

export const catalogRoutes: AppRouteDefinition[] = [
  ...productRoutes,
  { path: '/stock',            element: StockItemPage },
  { path: '/kardex',           element: KardexPage },
  { path: '/reorder',          element: ReorderPage },
  { path: '/stock-adjustment', element: StockAdjustmentPage },
  { path: '/receipt',          element: ReceiptPage },
  { path: '/category',         element: CategoryPage },
  ...customerRoutes,
  { path: '/warehouse', element: WarehousePage },
  { path: '/price-list', element: PriceListPage },
  { path: '/supplier', element: SupplierPage },
  { path: '/unit-of-measure', element: UnitOfMeasurePage },
  { path: '/sunat-catalog', element: SunatCatalogPage },
  { path: '/voucher-series', element: VoucherSeriesPage },
  { path: '/voucher-type', element: VoucherTypePage },
  { path: '/payment-method', element: PaymentMethodPage },
  { path: '/operation', element: OperationPage },
];

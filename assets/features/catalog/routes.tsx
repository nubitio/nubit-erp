import type { AppRouteDefinition } from '@app/router/types';
import { customerRoutes } from '@features/customer/routes';
import { productRoutes } from '@features/product/routes';
import {
  CategoryPage,
  OperationPage,
  PaymentMethodPage,
  PriceListPage,
  SunatCatalogPage,
  SupplierPage,
  StockItemPage,
  UnitOfMeasurePage,
  VoucherSeriesPage,
  VoucherTypePage,
  WarehousePage,
} from './route-pages';

export const catalogRoutes: AppRouteDefinition[] = [
  ...productRoutes,
  { path: '/stock', element: StockItemPage },
  { path: '/category', element: CategoryPage },
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

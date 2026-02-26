import type { AppRouteDefinition } from '@app/router/types';
import {
  CategoryPage,
  CustomerPage,
  OperationPage,
  PaymentMethodPage,
  PriceListPage,
  ProductPage,
  SunatCatalogPage,
  SupplierPage,
  UnitOfMeasurePage,
  VoucherSeriesPage,
  VoucherTypePage,
  WarehousePage,
} from './route-pages';

export const catalogRoutes: AppRouteDefinition[] = [
  { path: '/product', element: ProductPage },
  { path: '/category', element: CategoryPage },
  { path: '/customer', element: CustomerPage },
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

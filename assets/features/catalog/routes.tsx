import type { AppRouteDefinition } from '@app/router/types';
import { lazyNamed } from '@app/router/lazy';

const ProductPage = lazyNamed(() => import('@pages/Product/ProductPage'), 'ProductPage');
const CategoryPage = lazyNamed(() => import('@pages/Category/CategoryPage'), 'CategoryPage');
const CustomerPage = lazyNamed(() => import('@pages/Customer/CustomerPage'), 'CustomerPage');
const WarehousePage = lazyNamed(() => import('@pages/Warehouse/WarehousePage'), 'WarehousePage');
const PriceListPage = lazyNamed(() => import('@pages/PriceList/PriceListPage'), 'PriceListPage');
const SupplierPage = lazyNamed(() => import('@pages/Supplier/SupplierPage'), 'SupplierPage');
const UnitOfMeasurePage = lazyNamed(
  () => import('@pages/UnitOfMeasure/UnitOfMeasurePage'),
  'UnitOfMeasurePage',
);
const SunatCatalogPage = lazyNamed(
  () => import('@pages/SunatCatalog/SunatCatalogPage'),
  'SunatCatalogPage',
);
const VoucherSeriesPage = lazyNamed(
  () => import('@pages/VoucherSeries/VoucherSeriesPage'),
  'VoucherSeriesPage',
);
const VoucherTypePage = lazyNamed(() => import('@pages/VoucherType/VoucherTypePage'), 'VoucherTypePage');
const PaymentMethodPage = lazyNamed(
  () => import('@pages/PaymentMethod/PaymentMethodPage'),
  'PaymentMethodPage',
);
const OperationPage = lazyNamed(() => import('@pages/Operation/OperationPage'), 'OperationPage');

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

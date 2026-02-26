import { lazyNamed } from '@app/router/lazy';

export const ProductPage = lazyNamed(
  () => import('@features/catalog/pages/ProductPage'),
  'ProductPage',
);
export const CategoryPage = lazyNamed(
  () => import('@features/catalog/pages/CategoryPage'),
  'CategoryPage',
);
export const CustomerPage = lazyNamed(
  () => import('@features/catalog/pages/CustomerPage'),
  'CustomerPage',
);
export const WarehousePage = lazyNamed(
  () => import('@features/catalog/pages/WarehousePage'),
  'WarehousePage',
);
export const PriceListPage = lazyNamed(
  () => import('@features/catalog/pages/PriceListPage'),
  'PriceListPage',
);
export const SupplierPage = lazyNamed(
  () => import('@features/catalog/pages/SupplierPage'),
  'SupplierPage',
);
export const UnitOfMeasurePage = lazyNamed(
  () => import('@features/catalog/pages/UnitOfMeasurePage'),
  'UnitOfMeasurePage',
);
export const SunatCatalogPage = lazyNamed(
  () => import('@features/catalog/pages/SunatCatalogPage'),
  'SunatCatalogPage',
);
export const VoucherSeriesPage = lazyNamed(
  () => import('@features/catalog/pages/VoucherSeriesPage'),
  'VoucherSeriesPage',
);
export const VoucherTypePage = lazyNamed(
  () => import('@features/catalog/pages/VoucherTypePage'),
  'VoucherTypePage',
);
export const PaymentMethodPage = lazyNamed(
  () => import('@features/catalog/pages/PaymentMethodPage'),
  'PaymentMethodPage',
);
export const OperationPage = lazyNamed(
  () => import('@features/catalog/pages/OperationPage'),
  'OperationPage',
);

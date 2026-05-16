import { lazyNamed } from '@app/router/lazy';

export const KardexPage = lazyNamed(
  () => import('@features/kardex/KardexPage'),
  'KardexPage',
);

export const StockAdjustmentPage = lazyNamed(
  () => import('@features/stockEntry/StockEntryPage'),
  'AdjustmentPage',
);

export const ReceiptPage = lazyNamed(
  () => import('@features/stockEntry/StockEntryPage'),
  'ReceiptPage',
);

export const ReorderPage = lazyNamed(
  () => import('@features/reorder/ReorderPage'),
  'ReorderPage',
);

export const CategoryPage = lazyNamed(
  () => import('@features/catalog/CategoryPage'),
  'CategoryPage',
);
export const WarehousePage = lazyNamed(
  () => import('@features/catalog/WarehousePage'),
  'WarehousePage',
);
export const StockItemPage = lazyNamed(
  () => import('@features/catalog/StockItemPage'),
  'StockItemPage',
);
export const PriceListPage = lazyNamed(
  () => import('@features/catalog/PriceListPage'),
  'PriceListPage',
);
export const SupplierPage = lazyNamed(
  () => import('@features/catalog/SupplierPage'),
  'SupplierPage',
);
export const UnitOfMeasurePage = lazyNamed(
  () => import('@features/catalog/UnitOfMeasurePage'),
  'UnitOfMeasurePage',
);
export const SunatCatalogPage = lazyNamed(
  () => import('@features/catalog/SunatCatalogPage'),
  'SunatCatalogPage',
);
export const VoucherSeriesPage = lazyNamed(
  () => import('@features/catalog/VoucherSeriesPage'),
  'VoucherSeriesPage',
);
export const VoucherTypePage = lazyNamed(
  () => import('@features/catalog/VoucherTypePage'),
  'VoucherTypePage',
);
export const PaymentMethodPage = lazyNamed(
  () => import('@features/catalog/PaymentMethodPage'),
  'PaymentMethodPage',
);
export const OperationPage = lazyNamed(
  () => import('@features/catalog/OperationPage'),
  'OperationPage',
);

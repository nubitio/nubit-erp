import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';
import { customerRoutes } from '@features/customer/routes';
import { productRoutes } from '@features/product/routes';

const KardexPage = lazyNamed(() => import('@features/kardex/KardexPage'), 'KardexPage');
const StockAdjustmentPage = lazyNamed(
  () => import('@features/stockEntry/StockEntryPage'),
  'AdjustmentPage',
);
const ReceiptPage = lazyNamed(() => import('@features/stockEntry/StockEntryPage'), 'ReceiptPage');
const ReorderPage = lazyNamed(() => import('@features/reorder/ReorderPage'), 'ReorderPage');
const CategoryPage = lazyNamed(() => import('./CategoryPage'), 'CategoryPage');
const WarehousePage = lazyNamed(() => import('./WarehousePage'), 'WarehousePage');
const StockItemPage = lazyNamed(() => import('./StockItemPage'), 'StockItemPage');
const PriceListPage = lazyNamed(() => import('./PriceListPage'), 'PriceListPage');
const SupplierPage = lazyNamed(() => import('./SupplierPage'), 'SupplierPage');
const UnitOfMeasurePage = lazyNamed(() => import('./UnitOfMeasurePage'), 'UnitOfMeasurePage');
const SunatCatalogPage = lazyNamed(() => import('./SunatCatalogPage'), 'SunatCatalogPage');
const VoucherSeriesPage = lazyNamed(() => import('./VoucherSeriesPage'), 'VoucherSeriesPage');
const VoucherTypePage = lazyNamed(() => import('./VoucherTypePage'), 'VoucherTypePage');
const PaymentMethodPage = lazyNamed(() => import('./PaymentMethodPage'), 'PaymentMethodPage');
const OperationPage = lazyNamed(() => import('./OperationPage'), 'OperationPage');

export const catalogRoutes: AppRouteDefinition[] = [
  ...productRoutes,
  { path: '/stock', element: StockItemPage },
  { path: '/kardex', element: KardexPage },
  { path: '/reorder', element: ReorderPage },
  { path: '/stock-adjustment', element: StockAdjustmentPage },
  { path: '/receipt', element: ReceiptPage },
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

import { Navigate } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';
import { crudRoute } from '@app/router/crudRoute';
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
const PriceListPage = lazyNamed(() => import('./PriceListPage'), 'PriceListPage');
const SupplierPage = lazyNamed(() => import('./SupplierPage'), 'SupplierPage');
const UnitOfMeasurePage = lazyNamed(() => import('./UnitOfMeasurePage'), 'UnitOfMeasurePage');
const SunatCatalogPage = lazyNamed(() => import('./SunatCatalogPage'), 'SunatCatalogPage');
const VoucherSeriesPage = lazyNamed(() => import('./VoucherSeriesPage'), 'VoucherSeriesPage');
const VoucherTypePage = lazyNamed(() => import('./VoucherTypePage'), 'VoucherTypePage');
const PaymentMethodPage = lazyNamed(() => import('./PaymentMethodPage'), 'PaymentMethodPage');
const OperationPage = lazyNamed(() => import('./OperationPage'), 'OperationPage');
const DespatchGuidePage = lazyNamed(() => import('./DespatchGuidePage'), 'DespatchGuidePage');

const StockRedirect = () => <Navigate to="/inventory/stock" replace />;

export const catalogRoutes: AppRouteDefinition[] = [
  ...productRoutes,
  { path: '/stock', element: StockRedirect },
  { path: '/kardex', element: KardexPage },
  { path: '/reorder', element: ReorderPage },
  ...crudRoute('/stock-adjustment', StockAdjustmentPage),
  ...crudRoute('/receipt', ReceiptPage),
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
  { path: '/despatch-guide', element: DespatchGuidePage },
];

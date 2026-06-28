import { Navigate } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';
import { crudRoute } from '@app/router/crudRoute';
import type { AppRouteDefinition } from '@app/router/types';
import { customerRoutes } from '@features/customer/routes';
import { CatalogHubLayout } from './CatalogHubLayout';

const ProductPage = lazyNamed(() => import('@features/product/ProductPage'), 'ProductPage');
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
const ProductRedirect = () => <Navigate to="/catalog/products" replace />;
const CategoryRedirect = () => <Navigate to="/catalog/categories" replace />;
const PriceListRedirect = () => <Navigate to="/catalog/prices" replace />;
const SupplierRedirect = () => <Navigate to="/catalog/suppliers" replace />;
const UnitOfMeasureRedirect = () => <Navigate to="/catalog/units" replace />;
const VoucherSeriesRedirect = () => <Navigate to="/catalog/series" replace />;

export const catalogRoutes: AppRouteDefinition[] = [
  { path: '/product', element: ProductRedirect },
  { path: '/category', element: CategoryRedirect },
  { path: '/price-list', element: PriceListRedirect },
  { path: '/supplier', element: SupplierRedirect },
  { path: '/unit-of-measure', element: UnitOfMeasureRedirect },
  { path: '/voucher-series', element: VoucherSeriesRedirect },
  {
    path: '/catalog',
    element: CatalogHubLayout,
    children: [
      { path: 'products', element: ProductPage },
      { path: 'categories', element: CategoryPage },
      { path: 'units', element: UnitOfMeasurePage },
      { path: 'prices', element: PriceListPage },
      { path: 'suppliers', element: SupplierPage },
      { path: 'series', element: VoucherSeriesPage },
    ],
  },
  { path: '/stock', element: StockRedirect },
  { path: '/kardex', element: KardexPage },
  { path: '/reorder', element: ReorderPage },
  ...crudRoute('/stock-adjustment', StockAdjustmentPage),
  ...crudRoute('/receipt', ReceiptPage),
  ...customerRoutes,
  { path: '/warehouse', element: WarehousePage },
  { path: '/sunat-catalog', element: SunatCatalogPage },
  { path: '/voucher-type', element: VoucherTypePage },
  { path: '/payment-method', element: PaymentMethodPage },
  { path: '/operation', element: OperationPage },
  { path: '/despatch-guide', element: DespatchGuidePage },
];

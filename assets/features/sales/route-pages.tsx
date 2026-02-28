import { lazyNamed } from '@app/router/lazy';

export const CreditNotePage = lazyNamed(
  () => import('@features/sales/pages/CreditNotePage'),
  'CreditNotePage',
);
export const InboundPage = lazyNamed(() => import('@features/sales/pages/InboundPage'), 'InboundPage');
export const PurchasesPage = lazyNamed(() => import('@features/sales/pages/InboundPage'), 'PurchasesPage');
export const InvoicePage = lazyNamed(() => import('@features/sales/pages/InvoicePage'), 'InvoicePage');
export const KardexPage = lazyNamed(() => import('@features/sales/pages/KardexPage'), 'KardexPage');
export const OutboundPage = lazyNamed(
  () => import('@features/sales/pages/OutboundPage'),
  'OutboundPage',
);
export const SalePage = lazyNamed(() => import('@features/sales/pages/SalePage'), 'SalePage');
export const SaleConsultPage = lazyNamed(
  () => import('@features/sales/pages/SaleConsultPage'),
  'SaleConsultPage',
);
export const TransferPage = lazyNamed(
  () => import('@features/sales/pages/TransferPage'),
  'TransferPage',
);

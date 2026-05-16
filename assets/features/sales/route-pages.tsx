import { lazyNamed } from '@app/router/lazy';

export const CreditNotePage = lazyNamed(
  () => import('@features/sales/CreditNotePage'),
  'CreditNotePage',
);
export const SaleConsultPage = lazyNamed(
  () => import('@features/sales/SaleConsultPage'),
  'SaleConsultPage',
);

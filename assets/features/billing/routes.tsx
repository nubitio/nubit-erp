import { Navigate, useParams } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';
import { BillingHubLayout } from './BillingHubLayout';

const SaleDetailPage = lazyNamed(
  () => import('@features/sales/detail/SaleDetailPage'),
  'SaleDetailPage',
);
const NewInvoicePage = lazyNamed(
  () => import('@features/sales/invoice/NewInvoicePage'),
  'NewInvoicePage',
);
const SalePage = lazyNamed(() => import('@features/sales/SalePage'), 'SalePage');
const InvoicePage = lazyNamed(() => import('@features/sales/InvoicePage'), 'InvoicePage');
const CreditNotePage = lazyNamed(() => import('@features/sales/CreditNotePage'), 'CreditNotePage');
const CreditNoteIssuePage = lazyNamed(
  () => import('@features/sales/creditNote/CreditNoteIssuePage'),
  'CreditNoteIssuePage',
);
const SaleConsultPage = lazyNamed(
  () => import('@features/sales/SaleConsultPage'),
  'SaleConsultPage',
);
const AccountsReceivablePage = lazyNamed(
  () => import('@features/sales/AccountsReceivablePage'),
  'AccountsReceivablePage',
);

const PriceReviewPage = lazyNamed(() => import('./priceReview/PriceReviewPage'), 'PriceReviewPage');
const SalesRedirect = () => <Navigate to="/billing/sales" replace />;
const ConsultRedirect = () => <Navigate to="/billing/consult" replace />;

const LegacyCrudRedirect = ({ basePath }: { basePath: string }) => {
  const { id } = useParams();
  return <Navigate to={id ? `${basePath}/${id}` : basePath} replace />;
};

export const billingRoutes: AppRouteDefinition[] = [
  { path: '/sale', element: SalesRedirect },
  { path: '/sale-consult', element: ConsultRedirect },
  { path: '/accounts-receivable', element: AccountsReceivablePage },
  { path: '/invoice', element: () => <LegacyCrudRedirect basePath="/billing/documents" /> },
  { path: '/invoice/:id?', element: () => <LegacyCrudRedirect basePath="/billing/documents" /> },
  { path: '/credit-note', element: () => <LegacyCrudRedirect basePath="/billing/credit-notes" /> },
  {
    path: '/credit-note/:id?',
    element: () => <LegacyCrudRedirect basePath="/billing/credit-notes" />,
  },
  {
    path: '/billing',
    element: BillingHubLayout,
    children: [
      { path: 'sales', element: SalePage },
      { path: 'sales/new', element: NewInvoicePage },
      { path: 'sales/:id', element: SaleDetailPage },
      { path: 'consult', element: SaleConsultPage },
      { path: 'price-review', element: PriceReviewPage },
      { path: 'documents', element: InvoicePage },
      { path: 'documents/:id?', element: InvoicePage },
      { path: 'credit-notes/new', element: CreditNoteIssuePage },
      { path: 'credit-notes/new/:documentId', element: CreditNoteIssuePage },
      { path: 'credit-notes', element: CreditNotePage },
      { path: 'credit-notes/:id?', element: CreditNotePage },
    ],
  },
];

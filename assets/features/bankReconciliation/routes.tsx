import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const BankAccountPage = lazyNamed(() => import('./BankAccountPage'), 'BankAccountPage');
const BankStatementImportPage = lazyNamed(
  () => import('./BankStatementImportPage'),
  'BankStatementImportPage',
);
const BankStatementImportUpload = lazyNamed(
  () => import('./BankStatementImportUpload'),
  'BankStatementImportUpload',
);
const BankReconciliationScreen = lazyNamed(
  () => import('./BankReconciliationScreen'),
  'BankReconciliationScreen',
);

export const bankReconciliationRoutes: AppRouteDefinition[] = [
  { path: '/bank-accounts', element: BankAccountPage },
  { path: '/bank-statement-imports', element: BankStatementImportPage },
  { path: '/bank-statement-imports/new', element: BankStatementImportUpload },
  { path: '/bank-reconciliation', element: BankReconciliationScreen },
  { path: '/bank-reconciliation/:importId', element: BankReconciliationScreen },
];

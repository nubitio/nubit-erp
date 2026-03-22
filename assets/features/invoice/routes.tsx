import type { AppRouteDefinition } from '@app/router/types';
import { InvoicePage } from './route-pages';

export const invoiceRoutes: AppRouteDefinition[] = [{ path: '/invoice', element: InvoicePage }];

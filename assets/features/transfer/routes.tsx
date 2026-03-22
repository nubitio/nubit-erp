import type { AppRouteDefinition } from '@app/router/types';
import { TransferPage } from './route-pages';

export const transferRoutes: AppRouteDefinition[] = [{ path: '/transfer', element: TransferPage }];

import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const TreasuryPage = lazyNamed(() => import('./TreasuryPage'), 'TreasuryPage');

export const treasuryRoutes: AppRouteDefinition[] = [{ path: '/treasury', element: TreasuryPage }];

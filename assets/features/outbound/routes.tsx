import type { AppRouteDefinition } from '@app/router/types';
import { OutboundPage } from './route-pages';

export const outboundRoutes: AppRouteDefinition[] = [{ path: '/outbound', element: OutboundPage }];

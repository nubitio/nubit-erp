import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const FixedAssetPage = lazyNamed(() => import('./FixedAssetPage'), 'FixedAssetPage');

export const fixedAssetsRoutes: AppRouteDefinition[] = [
  { path: '/fixed-assets', element: FixedAssetPage },
];

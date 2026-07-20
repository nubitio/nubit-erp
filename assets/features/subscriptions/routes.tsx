import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';
import { SubscriptionsHubLayout } from './SubscriptionsHubLayout';

const SubscriptionPlanPage = lazyNamed(() => import('./SubscriptionPlanPage'), 'SubscriptionPlanPage');
const CustomerSubscriptionPage = lazyNamed(
  () => import('./CustomerSubscriptionPage'),
  'CustomerSubscriptionPage',
);

export const subscriptionsRoutes: AppRouteDefinition[] = [
  {
    path: '/subscriptions',
    element: SubscriptionsHubLayout,
    children: [
      { path: 'plans', element: SubscriptionPlanPage },
      { path: 'customer-subscriptions', element: CustomerSubscriptionPage },
    ],
  },
];

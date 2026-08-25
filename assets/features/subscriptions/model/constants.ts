import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { createCrudEvents } from '@nubitio/crud';

export const subscriptionPlanEvents = createCrudEvents('subscription-plan');
export const subscriptionPlanApiUrl = `${API}subscription-plans`;

export const customerSubscriptionEvents = createCrudEvents('customer-subscription');
export const customerSubscriptionRunApiUrl = API + 'customer-subscription-runs';
export const customerSubscriptionLineApiUrl = API + 'customer-subscription-lines';
export const customerSubscriptionApiUrl = `${API}customer-subscriptions`;

export { subscriptionPlanFormLayout, customerSubscriptionFormLayout } from './formLayout';

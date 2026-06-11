import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { createCrudEvents } from '@nubitio/crud';

export const customerEvents = createCrudEvents('customer');
export const customerApiUrl = `${API}customers`;

export { customerFormLayout } from './formLayout';

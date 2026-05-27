import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import { createCrudEvents } from '@nubit/crud';

export const customerEvents = createCrudEvents('customer');
export const customerApiUrl = `${API}customers`;

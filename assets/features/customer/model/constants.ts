import { API_BASE_URL } from '@app/constants';
import { createCrudEvents } from '@nubit/crud';

export const customerEvents = createCrudEvents('customer');
export const customerApiUrl = `${API_BASE_URL}customers`;

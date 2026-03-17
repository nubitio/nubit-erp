import { Constants } from '@core/common/Constants';
import { createCrudEvents } from '@features/movement';

export const customerEvents = createCrudEvents('customer');
export const customerApiUrl = `${Constants.API_BASE_URL}customers`;

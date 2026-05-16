import { Constants } from '@core/common/Constants';
import { createCrudEvents } from '@core/event/createCrudEvents';

export const customerEvents = createCrudEvents('customer');
export const customerApiUrl = `${Constants.API_BASE_URL}customers`;

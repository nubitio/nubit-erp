import { SmartCrudPage, defineResource } from '@core/crud';
import { customerApiUrl, customerEvents } from '@features/customer';

const customerResource = defineResource({
  id: 'customer',
  title: 'Clientes',
  apiUrl: customerApiUrl,
  fields: [],
  events: customerEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});

export const CustomerPage = () => <SmartCrudPage resource={customerResource} />;

// Static properties consumed by SalePage — must stay as module-level singletons.
CustomerPage.EVENTS = customerEvents;
CustomerPage.API_URL = customerApiUrl;

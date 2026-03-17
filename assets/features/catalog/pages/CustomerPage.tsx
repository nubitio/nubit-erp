import './CustomerPage.scss';
import { CrudPage, defineResource } from '@core/crud';
import { customerApiUrl, customerEvents } from '@features/customer';
import { CustomerFields } from './CustomerFields';

const customerResource = defineResource({
  id: 'customer',
  title: 'Clientes',
  apiUrl: customerApiUrl,
  fields: (formRef) => CustomerFields(formRef),
  events: customerEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});

export const CustomerPage = () => <CrudPage resource={customerResource} />;

// Static properties consumed by SalePage — must stay as module-level singletons.
CustomerPage.EVENTS = customerEvents;
CustomerPage.API_URL = customerApiUrl;

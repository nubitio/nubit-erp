import { SmartCrudPage, defineResource } from '@nubit/crud';
import { paymentMethodEvents } from '@app/domainEvents';
import { paymentMethodEndpoints } from './api/catalogEndpoints';

const resource = defineResource({
  id: 'payment-methods',
  title: 'Medios de pago',
  apiUrl: paymentMethodEndpoints.list(),
  fields: [],
  events: paymentMethodEvents,
});
export const PaymentMethodPage = () => <SmartCrudPage resource={resource} />;

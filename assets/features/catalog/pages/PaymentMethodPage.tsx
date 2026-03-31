import { SmartCrudPage, defineResource } from '@core/crud';
import { paymentMethodEvents } from '@features/paymentMethod';
import { paymentMethodEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'payment-methods',
  title: 'Medios de pago',
  apiUrl: paymentMethodEndpoints.list(),
  fields: [],
  events: paymentMethodEvents,
});
export const PaymentMethodPage = () => <SmartCrudPage resource={resource} />;

import { SmartCrudPage, defineResource } from '@nubit/crud';
import { paymentMethodEndpoints } from './api/catalogEndpoints';

const resource = defineResource(paymentMethodEndpoints.list(), {
  id: 'payment-methods',
  title: 'Medios de pago',
});
export const PaymentMethodPage = () => <SmartCrudPage resource={resource} />;

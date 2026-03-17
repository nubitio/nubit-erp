import './PaymentMethodPage.scss';
import { CrudPage, defineResource } from '@core/crud';
import { paymentMethodEvents } from '@features/paymentMethod';
import { PaymentMethodFields } from './PaymentMethodFields';
import { paymentMethodEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'payment-methods',
  title: 'Medios de pago',
  apiUrl: paymentMethodEndpoints.list(),
  fields: PaymentMethodFields(),
  events: paymentMethodEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const PaymentMethodPage = () => <CrudPage resource={resource} />;

import { SmartCrudPage, defineFields, defineResource, type DataRecord } from '@nubitio/crud';
import { paymentMethodEndpoints } from './api/catalogEndpoints';

interface PaymentMethodRecord extends DataRecord {
  id: string;
  name: string;
}

const resource = defineResource<PaymentMethodRecord>(paymentMethodEndpoints.list(), {
  id: 'payment-methods',
  title: 'Medios de pago',
  viewMode: 'dialog',
  emptyState: {
    title: 'Sin medios de pago',
    description: 'Registra las formas de pago aceptadas en ventas y caja.',
    icon: 'credit-card',
  },
  fieldContract: defineFields<PaymentMethodRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'name', patch: { order: 0, width: 320, label: 'Nombre' } },
    ],
  }),
});

export const PaymentMethodPage = () => <SmartCrudPage resource={resource} />;
import {
  defineFields,
  defineResource,
  enumField,
  FieldType,
  type DataRecord,
  type GridCellContext,
} from '@nubitio/crud';
import { SchemaCrudPage } from '@nubitio/crud';
import { paymentMethodEndpoints } from './api/catalogEndpoints';
import { SharedUtils } from '@shared/lib/SharedUtils';

interface PaymentMethodRecord extends DataRecord {
  id: string;
  name: string;
  isActive: boolean;
  settlementBehavior: 'COLLECTED' | 'RECEIVABLE' | 'ADVANCE';
}

const SETTLEMENT_OPTIONS = [
  { value: 'COLLECTED', text: 'Cobro inmediato' },
  { value: 'RECEIVABLE', text: 'Por cobrar (crédito)' },
  { value: 'ADVANCE', text: 'Anticipo de cliente' },
];

const { name: _settlementName, ...settlementPatch } = enumField(SETTLEMENT_OPTIONS)
  .name('settlementBehavior')
  .label('Forma de liquidación')
  .helpText(
    'Cobro inmediato: el dinero ingresa al registrar la venta. Por cobrar: queda como deuda del cliente. Anticipo: aplica un adelanto ya recibido.',
  )
  .required(true)
  .build();
void _settlementName;

const resource = defineResource<PaymentMethodRecord>(paymentMethodEndpoints.list(), {
  id: 'payment-methods',
  title: 'Métodos de pago',
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
      {
        kind: 'override',
        key: 'isActive',
        patch: {
          order: 1,
          width: 100,
          label: 'Activo',
          type: FieldType.SWITCH,
          formatter: (cell: GridCellContext) => SharedUtils.booleanFormatter(Boolean(cell.value)),
        },
      },
      {
        kind: 'override',
        key: 'settlementBehavior',
        patch: { ...settlementPatch, order: 2, width: 200 },
      },
    ],
  }),
});

export const PaymentMethodPage = () => <SchemaCrudPage resource={resource} />;

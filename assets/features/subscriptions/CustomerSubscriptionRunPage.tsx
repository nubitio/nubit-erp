import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  type DataRecord,
  type GridCellContext,
} from '@nubitio/crud';
import { Link } from 'react-router-dom';
import { customerSubscriptionRunApiUrl } from './model/constants';

interface SubscriptionRunRecord extends DataRecord {
  id: string;
  subscription: unknown;
  periodStart: string;
  periodEnd: string;
  source: string;
  status: string;
  saleId: string;
  createdAt: string;
}

const resource = defineResource<SubscriptionRunRecord>(customerSubscriptionRunApiUrl, {
  id: 'customer-subscription-runs',
  title: 'Historial de emisiones',
  defaultPreset: 'basic',
  columnPresets: [
    {
      key: 'basic',
      label: 'Básico',
      columns: [
        'subscription',
        'periodStart',
        'periodEnd',
        'source',
        'status',
        'saleId',
        'createdAt',
      ],
    },
  ],
  permissions: { canAdd: false, canEdit: false, canDelete: false },
  emptyState: {
    title: 'Sin cargos generados',
    description: 'Los cargos automáticos y manuales aparecerán aquí con su período y venta.',
    icon: 'clock-counter-clockwise',
  },
  fieldContract: defineFields<SubscriptionRunRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'subscription', patch: { label: 'Suscripción', order: 0 } },
      { kind: 'override', key: 'periodStart', patch: { label: 'Desde', order: 1 } },
      { kind: 'override', key: 'periodEnd', patch: { label: 'Hasta', order: 2 } },
      { kind: 'override', key: 'source', patch: { label: 'Origen', order: 3 } },
      { kind: 'override', key: 'status', patch: { label: 'Estado', order: 4 } },
      {
        kind: 'override',
        key: 'saleId',
        patch: {
          label: 'Venta',
          order: 5,
          formatter: (cell: GridCellContext) => (
            <Link to={'/billing/sales?highlight=' + encodeURIComponent(String(cell.value))}>
              Ver venta
            </Link>
          ),
        },
      },
      { kind: 'override', key: 'createdAt', patch: { label: 'Generado', order: 6 } },
    ],
  }),
});

export const CustomerSubscriptionRunPage = () => <SchemaCrudPage resource={resource} />;

CustomerSubscriptionRunPage.API_URL = customerSubscriptionRunApiUrl;

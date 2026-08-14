import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  FieldType,
  type DataRecord,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { saleEndpoints } from './api/saleEndpoints';
import { useNavigate } from 'react-router-dom';

interface AccountsReceivableRecord extends DataRecord {
  id: string;
  customer: unknown;
  issuedDate: string;
  dueDate: string | null;
  grandTotal: string;
  paidTotal: string;
  balanceDue: string;
  status: string;
}

const ACCOUNTS_RECEIVABLE_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['customer', 'issuedDate', 'dueDate', 'grandTotal', 'balanceDue', 'status'],
  },
];

const accountsReceivableResource = defineResource<AccountsReceivableRecord>(saleEndpoints.list(), {
  id: 'accounts-receivable',
  title: 'Cuentas por cobrar',
  defaultPreset: 'basic',
  columnPresets: ACCOUNTS_RECEIVABLE_COLUMN_PRESETS,
  sort: [{ selector: 'dueDate', desc: false }],
  filter: [{ field: 'balanceDue', operator: '>', value: 0 }],
  permissions: { canAdd: false, canEdit: false, canView: false, canDelete: false },
  emptyState: {
    title: 'Sin cuentas por cobrar',
    description: 'Las ventas a crédito con saldo pendiente aparecerán aquí.',
    icon: 'hand-coins',
  },
  fieldContract: defineFields<AccountsReceivableRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      {
        kind: 'override',
        key: 'customer',
        patch: {
          order: 0,
          type: FieldType.ENTITY,
          url: API + 'customers',
          valueField: '_iri',
          textField: 'businessName',
          layoutHint: 'lookup',
          label: 'Cliente',
        },
      },
      { kind: 'override', key: 'issuedDate', patch: { order: 1, layoutHint: 'short', label: 'Fecha de emisión' } },
      { kind: 'override', key: 'dueDate', patch: { order: 2, layoutHint: 'short', label: 'Fecha de vencimiento' } },
      { kind: 'override', key: 'grandTotal', patch: { order: 3, layoutHint: 'short', label: 'Total' } },
      { kind: 'override', key: 'paidTotal', patch: { order: 4, layoutHint: 'short', label: 'Cobrado' } },
      { kind: 'override', key: 'balanceDue', patch: { order: 5, layoutHint: 'short', label: 'Saldo' } },
      { kind: 'override', key: 'status', patch: { order: 6, layoutHint: 'short', label: 'Estado' } },
    ],
  }),
});

export const AccountsReceivablePage = () => {
  const navigate = useNavigate();

  const resource = {
    ...accountsReceivableResource,
    rowActions: (row: AccountsReceivableRecord) => [
      {
        key: 'collect',
        text: 'Registrar cobro',
        icon: 'ph-hand-coins',
        type: 'success',
        onClick: () => navigate(`/sale/${row.id}/collect`),
      },
    ],
  };

  return <SchemaCrudPage resource={resource} />;
};

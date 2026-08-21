import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  enumField,
  FieldType,
  type DataRecord,
  type Field,
  type GridCellContext,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { supplierBillApiUrl, supplierBillEvents, supplierBillFormLayout } from './model/constants';
import { Badge } from '@nubitio/ui';
import { useNavigate } from 'react-router-dom';
import { dateCell, entityNameCell, moneyCell, payableStatusCell } from '@shared/ui/crudFormatters';
import { OwnerRowButtons } from '@shared/ui/OwnerRowButtons';
import { payableStatus } from '@shared/lib/displayFormat';

interface SupplierBillRecord extends DataRecord {
  id: string;
  supplier: unknown;
  branch: unknown;
  purchase: unknown;
  category: unknown;
  documentType: string | null;
  series: string | null;
  number: string | null;
  issueDate: string;
  dueDate: string;
  currency: string;
  amount: string;
  paidTotal: string;
  balanceDue: string;
  status: 'PENDING' | 'PARTIAL' | 'PAID' | 'VOID';
  note: string | null;
}

function toPatch(field: Field): SmartCrudFieldPatch<SupplierBillRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<SupplierBillRecord>;
}

const STATUS_BADGE: Record<SupplierBillRecord['status'], { variant: 'success' | 'warning' | 'secondary' | 'danger'; text: string }> = {
  PENDING: { variant: 'warning', text: 'Pendiente' },
  PARTIAL: { variant: 'secondary', text: 'Parcial' },
  PAID: { variant: 'success', text: 'Pagada' },
  VOID: { variant: 'danger', text: 'Anulada' },
};

const statusPatch = toPatch(
  enumField([
    { value: 'PENDING', text: 'Pendiente' },
    { value: 'PARTIAL', text: 'Parcial' },
    { value: 'PAID', text: 'Pagada' },
    { value: 'VOID', text: 'Anulada' },
  ])
    .name('status')
    .label('Estado')
    .formatter((cell: GridCellContext) => {
      const badge = STATUS_BADGE[cell.value as SupplierBillRecord['status']];
      if (!badge) return null;

      return <Badge variant={badge.variant} outlined>{badge.text}</Badge>;
    })
    .build(),
);

export const SupplierBillPage = () => {
  const navigate = useNavigate();

  const resource = {
    ...defineResource<SupplierBillRecord>(supplierBillApiUrl, {
  id: 'supplier-bill-actions',
  title: 'Cuentas por pagar',
  events: supplierBillEvents,
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  formLayout: supplierBillFormLayout,
  sort: [{ selector: 'dueDate', desc: false }],
  filter: [{ field: 'balanceDue', operator: '>', value: 0 }],
  stateStoring: false,
  emptyState: {
    title: 'Nada por pagar',
    description: 'Registra facturas de proveedores, alquiler o servicios para controlar vencimientos.',
    icon: 'invoice',
  },
  fieldContract: defineFields<SupplierBillRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      {
        kind: 'override',
        key: 'supplier',
        patch: {
          order: 0,
          type: FieldType.ENTITY,
          url: API + 'suppliers',
          valueField: '_iri',
          textField: 'businessName',
          layoutHint: 'lookup',
          label: 'Proveedor',
          formatter: entityNameCell,
        },
      },
      {
        kind: 'override',
        key: 'branch',
        patch: {
          order: 1,
          type: FieldType.ENTITY,
          url: API + 'branches',
          valueField: '_iri',
          textField: 'name',
          layoutHint: 'lookup',
          label: 'Sucursal',
          visible: false,
          visibleOnForm: true,
        },
      },
      {
        kind: 'override',
        key: 'category',
        patch: {
          order: 2,
          type: FieldType.ENTITY,
          url: API + 'expense-categories',
          valueField: '_iri',
          textField: 'name',
          layoutHint: 'lookup',
          label: 'Categoría de gasto',
          visible: false,
          visibleOnForm: true,
        },
      },
      {
        kind: 'override',
        key: 'purchase',
        patch: {
          order: 3,
          type: FieldType.ENTITY,
          url: API + 'purchases',
          valueField: '_iri',
          textField: 'receiptNo',
          layoutHint: 'lookup',
          label: 'Compra asociada',
          helpText: 'Solo si esta factura corresponde a una compra de mercadería ya registrada.',
          required: false,
          visible: false,
          visibleOnForm: true,
        },
      },
      { kind: 'override', key: 'documentType', patch: { order: 4, layoutHint: 'short', label: 'Tipo de documento', visible: false, visibleOnForm: true } },
      { kind: 'override', key: 'series', patch: { order: 5, layoutHint: 'short', label: 'Serie', visible: false, visibleOnForm: true } },
      { kind: 'override', key: 'number', patch: { order: 6, layoutHint: 'short', label: 'Número' } },
      { kind: 'override', key: 'issueDate', patch: { order: 7, layoutHint: 'short', label: 'Fecha de emisión', formatter: dateCell, visible: false, visibleOnForm: true } },
      { kind: 'override', key: 'dueDate', patch: { order: 8, layoutHint: 'short', label: 'Fecha de vencimiento', formatter: dateCell } },
      { kind: 'override', key: 'amount', patch: { order: 9, layoutHint: 'short', label: 'Monto', formatter: moneyCell, align: 'right' } },
      { kind: 'override', key: 'currency', patch: { order: 10, layoutHint: 'short', label: 'Moneda', visible: false, visibleOnForm: true } },
      { kind: 'override', key: 'paidTotal', patch: { order: 11, layoutHint: 'short', label: 'Pagado', disabled: true, formatter: moneyCell, visible: false } },
      { kind: 'override', key: 'balanceDue', patch: { order: 12, layoutHint: 'short', label: 'Saldo', disabled: true, formatter: moneyCell, align: 'right' } },
      { kind: 'override', key: 'status', patch: { ...statusPatch, order: 13, layoutHint: 'short', disabled: true, formatter: payableStatusCell } },
      { kind: 'override', key: 'note', patch: { order: 15, layoutHint: 'fullWidth', label: 'Notas', visible: false, visibleOnForm: true } },
      { kind: 'override', key: 'id', patch: { visible: false, visibleOnForm: false } },
      { kind: 'override', key: 'createdAt', patch: { visibleOnForm: false, visible: false } },
      {
        kind: 'append' as const,
        field: {
          name: '_pay',
          type: FieldType.TEXT,
          label: '',
          order: 16,
          width: 110,
          visible: true,
          filterable: false,
          sortable: false,
          visibleOnForm: false,
          formatter: (cell: GridCellContext) => {
            if (payableStatus(cell.data ?? {}) === 'paid') return null;
            const id = String(cell.data?.id ?? '');
            if (!id) return null;
            return (
              <OwnerRowButtons
                actions={[{ label: 'Pagar', onClick: () => navigate(`/supplier-bill/${id}/pay`) }]}
              />
            );
          },
        } as Field,
      },
    ],
  }),
  }),
    rowActions: (row: SupplierBillRecord) => {
      if (payableStatus(row) === 'paid' || row.status === 'VOID') {
        return [];
      }

      return [
        {
          key: 'pay',
          text: 'Pagar',
          icon: 'ph-hand-coins',
          type: 'success',
          onClick: () => navigate(`/supplier-bill/${row.id}/pay`),
        },
      ];
    },
  };

  return <SchemaCrudPage resource={resource} />;
};

SupplierBillPage.EVENTS = supplierBillEvents;
SupplierBillPage.API_URL = supplierBillApiUrl;

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

const SUPPLIER_BILL_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['supplier', 'category', 'dueDate', 'amount', 'balanceDue', 'status'],
  },
];

const supplierBillResource = defineResource<SupplierBillRecord>(supplierBillApiUrl, {
  id: 'supplier-bill',
  title: 'Facturas de proveedor',
  events: supplierBillEvents,
  defaultPreset: 'basic',
  columnPresets: SUPPLIER_BILL_COLUMN_PRESETS,
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  formLayout: supplierBillFormLayout,
  sort: [{ selector: 'dueDate', desc: false }],
  emptyState: {
    title: 'Sin facturas de proveedor',
    description: 'Registra facturas de servicios como comisiones bancarias, alquiler o compras.',
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
        },
      },
      { kind: 'override', key: 'documentType', patch: { order: 4, layoutHint: 'short', label: 'Tipo de documento' } },
      { kind: 'override', key: 'series', patch: { order: 5, layoutHint: 'short', label: 'Serie' } },
      { kind: 'override', key: 'number', patch: { order: 6, layoutHint: 'short', label: 'Número' } },
      { kind: 'override', key: 'issueDate', patch: { order: 7, layoutHint: 'short', label: 'Fecha de emisión' } },
      { kind: 'override', key: 'dueDate', patch: { order: 8, layoutHint: 'short', label: 'Fecha de vencimiento' } },
      { kind: 'override', key: 'amount', patch: { order: 9, layoutHint: 'short', label: 'Monto' } },
      { kind: 'override', key: 'currency', patch: { order: 10, layoutHint: 'short', label: 'Moneda' } },
      { kind: 'override', key: 'paidTotal', patch: { order: 11, layoutHint: 'short', label: 'Pagado', disabled: true } },
      { kind: 'override', key: 'balanceDue', patch: { order: 12, layoutHint: 'short', label: 'Saldo', disabled: true } },
      { kind: 'override', key: 'status', patch: { ...statusPatch, order: 13, layoutHint: 'short', disabled: true } },
      { kind: 'override', key: 'note', patch: { order: 14, layoutHint: 'fullWidth', label: 'Notas' } },
      { kind: 'override', key: 'createdAt', patch: { visibleOnForm: false, visible: false } },
    ],
  }),
});

export const SupplierBillPage = () => {
  const navigate = useNavigate();

  const resource = {
    ...supplierBillResource,
    rowActions: (row: SupplierBillRecord) => {
      if (row.status === 'PAID' || row.status === 'VOID') {
        return [];
      }

      return [
        {
          key: 'pay',
          text: 'Registrar pago',
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

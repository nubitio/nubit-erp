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
import { Badge } from '@nubitio/ui';
import { useNavigate } from 'react-router-dom';
import { payrollRunApiUrl, payrollRunEvents, payrollRunFormLayout } from './model/constants';

interface PayrollRunRecord extends DataRecord {
  id: string;
  company: unknown;
  period: string;
  type: 'REGULAR' | 'GRATIFICATION_JULY' | 'GRATIFICATION_DECEMBER' | 'CTS_MAY' | 'CTS_NOVEMBER' | 'LIQUIDACION';
  status: 'DRAFT' | 'CALCULATED' | 'APPROVED' | 'PAID';
}

function toPatch(field: Field): SmartCrudFieldPatch<PayrollRunRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<PayrollRunRecord>;
}

const TYPE_TEXT: Record<PayrollRunRecord['type'], string> = {
  REGULAR: 'Planilla regular',
  GRATIFICATION_JULY: 'Gratificación julio',
  GRATIFICATION_DECEMBER: 'Gratificación diciembre',
  CTS_MAY: 'CTS mayo',
  CTS_NOVEMBER: 'CTS noviembre',
  LIQUIDACION: 'Liquidación de beneficios sociales',
};

const STATUS_BADGE: Record<PayrollRunRecord['status'], { variant: 'success' | 'warning' | 'secondary' | 'danger'; text: string }> = {
  DRAFT: { variant: 'warning', text: 'Borrador' },
  CALCULATED: { variant: 'secondary', text: 'Calculada' },
  APPROVED: { variant: 'secondary', text: 'Aprobada' },
  PAID: { variant: 'success', text: 'Pagada' },
};

const typePatch = toPatch(
  enumField(Object.entries(TYPE_TEXT).map(([value, text]) => ({ value, text })))
    .name('type')
    .label('Tipo')
    .formatter((cell: GridCellContext) => TYPE_TEXT[cell.value as PayrollRunRecord['type']] ?? cell.value)
    .build(),
);

const statusPatch = toPatch(
  enumField(Object.entries(STATUS_BADGE).map(([value, badge]) => ({ value, text: badge.text })))
    .name('status')
    .label('Estado')
    .formatter((cell: GridCellContext) => {
      const badge = STATUS_BADGE[cell.value as PayrollRunRecord['status']];
      if (!badge) return null;

      return <Badge variant={badge.variant} outlined>{badge.text}</Badge>;
    })
    .build(),
);

const PAYROLL_RUN_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['company', 'period', 'type', 'status'],
  },
];

const payrollRunResource = defineResource<PayrollRunRecord>(payrollRunApiUrl, {
  id: 'payroll-run',
  title: 'Corridas de planilla',
  events: payrollRunEvents,
  defaultPreset: 'basic',
  columnPresets: PAYROLL_RUN_COLUMN_PRESETS,
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  formLayout: payrollRunFormLayout,
  sort: [{ selector: 'period', desc: true }],
  emptyState: {
    title: 'Sin corridas de planilla',
    description: 'Crea una corrida para calcular la planilla regular, gratificaciones o CTS de un periodo.',
    icon: 'calculator',
  },
  fieldContract: defineFields<PayrollRunRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      {
        kind: 'override',
        key: 'company',
        patch: {
          order: 0,
          type: FieldType.ENTITY,
          url: API + 'companies',
          valueField: '_iri',
          textField: 'businessName',
          layoutHint: 'lookup',
          label: 'Empresa',
        },
      },
      { kind: 'override', key: 'period', patch: { order: 1, layoutHint: 'short', label: 'Periodo' } },
      { kind: 'override', key: 'type', patch: { ...typePatch, order: 2, layoutHint: 'short' } },
      { kind: 'override', key: 'status', patch: { ...statusPatch, order: 3, layoutHint: 'short', disabled: true } },
      { kind: 'override', key: 'calculatedAt', patch: { visibleOnForm: false } },
      { kind: 'override', key: 'approvedAt', patch: { visibleOnForm: false } },
      { kind: 'override', key: 'paidAt', patch: { visibleOnForm: false } },
      { kind: 'override', key: 'createdAt', patch: { visibleOnForm: false, visible: false } },
    ],
  }),
});

export const PayrollRunPage = () => {
  const navigate = useNavigate();

  const resource = {
    ...payrollRunResource,
    rowActions: (row: PayrollRunRecord) => [
      {
        key: 'detail',
        text: 'Ver detalle',
        icon: 'ph-eye',
        type: 'default' as const,
        onClick: () => navigate(`/payroll-runs/${row.id}`),
      },
    ],
  };

  return <SchemaCrudPage resource={resource} />;
};

PayrollRunPage.EVENTS = payrollRunEvents;
PayrollRunPage.API_URL = payrollRunApiUrl;

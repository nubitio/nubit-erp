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
import { Badge } from '@nubitio/ui';
import { useNavigate } from 'react-router-dom';
import { bankStatementImportApiUrl, bankStatementImportEvents } from './model/constants';

const API = getCoreApiBaseUrl();

interface BankStatementImportRecord extends DataRecord {
  id: string;
  bankAccount: unknown;
  fileName: string;
  bankProfile: string;
  status: 'PROCESSING' | 'COMPLETED' | 'FAILED';
  totalRows: number;
  importedRows: number;
  errorRows: number;
  createdAt: string;
}

function toPatch(field: Field): SmartCrudFieldPatch<BankStatementImportRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<BankStatementImportRecord>;
}

const STATUS_BADGE: Record<
  BankStatementImportRecord['status'],
  { variant: 'success' | 'warning' | 'secondary' | 'danger'; text: string }
> = {
  PROCESSING: { variant: 'warning', text: 'Procesando' },
  COMPLETED: { variant: 'success', text: 'Completado' },
  FAILED: { variant: 'danger', text: 'Falló' },
};

const statusPatch = toPatch(
  enumField([
    { value: 'PROCESSING', text: 'Procesando' },
    { value: 'COMPLETED', text: 'Completado' },
    { value: 'FAILED', text: 'Falló' },
  ])
    .name('status')
    .label('Estado')
    .formatter((cell: GridCellContext) => {
      const badge = STATUS_BADGE[cell.value as BankStatementImportRecord['status']];
      if (!badge) return null;

      return (
        <Badge variant={badge.variant} outlined>
          {badge.text}
        </Badge>
      );
    })
    .build(),
);

const BANK_STATEMENT_IMPORT_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: [
      'bankAccount',
      'fileName',
      'bankProfile',
      'status',
      'importedRows',
      'errorRows',
      'createdAt',
    ],
  },
];

const bankStatementImportResource = defineResource<BankStatementImportRecord>(
  bankStatementImportApiUrl,
  {
    id: 'bank-statement-import',
    title: 'Importaciones de extractos',
    events: bankStatementImportEvents,
    defaultPreset: 'basic',
    columnPresets: BANK_STATEMENT_IMPORT_COLUMN_PRESETS,
    viewMode: { mode: 'drawer', drawerSize: 'md' },
    sort: [{ selector: 'createdAt', desc: true }],
    permissions: { canAdd: false, canEdit: false, canDelete: false },
    emptyState: {
      title: 'Sin importaciones',
      description: 'Sube un extracto bancario para empezar a conciliar movimientos.',
      icon: 'upload-simple',
    },
    fieldContract: defineFields<BankStatementImportRecord>({
      source: 'hydra',
      strategy: 'augment',
      directives: [
        {
          kind: 'override',
          key: 'bankAccount',
          patch: {
            order: 0,
            type: FieldType.ENTITY,
            url: API + 'bank-accounts',
            valueField: '_iri',
            textField: 'alias',
            layoutHint: 'lookup',
            label: 'Cuenta bancaria',
          },
        },
        {
          kind: 'override',
          key: 'fileName',
          patch: { order: 1, layoutHint: 'medium', label: 'Archivo' },
        },
        {
          kind: 'override',
          key: 'bankProfile',
          patch: { order: 2, layoutHint: 'short', label: 'Formato' },
        },
        {
          kind: 'override',
          key: 'status',
          patch: { ...statusPatch, order: 3, layoutHint: 'short' },
        },
        {
          kind: 'override',
          key: 'totalRows',
          patch: { order: 4, layoutHint: 'short', label: 'Filas' },
        },
        {
          kind: 'override',
          key: 'importedRows',
          patch: { order: 5, layoutHint: 'short', label: 'Importadas' },
        },
        {
          kind: 'override',
          key: 'errorRows',
          patch: { order: 6, layoutHint: 'short', label: 'Con error' },
        },
        {
          kind: 'override',
          key: 'createdAt',
          patch: { order: 7, layoutHint: 'short', label: 'Fecha' },
        },
      ],
    }),
  },
);

export const BankStatementImportPage = () => {
  const navigate = useNavigate();

  const resource = {
    ...bankStatementImportResource,
    toolbar: {
      primary: [
        {
          key: 'import',
          text: 'Importar extracto',
          icon: 'ph-upload-simple',
          type: 'primary' as const,
          onClick: () => navigate('/bank-statement-imports/new'),
        },
      ],
    },
    rowActions: (row: BankStatementImportRecord) => {
      if (row.status !== 'COMPLETED') {
        return [];
      }

      return [
        {
          key: 'reconcile',
          text: 'Ver conciliación',
          icon: 'ph-arrows-left-right',
          type: 'success',
          onClick: () => navigate(`/bank-reconciliation/${row.id}`),
        },
      ];
    },
  };

  return <SchemaCrudPage resource={resource} />;
};

BankStatementImportPage.EVENTS = bankStatementImportEvents;
BankStatementImportPage.API_URL = bankStatementImportApiUrl;

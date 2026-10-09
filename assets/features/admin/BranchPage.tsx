import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  FieldType,
  type DataRecord,
  type GridCellContext,
} from '@nubitio/crud';
import { branchEndpoints } from './api/adminEndpoints';
import { SharedUtils } from '@shared/lib/SharedUtils';

interface BranchRecord extends DataRecord {
  id: string;
  name: string;
  address: string;
  phone: string;
  isActive: boolean;
  company: unknown;
}

const BRANCH_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['name', 'phone', 'address', 'isActive'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: ['name', 'phone', 'address', 'isActive', 'company'],
  },
];

const BRANCH_FORM_LAYOUT = {
  type: 'sections' as const,
  sections: [
    {
      label: 'General',
      fields: ['name', 'address', 'phone', 'isActive'],
    },
    {
      label: 'Empresa',
      fields: ['company'],
    },
  ],
};

const resource = defineResource<BranchRecord>(branchEndpoints.list(), {
  id: 'branch',
  title: 'Locales',
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  defaultPreset: 'basic',
  columnPresets: BRANCH_COLUMN_PRESETS,
  formLayout: BRANCH_FORM_LAYOUT,
  fieldContract: defineFields<BranchRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'name', patch: { order: 0, layoutHint: 'medium', label: 'Nombre' } },
      {
        kind: 'override',
        key: 'phone',
        patch: { order: 1, layoutHint: 'short', label: 'Teléfono' },
      },
      {
        kind: 'override',
        key: 'address',
        patch: { order: 2, layoutHint: 'long', label: 'Dirección' },
      },
      {
        kind: 'override',
        key: 'isActive',
        patch: {
          order: 3,
          width: 100,
          layoutHint: 'short',
          label: 'Activo',
          type: FieldType.SWITCH,
          formatter: (cell: GridCellContext) => SharedUtils.booleanFormatter(Boolean(cell.value)),
        },
      },
      {
        kind: 'override',
        key: 'company',
        patch: { order: 4, layoutHint: 'lookup', label: 'Empresa' },
      },
    ],
  }),
});

export const BranchPage = () => <SchemaCrudPage resource={resource} />;

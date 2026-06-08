import { SmartCrudPage, defineFields, defineResource, type DataRecord } from '@nubit/crud';
import { branchEndpoints } from './api/adminEndpoints';

interface BranchRecord extends DataRecord {
  id: string;
  name: string;
  address: string;
  phone: string;
  company: unknown;
}

const BRANCH_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['name', 'phone', 'address'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: ['name', 'phone', 'address', 'company'],
  },
];

const BRANCH_FORM_LAYOUT = {
  type: 'sections' as const,
  sections: [
    {
      label: 'General',
      fields: ['name', 'address', 'phone'],
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
      { kind: 'override', key: 'phone', patch: { order: 1, layoutHint: 'short', label: 'Teléfono' } },
      { kind: 'override', key: 'address', patch: { order: 2, layoutHint: 'long', label: 'Dirección' } },
      { kind: 'override', key: 'company', patch: { order: 3, layoutHint: 'lookup', label: 'Empresa' } },
    ],
  }),
});

export const BranchPage = () => <SmartCrudPage resource={resource} />;

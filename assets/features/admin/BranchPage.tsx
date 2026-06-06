import { SmartCrudPage, defineResource } from '@nubit/crud';
import { branchEndpoints } from './api/adminEndpoints';

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
  type: 'tabs' as const,
  tabs: [
    {
      label: 'General',
      icon: 'ph-storefront',
      fields: ['name', 'address', 'phone'],
    },
    {
      label: 'Empresa',
      icon: 'ph-buildings',
      fields: ['company'],
    },
  ],
};

const resource = defineResource(branchEndpoints.list(), {
  id: 'branch',
  title: 'Locales',
  viewMode: { mode: 'drawer', drawerWidth: 640 },
  defaultPreset: 'basic',
  columnPresets: BRANCH_COLUMN_PRESETS,
  formLayout: BRANCH_FORM_LAYOUT,
});

export const BranchPage = () => <SmartCrudPage resource={resource} />;
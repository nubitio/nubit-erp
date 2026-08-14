import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  switchField,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { SharedUtils } from '@shared/lib/SharedUtils';

interface ExpenseCategoryRecord extends DataRecord {
  id: number;
  code: string;
  name: string;
  system: boolean;
  active: boolean;
}

function toPatch(field: Field): SmartCrudFieldPatch<ExpenseCategoryRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<ExpenseCategoryRecord>;
}

const booleanSwitchPatch = (name: 'system' | 'active', label: string) =>
  toPatch(
    switchField()
      .name(name)
      .label(label)
      .defaultValue(true)
      .formatter((cell) => SharedUtils.booleanFormatter(cell.value as boolean))
      .build(),
  );

const EXPENSE_CATEGORY_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['code', 'name', 'active'],
  },
];

const EXPENSE_CATEGORY_FORM_LAYOUT = {
  type: 'sections' as const,
  sections: [
    {
      label: 'General',
      fields: ['code', 'name'],
    },
    {
      label: 'Estado',
      fields: ['system', 'active'],
    },
  ],
};

const resource = defineResource<ExpenseCategoryRecord>(API + 'expense-categories', {
  id: 'expense-categories',
  title: 'Categorías de gasto',
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  defaultPreset: 'basic',
  columnPresets: EXPENSE_CATEGORY_COLUMN_PRESETS,
  formLayout: EXPENSE_CATEGORY_FORM_LAYOUT,
  emptyState: {
    title: 'Sin categorías de gasto',
    description: 'Crea categorías para clasificar facturas de proveedor (comisiones bancarias, alquiler, etc.).',
    icon: 'tag',
  },
  fieldContract: defineFields<ExpenseCategoryRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'code', patch: { order: 0, layoutHint: 'short', label: 'Código' } },
      { kind: 'override', key: 'name', patch: { order: 1, layoutHint: 'medium', label: 'Nombre' } },
      { kind: 'override', key: 'system', patch: { ...booleanSwitchPatch('system', 'Sistema'), order: 2, layoutHint: 'short' } },
      { kind: 'override', key: 'active', patch: { ...booleanSwitchPatch('active', 'Activo'), order: 3, layoutHint: 'short' } },
    ],
  }),
});
export const ExpenseCategoryPage = () => <SchemaCrudPage resource={resource} />;

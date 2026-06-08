import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import {
  SmartCrudPage,
  defineFields,
  defineResource,
  enumField,
  switchField,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubit/crud';
import { Badge } from '@nubit/ui';
import { SharedUtils } from '@shared/lib/SharedUtils';

interface CashMovementCategoryRecord extends DataRecord {
  id: number;
  code: string;
  name: string;
  type: 'income' | 'expense';
  system: boolean;
  active: boolean;
}

function toPatch(field: Field): SmartCrudFieldPatch<CashMovementCategoryRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<CashMovementCategoryRecord>;
}

const typePatch = toPatch(
  enumField([
    { value: 'income', text: 'Ingreso' },
    { value: 'expense', text: 'Egreso' },
  ])
    .name('type')
    .label('Tipo')
    .formatter((cell) => {
      if (cell.value === 'income') {
        return <Badge variant="success" outlined>Ingreso</Badge>;
      }

      if (cell.value === 'expense') {
        return <Badge variant="danger" outlined>Egreso</Badge>;
      }

      return null;
    })
    .required(true)
    .build(),
);

const booleanSwitchPatch = (name: 'system' | 'active', label: string) =>
  toPatch(
    switchField()
      .name(name)
      .label(label)
      .defaultValue(true)
      .formatter((cell) => SharedUtils.booleanFormatter(cell.value as boolean))
      .build(),
  );

const CASH_MOVEMENT_CATEGORY_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['code', 'name', 'type'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: ['code', 'name', 'type', 'active'],
  },
];

const CASH_MOVEMENT_CATEGORY_FORM_LAYOUT = {
  type: 'sections' as const,
  sections: [
    {
      label: 'General',
      fields: ['code', 'name', 'type'],
    },
    {
      label: 'Estado',
      fields: ['system', 'active'],
    },
  ],
};

const resource = defineResource<CashMovementCategoryRecord>(API + 'cash-movement-categories', {
  id: 'cash-movement-categories',
  title: 'Categorías de caja',
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  defaultPreset: 'basic',
  columnPresets: CASH_MOVEMENT_CATEGORY_COLUMN_PRESETS,
  formLayout: CASH_MOVEMENT_CATEGORY_FORM_LAYOUT,
  emptyState: {
    title: 'Sin categorías de caja',
    description: 'Crea categorías para clasificar ingresos y egresos de caja.',
    icon: 'tag',
  },
  fieldContract: defineFields<CashMovementCategoryRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'code', patch: { order: 0, col: 4, label: 'Código' } },
      { kind: 'override', key: 'name', patch: { order: 1, col: 8, label: 'Nombre' } },
      { kind: 'override', key: 'type', patch: { ...typePatch, order: 2, col: 6 } },
      { kind: 'override', key: 'system', patch: { ...booleanSwitchPatch('system', 'Sistema'), order: 3, col: 6 } },
      { kind: 'override', key: 'active', patch: { ...booleanSwitchPatch('active', 'Activo'), order: 4, col: 6 } },
    ],
  }),
});
export const CashMovementCategoryPage = () => <SmartCrudPage resource={resource} />;

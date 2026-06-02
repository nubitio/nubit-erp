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

const resource = defineResource<CashMovementCategoryRecord>(API + 'cash-movement-categories', {
  id: 'cash-movement-categories',
  title: 'Categorías de caja',
  fieldContract: defineFields<CashMovementCategoryRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'type', patch: typePatch },
      { kind: 'override', key: 'system', patch: booleanSwitchPatch('system', 'Sistema') },
      { kind: 'override', key: 'active', patch: booleanSwitchPatch('active', 'Activo') },
    ],
  }),
});
export const CashMovementCategoryPage = () => <SmartCrudPage resource={resource} />;

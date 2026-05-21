import { API_BASE_URL } from '@app/constants';
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
import { cashMovementCategoryEvents } from '@features/cashRegister';
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
        return <span className="badge badge-outlined rounded-pill text-bg-success">Ingreso</span>;
      }

      if (cell.value === 'expense') {
        return <span className="badge badge-outlined rounded-pill text-bg-danger">Egreso</span>;
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
      .formatter((cell) => SharedUtils.booleanFormatter(cell.value))
      .build(),
  );

const resource = defineResource<CashMovementCategoryRecord>({
  id: 'cash-movement-categories',
  title: 'Categorías de caja',
  apiUrl: API_BASE_URL + 'cash-movement-categories',
  fields: [],
  events: cashMovementCategoryEvents,
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

import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  enumField,
  switchField,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { bankAccountApiUrl, bankAccountEvents, bankAccountFormLayout } from './model/constants';

interface BankAccountRecord extends DataRecord {
  id: string;
  bank: string;
  alias: string;
  accountNumber: string;
  cci: string | null;
  currency: string;
  active: boolean;
}

function toPatch(field: Field): SmartCrudFieldPatch<BankAccountRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<BankAccountRecord>;
}

const BANK_ACCOUNT_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['bank', 'alias', 'accountNumber', 'currency', 'active'],
  },
];

const bankAccountResource = defineResource<BankAccountRecord>(bankAccountApiUrl, {
  id: 'bank-account',
  title: 'Cuentas bancarias',
  events: bankAccountEvents,
  defaultPreset: 'basic',
  columnPresets: BANK_ACCOUNT_COLUMN_PRESETS,
  viewMode: { mode: 'drawer', drawerSize: 'sm' },
  formLayout: bankAccountFormLayout,
  sort: [{ selector: 'alias', desc: false }],
  emptyState: {
    title: 'Sin cuentas bancarias',
    description:
      'Registra las cuentas bancarias de la empresa para poder importar y conciliar sus extractos.',
    icon: 'bank',
  },
  fieldContract: defineFields<BankAccountRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      {
        kind: 'override',
        key: 'bank',
        patch: {
          ...toPatch(
            enumField([
              { value: 'BCP', text: 'BCP' },
              { value: 'BBVA', text: 'BBVA' },
              { value: 'INTERBANK', text: 'Interbank' },
              { value: 'SCOTIABANK', text: 'Scotiabank' },
              { value: 'OTHER', text: 'Otro' },
            ])
              .name('bank')
              .label('Banco')
              .build(),
          ),
          order: 0,
          layoutHint: 'short',
        },
      },
      { kind: 'override', key: 'alias', patch: { order: 1, layoutHint: 'medium', label: 'Alias' } },
      {
        kind: 'override',
        key: 'accountNumber',
        patch: { order: 2, layoutHint: 'medium', label: 'Número de cuenta' },
      },
      {
        kind: 'override',
        key: 'cci',
        patch: { order: 3, layoutHint: 'medium', label: 'CCI', required: false },
      },
      {
        kind: 'override',
        key: 'currency',
        patch: {
          ...toPatch(
            enumField([
              { value: 'PEN', text: 'Soles (PEN)' },
              { value: 'USD', text: 'Dólares (USD)' },
            ])
              .name('currency')
              .label('Moneda')
              .build(),
          ),
          order: 4,
          layoutHint: 'short',
        },
      },
      {
        kind: 'override',
        key: 'active',
        patch: {
          ...toPatch(
            switchField()
              .name('active')
              .label('Activa')
              .defaultValue(true)
              .formatter((cell) => SharedUtils.booleanFormatter(cell.value as boolean))
              .build(),
          ),
          order: 5,
          layoutHint: 'short',
        },
      },
      { kind: 'override', key: 'createdAt', patch: { visibleOnForm: false, visible: false } },
    ],
  }),
});

export const BankAccountPage = () => <SchemaCrudPage resource={bankAccountResource} />;

BankAccountPage.EVENTS = bankAccountEvents;
BankAccountPage.API_URL = bankAccountApiUrl;

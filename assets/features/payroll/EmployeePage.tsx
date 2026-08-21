import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  enumField,
  switchField,
  FieldType,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { SharedUtils } from '@shared/lib/SharedUtils';
import { ErpModuleBanner } from '@features/shared/ErpModuleBanner';
import { employeeApiUrl, employeeEvents, employeeFormLayout } from './model/constants';

interface EmployeeRecord extends DataRecord {
  id: string;
  company: unknown;
  user: unknown;
  dni: string;
  firstName: string;
  lastName: string;
  hireDate: string;
  terminationDate: string | null;
  contractType: 'INDEFINIDO' | 'PLAZO_FIJO';
  baseSalary: string;
  pensionScheme: 'AFP' | 'ONP';
  afpProvider: string | null;
  epsAffiliated: boolean;
  status: 'ACTIVE' | 'TERMINATED';
}

function toPatch(field: Field): SmartCrudFieldPatch<EmployeeRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<EmployeeRecord>;
}

const contractTypePatch = toPatch(
  enumField([
    { value: 'INDEFINIDO', text: 'Indefinido' },
    { value: 'PLAZO_FIJO', text: 'Plazo fijo' },
  ])
    .name('contractType')
    .label('Tipo de contrato')
    .build(),
);

const pensionSchemePatch = toPatch(
  enumField([
    { value: 'ONP', text: 'ONP' },
    { value: 'AFP', text: 'AFP' },
  ])
    .name('pensionScheme')
    .label('Sistema de pensión')
    .build(),
);

const afpProviderPatch = toPatch(
  enumField([
    { value: 'HABITAT', text: 'Habitat' },
    { value: 'INTEGRA', text: 'Integra' },
    { value: 'PRIMA', text: 'Prima' },
    { value: 'PROFUTURO', text: 'Profuturo' },
  ])
    .name('afpProvider')
    .label('AFP')
    .build(),
);

const epsAffiliatedPatch = toPatch(
  switchField()
    .name('epsAffiliated')
    .label('Afiliado a EPS')
    .defaultValue(false)
    .formatter((cell) => SharedUtils.booleanFormatter(cell.value as boolean))
    .build(),
);

const statusPatch = toPatch(
  enumField([
    { value: 'ACTIVE', text: 'Activo' },
    { value: 'TERMINATED', text: 'Cesado' },
  ])
    .name('status')
    .label('Estado')
    .build(),
);

const EMPLOYEE_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['dni', 'firstName', 'lastName', 'baseSalary', 'pensionScheme', 'status'],
  },
];

const employeeResource = defineResource<EmployeeRecord>(employeeApiUrl, {
  id: 'employee',
  title: 'Empleados',
  events: employeeEvents,
  defaultPreset: 'basic',
  columnPresets: EMPLOYEE_COLUMN_PRESETS,
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  formLayout: employeeFormLayout,
  sort: [{ selector: 'lastName', desc: false }],
  emptyState: {
    title: 'Sin empleados',
    description: 'Registra un empleado para incluirlo en las corridas de planilla.',
    icon: 'identification-card',
  },
  fieldContract: defineFields<EmployeeRecord>({
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
      { kind: 'override', key: 'dni', patch: { order: 1, layoutHint: 'short', label: 'DNI' } },
      { kind: 'override', key: 'firstName', patch: { order: 2, layoutHint: 'medium', label: 'Nombres' } },
      { kind: 'override', key: 'lastName', patch: { order: 3, layoutHint: 'medium', label: 'Apellidos' } },
      { kind: 'override', key: 'hireDate', patch: { order: 4, layoutHint: 'short', label: 'Fecha de ingreso' } },
      {
        kind: 'override',
        key: 'terminationDate',
        patch: {
          order: 5,
          layoutHint: 'short',
          label: 'Fecha de cese',
          required: false,
          // The schema-inferred date field defaults empty dates to "now" —
          // wrong for an optional field where "today" would silently mark
          // a brand-new employee as terminated. Force it to stay empty.
          defaultValue: () => undefined,
        },
      },
      { kind: 'override', key: 'contractType', patch: { ...contractTypePatch, order: 6, layoutHint: 'short' } },
      { kind: 'override', key: 'baseSalary', patch: { order: 7, layoutHint: 'short', label: 'Sueldo base' } },
      { kind: 'override', key: 'pensionScheme', patch: { ...pensionSchemePatch, order: 8, layoutHint: 'short' } },
      { kind: 'override', key: 'afpProvider', patch: { ...afpProviderPatch, order: 9, layoutHint: 'short' } },
      { kind: 'override', key: 'epsAffiliated', patch: { ...epsAffiliatedPatch, order: 10, layoutHint: 'short' } },
      {
        kind: 'override',
        key: 'user',
        patch: {
          order: 11,
          type: FieldType.ENTITY,
          url: API + 'users',
          valueField: '_iri',
          textField: 'username',
          layoutHint: 'lookup',
          label: 'Usuario del sistema',
          required: false,
          helpText: 'Solo si el empleado también tiene una cuenta de acceso al sistema.',
        },
      },
      { kind: 'override', key: 'status', patch: { ...statusPatch, order: 12, layoutHint: 'short', disabled: true } },
      { kind: 'override', key: 'createdAt', patch: { visibleOnForm: false, visible: false } },
    ],
  }),
});

export const EmployeePage = () => (
  <>
    <ErpModuleBanner tone="warning">
      Calcula AFP, CTS y quinta como apoyo operativo. Valida PLAME y los pagos con tu contador
      antes de declarar.
    </ErpModuleBanner>
    <SchemaCrudPage resource={employeeResource} />
  </>
);

EmployeePage.EVENTS = employeeEvents;
EmployeePage.API_URL = employeeApiUrl;

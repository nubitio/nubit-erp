import { describe, expect, it } from 'vitest';
import type { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import { SmartCrudFieldContractError, defineFields } from './fieldContract';
import { resolveSmartCrudFields } from './resolveSmartCrudFields';

function makeField(name: string, overrides: Partial<Field> = {}): Field {
  return {
    name,
    label: name.charAt(0).toUpperCase() + name.slice(1),
    type: FieldType.TEXT,
    required: false,
    readonly: false,
    disabled: false,
    hidden: false,
    filterable: false,
    sortable: true,
    hideable: true,
    visible: true,
    visibleOnForm: true,
    isIdentity: false,
    col: undefined,
    width: undefined,
    height: null,
    minWidth: undefined,
    minHeight: undefined,
    align: undefined,
    validators: [],
    mode: 'remote',
    url: undefined,
    loadOptions: [],
    filters: [],
    byKeyUrl: null,
    textField: 'name',
    valueField: 'id',
    valueType: 'string',
    format: '',
    selectedFilterOperation: undefined,
    filterValue: undefined,
    data: [],
    formatter: undefined,
    itemFormatter: undefined,
    defaultValue: undefined,
    onChange: undefined,
    onSelect: undefined,
    onClick: undefined,
    onKeyPress: undefined,
    precision: 2,
    accept: undefined,
    buttons: [],
    hidingPriority: undefined,
    searchEnabled: false,
    searchExpr: null,
    fixed: false,
    fixedPosition: undefined,
    helpText: undefined,
    contentRender: undefined,
    maxLength: undefined,
    multiple: false,
    editorOptions: undefined,
    sendAsString: false,
    ...overrides,
  } as Field;
}

describe('resolveSmartCrudFields', () => {
  it('resolves hydra directives in deterministic prepend-baseline-append order', () => {
    const baseline = [makeField('id', { isIdentity: true }), makeField('email'), makeField('role')];

    const contract = defineFields<{ id: string; email: string; role: string }>({
      source: 'hydra',
      strategy: 'augment',
      directives: [
        {
          kind: 'prepend',
          field: makeField('tenantCode', { label: 'Tenant', type: FieldType.TEXT }),
        },
        {
          kind: 'override',
          key: 'email',
          patch: { label: 'Correo', required: true },
        },
        {
          kind: 'remove',
          key: 'role',
        },
        {
          kind: 'append',
          field: makeField('plainPassword', { label: 'Contraseña', type: FieldType.PASSWORD }),
        },
      ],
    });

    const resolved = resolveSmartCrudFields({ baselineFields: baseline, contract });

    expect(resolved.map((field) => field.name)).toEqual([
      'tenantCode',
      'id',
      'email',
      'plainPassword',
    ]);
    expect(resolved.find((field) => field.name === 'email')).toMatchObject({
      label: 'Correo',
      required: true,
    });
  });

  it('rejects hydra directives targeting unknown inferred fields', () => {
    const contract = defineFields<{ email: string }>({
      source: 'hydra',
      strategy: 'augment',
      directives: [{ kind: 'override', key: 'email', patch: { label: 'Correo' } }],
    });

    expect(() =>
      resolveSmartCrudFields({
        baselineFields: [makeField('name')],
        contract,
      }),
    ).toThrowError(SmartCrudFieldContractError);
  });

  it('rejects synthetic fields that collide with inferred hydra field names', () => {
    const contract = defineFields<{ email: string }>({
      source: 'hydra',
      strategy: 'augment',
      directives: [{ kind: 'append', field: makeField('email', { label: 'Synthetic Email' }) }],
    });

    expect(() =>
      resolveSmartCrudFields({
        baselineFields: [makeField('email')],
        contract,
      }),
    ).toThrowError(SmartCrudFieldContractError);
  });

  it('validates raw runtime contracts before resolution when defineFields is bypassed', () => {
    expect(() =>
      resolveSmartCrudFields({
        baselineFields: [makeField('email')],
        contract: {
          source: 'hydra',
          strategy: 'augment',
          directives: [
            {
              kind: 'override',
              key: 'email',
              patch: { label: 'Correo', unexpected: true } as never,
            },
          ],
        },
      }),
    ).toThrowError(SmartCrudFieldContractError);
  });
});

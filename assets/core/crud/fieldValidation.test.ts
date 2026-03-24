import { describe, expect, it } from 'vitest';
import type { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import {
  SmartCrudFieldContractError,
  validateFieldContract,
  validateHydraFieldResolutionInput,
} from './fieldValidation';

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

describe('fieldValidation', () => {
  it('rejects unknown keys in canonical hydra contracts with actionable errors', () => {
    try {
      validateFieldContract({
        source: 'hydra',
        strategy: 'augment',
        directives: [
          {
            kind: 'override',
            key: 'email',
            patch: {
              label: 'Correo',
              mystery: true,
              operation: {
                only: 'create',
                unexpected: true,
              },
            },
          },
        ],
        unknown: true,
      } as unknown as Parameters<typeof validateFieldContract>[0]);
      throw new Error('expected validation to fail');
    } catch (error) {
      expect(error).toBeInstanceOf(SmartCrudFieldContractError);
      expect((error as SmartCrudFieldContractError).issues).toEqual(
        expect.arrayContaining([
          expect.stringContaining("Hydra contract contains unknown key 'unknown'"),
          expect.stringContaining("Override 'email' patch contains unknown key 'mystery'"),
          expect.stringContaining("Override 'email' operation contains unknown key 'unexpected'"),
        ]),
      );
    }
  });

  it('rejects bad hydra runtime targets and synthetic collisions deterministically', () => {
    expect(() =>
      validateHydraFieldResolutionInput([makeField('email')], {
        source: 'hydra',
        strategy: 'augment',
        directives: [
          { kind: 'override', key: 'missing', patch: { label: 'Ghost' } },
          { kind: 'append', field: makeField('email', { label: 'Synthetic Email' }) },
        ],
      }),
    ).toThrowError(SmartCrudFieldContractError);

    try {
      validateHydraFieldResolutionInput([makeField('email')], {
        source: 'hydra',
        strategy: 'augment',
        directives: [
          { kind: 'override', key: 'missing', patch: { label: 'Ghost' } },
          { kind: 'append', field: makeField('email', { label: 'Synthetic Email' }) },
        ],
      });
      throw new Error('expected validation to fail');
    } catch (error) {
      expect((error as SmartCrudFieldContractError).issues).toEqual([
        "Override targets unknown Hydra field 'missing'.",
        "Synthetic field 'email' collides with an inferred Hydra field. Use 'override' instead.",
      ]);
    }
  });
});

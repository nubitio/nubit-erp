import { describe, expect, it } from 'vitest';
import { FieldType } from '../field/FieldType';
import {
  SmartCrudFieldContractError,
  defineFields,
  validateFieldContract,
  type SmartCrudFieldContract,
} from './fieldContract';

interface UserRecord {
  id: string;
  email: string;
  password: string;
}

describe('fieldContract', () => {
  it('accepts a typed hydra augmentation contract', () => {
    const contract = defineFields<UserRecord>({
      source: 'hydra',
      strategy: 'augment',
      directives: [
        {
          kind: 'override',
          key: 'email',
          patch: {
            label: 'Correo',
            operation: {
              create: { required: true },
              edit: { readonly: true },
            },
          },
        },
        {
          kind: 'append',
          field: {
            name: 'plainPassword',
            label: 'Contraseña',
            type: FieldType.PASSWORD,
            isIdentity: false,
            col: 12,
            width: undefined,
            height: null,
            minWidth: undefined,
            minHeight: undefined,
            align: undefined,
            sortable: false,
            filterable: false,
            hideable: false,
            validators: [],
            mode: 'none',
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
            visible: true,
            defaultValue: undefined,
            autoSelectIfSingle: false,
            onChange: undefined,
            onSelect: undefined,
            onClick: undefined,
            onKeyPress: undefined,
            readonly: false,
            disabled: false,
            hidden: false,
            required: false,
            precision: 0,
            accept: undefined,
            buttons: [],
            hidingPriority: undefined,
            searchEnabled: false,
            searchExpr: null,
            fixed: false,
            fixedPosition: undefined,
            helpText: 'Visible only while creating a user.',
            contentRender: undefined,
            visibleOnForm: true,
            maxLength: undefined,
            multiple: false,
            editorOptions: undefined,
            sendAsString: false,
            operation: {
              create: { visible: true, required: true },
              edit: { visible: false },
            },
          },
        },
      ],
    });

    expect(contract.source).toBe('hydra');
    expect(contract.source).toBe('hydra');
    const hydraContract = contract as Extract<
      SmartCrudFieldContract<UserRecord>,
      { source: 'hydra' }
    >;

    expect(hydraContract.directives).toHaveLength(2);
  });

  it('rejects duplicate hydra targets to keep resolution deterministic', () => {
    expect(() =>
      validateFieldContract({
        source: 'hydra',
        strategy: 'augment',
        directives: [
          { kind: 'remove', key: 'email' },
          { kind: 'override', key: 'email', patch: { label: 'Correo' } },
        ],
      } satisfies SmartCrudFieldContract<UserRecord>),
    ).toThrowError(SmartCrudFieldContractError);
  });

  it('accepts first-class create/edit operation flags for overrides and synthetic fields', () => {
    const contract = validateFieldContract({
      source: 'hydra',
      strategy: 'augment',
      directives: [
        {
          kind: 'override',
          key: 'email',
          patch: {
            operation: {
              readonly: { edit: true },
              required: { create: true },
            },
          },
        },
        {
          kind: 'append',
          field: {
            name: 'plainPassword',
            label: 'Contraseña',
            type: FieldType.PASSWORD,
            isIdentity: false,
            col: 12,
            width: undefined,
            height: null,
            minWidth: undefined,
            minHeight: undefined,
            align: undefined,
            sortable: false,
            filterable: false,
            hideable: false,
            validators: [],
            mode: 'none',
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
            visible: true,
            defaultValue: undefined,
            autoSelectIfSingle: false,
            onChange: undefined,
            onSelect: undefined,
            onClick: undefined,
            onKeyPress: undefined,
            readonly: false,
            disabled: false,
            hidden: false,
            required: false,
            precision: 0,
            accept: undefined,
            buttons: [],
            hidingPriority: undefined,
            searchEnabled: false,
            searchExpr: null,
            fixed: false,
            fixedPosition: undefined,
            helpText: 'Visible only while creating a user.',
            contentRender: undefined,
            visibleOnForm: true,
            maxLength: undefined,
            multiple: false,
            editorOptions: undefined,
            sendAsString: false,
            operation: {
              only: 'create',
              required: { create: true },
            },
          },
        },
      ],
    } satisfies SmartCrudFieldContract<UserRecord>);

    expect(contract.source).toBe('hydra');
    expect((contract as unknown as { directives: unknown[] }).directives).toHaveLength(2);
  });

  it('rejects contradictory operation state', () => {
    expect(() =>
      validateFieldContract({
        source: 'manual',
        strategy: 'replace',
        fields: [
          {
            name: 'plainPassword',
            label: 'Contraseña',
            type: FieldType.PASSWORD,
            isIdentity: false,
            col: 12,
            width: undefined,
            height: null,
            minWidth: undefined,
            minHeight: undefined,
            align: undefined,
            sortable: false,
            filterable: false,
            hideable: false,
            validators: [],
            mode: 'none',
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
            visible: true,
            defaultValue: undefined,
            autoSelectIfSingle: false,
            onChange: undefined,
            onSelect: undefined,
            onClick: undefined,
            onKeyPress: undefined,
            readonly: false,
            disabled: false,
            hidden: false,
            required: false,
            precision: 0,
            accept: undefined,
            buttons: [],
            hidingPriority: undefined,
            searchEnabled: false,
            searchExpr: null,
            fixed: false,
            fixedPosition: undefined,
            helpText: undefined,
            contentRender: undefined,
            visibleOnForm: true,
            maxLength: undefined,
            multiple: false,
            editorOptions: undefined,
            sendAsString: false,
            operation: {
              edit: { visible: false, required: true },
            },
          },
        ],
      } satisfies SmartCrudFieldContract<UserRecord>),
    ).toThrowError(SmartCrudFieldContractError);
  });

  it('rejects conflicting first-class operation semantics', () => {
    expect(() =>
      validateFieldContract({
        source: 'hydra',
        strategy: 'augment',
        directives: [
          {
            kind: 'override',
            key: 'email',
            patch: {
              operation: {
                only: 'create',
                edit: { visible: true },
              },
            },
          },
        ],
      } satisfies SmartCrudFieldContract<UserRecord>),
    ).toThrowError(SmartCrudFieldContractError);
  });
});

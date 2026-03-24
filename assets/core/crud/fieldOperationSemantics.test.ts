import { describe, expect, it } from 'vitest';
import { FieldType } from '../field/FieldType';
import {
  SmartCrudFieldOperationSemanticsError,
  applyFieldOperationFormSemantics,
  applyFieldOperationSemantics,
  normalizeFieldOperationSemantics,
  resolveFieldOperationSemantics,
} from './fieldOperationSemantics';

const baseField = {
  name: 'email',
  label: 'Email',
  type: FieldType.TEXT,
  isIdentity: false,
  col: 12 as const,
  width: undefined,
  height: null,
  minWidth: undefined,
  minHeight: undefined,
  align: undefined,
  sortable: true,
  filterable: true,
  hideable: true,
  validators: [],
  mode: 'none' as const,
  url: undefined,
  loadOptions: [],
  filters: [],
  byKeyUrl: null,
  textField: 'name',
  valueField: 'id',
  valueType: 'string' as const,
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
};

describe('fieldOperationSemantics', () => {
  it('normalizes first-class create-only semantics deterministically', () => {
    const semantics = normalizeFieldOperationSemantics({
      only: 'create',
      required: { create: true },
      readonly: { edit: true },
    });

    expect(semantics.create).toEqual({ visible: true, required: true });
    expect(semantics.edit).toEqual({ visible: false, readonly: true });
  });

  it('applies normalized semantics to inferred or synthetic field defaults', () => {
    const semantics = resolveFieldOperationSemantics(baseField, {
      only: 'edit',
      required: { edit: true },
      readonly: { edit: true },
    });

    expect(semantics.create).toEqual({
      visible: false,
      required: false,
      readonly: false,
      disabled: false,
    });
    expect(semantics.edit).toEqual({
      visible: true,
      required: true,
      readonly: true,
      disabled: false,
    });
  });

  it('projects operation semantics back onto a runtime field clone', () => {
    const field = applyFieldOperationSemantics(baseField, 'create', {
      only: 'create',
      required: { create: true },
    });

    expect(field.visible).toBe(true);
    expect(field.hidden).toBe(false);
    expect(field.required).toBe(true);
  });

  it('projects operation semantics onto form state without hiding grid columns', () => {
    const field = applyFieldOperationFormSemantics(baseField, 'edit', {
      only: 'create',
      required: { create: true },
    });

    expect(field.visible).toBe(true);
    expect(field.visibleOnForm).toBe(false);
    expect(field.hidden).toBe(true);
    expect(field.required).toBe(false);
  });

  it('rejects conflicting explicit visibility semantics', () => {
    expect(() =>
      normalizeFieldOperationSemantics({
        only: 'create',
        edit: { visible: true },
      }),
    ).toThrowError(SmartCrudFieldOperationSemanticsError);
  });

  it('rejects invisible required states', () => {
    expect(() =>
      normalizeFieldOperationSemantics({
        required: { edit: true },
        edit: { visible: false },
      }),
    ).toThrowError(SmartCrudFieldOperationSemanticsError);
  });
});

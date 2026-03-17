// @vitest-environment jsdom
/**
 * Unit tests for SLICE-006 — useFieldPermissions hook
 *
 * Coverage:
 *   - no permissions set → all fields returned
 *   - visibility gate: hidden when no role intersection
 *   - visibility gate: shown when role intersects
 *   - editability gate: forced disabled when user lacks editable role
 *   - editability gate: field unchanged when user has editable role
 *   - empty userRoles array
 *   - field with both visible and editable permissions
 */

import { describe, it, expect } from 'vitest';
import { renderHook } from '@testing-library/react';
import type { Field } from '../../field/Field';
import { FieldType } from '../../field/FieldType';
import { useFieldPermissions } from './useFieldPermissions';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeField(overrides: Partial<Field> = {}): Field {
  return {
    isIdentity: false,
    type: FieldType.TEXT,
    col: 12,
    name: 'testField',
    label: 'Test',
    width: undefined,
    height: null,
    minWidth: undefined,
    minHeight: undefined,
    align: 'left',
    sortable: true,
    filterable: true,
    hideable: true,
    validators: [],
    mode: 'local',
    url: undefined,
    loadOptions: [],
    filters: [],
    byKeyUrl: null,
    textField: '',
    valueField: '',
    valueType: 'string',
    format: '',
    selectedFilterOperation: undefined,
    filterValue: undefined,
    data: [],
    formatter: undefined,
    itemFormatter: undefined,
    visible: true,
    defaultValue: undefined,
    onChange: undefined,
    onSelect: undefined,
    onClick: undefined,
    onKeyPress: undefined,
    readonly: false,
    disabled: false,
    hidden: false,
    required: false,
    precision: 0,
    accept: null,
    buttons: [],
    hidingPriority: undefined,
    searchEnabled: true,
    searchExpr: null,
    fixed: false,
    fixedPosition: undefined,
    helpText: undefined,
    contentRender: null,
    visibleOnForm: true,
    maxLength: undefined,
    multiple: false,
    editorOptions: undefined,
    sendAsString: false,
    ...overrides,
  };
}

// ---------------------------------------------------------------------------
// useFieldPermissions
// ---------------------------------------------------------------------------

describe('useFieldPermissions', () => {
  it('returns all fields when no permissions set', () => {
    const fields: Field[] = [makeField({ name: 'name' }), makeField({ name: 'email' })];
    const { result } = renderHook(() => useFieldPermissions(fields, ['user']));

    expect(result.current).toHaveLength(2);
    expect(result.current[0].name).toBe('name');
    expect(result.current[1].name).toBe('email');
  });

  it('hides field when userRoles has no intersection with permissions.visible', () => {
    const fields: Field[] = [
      makeField({
        name: 'discount',
        permissions: { visible: ['admin', 'manager'] },
      }),
    ];
    const { result } = renderHook(() => useFieldPermissions(fields, ['user']));

    expect(result.current).toHaveLength(0);
  });

  it('shows field when userRoles intersects with permissions.visible', () => {
    const fields: Field[] = [
      makeField({
        name: 'discount',
        permissions: { visible: ['admin', 'manager'] },
      }),
    ];
    const { result } = renderHook(() => useFieldPermissions(fields, ['manager', 'viewer']));

    expect(result.current).toHaveLength(1);
    expect(result.current[0].name).toBe('discount');
  });

  it('forces disabledWhen=true when user lacks editable role', () => {
    const fields: Field[] = [
      makeField({
        name: 'price',
        permissions: { editable: ['admin'] },
      }),
    ];
    const { result } = renderHook(() => useFieldPermissions(fields, ['user']));

    // Field is still visible but disabledWhen is forced to () => true
    expect(result.current).toHaveLength(1);
    expect(result.current[0].disabledWhen).toBeDefined();
    // The forced disabledWhen should always return true
    expect(result.current[0].disabledWhen!({})).toBe(true);
  });

  it('does not modify field when user has editable role', () => {
    const originalDisabledWhen = undefined;
    const fields: Field[] = [
      makeField({
        name: 'price',
        permissions: { editable: ['admin'] },
        disabledWhen: originalDisabledWhen,
      }),
    ];
    const { result } = renderHook(() => useFieldPermissions(fields, ['admin']));

    expect(result.current).toHaveLength(1);
    // The original field is passed through — disabledWhen not overridden
    expect(result.current[0].disabledWhen).toBeUndefined();
  });

  it('handles empty userRoles array', () => {
    const fields: Field[] = [
      makeField({
        name: 'secret',
        permissions: { visible: ['admin'] },
      }),
      makeField({ name: 'public' }), // no permissions
    ];
    const { result } = renderHook(() => useFieldPermissions(fields, []));

    // 'secret' is hidden (no roles), 'public' is shown (no permissions)
    expect(result.current).toHaveLength(1);
    expect(result.current[0].name).toBe('public');
  });

  it('handles field with both visible and editable permissions', () => {
    const fields: Field[] = [
      makeField({
        name: 'salary',
        permissions: { visible: ['admin', 'hr'], editable: ['admin'] },
      }),
    ];

    // User is 'hr' — can see but not edit
    const { result } = renderHook(() => useFieldPermissions(fields, ['hr']));

    expect(result.current).toHaveLength(1);
    expect(result.current[0].name).toBe('salary');
    // forced disabled because 'hr' is not in editable=['admin']
    expect(result.current[0].disabledWhen).toBeDefined();
    expect(result.current[0].disabledWhen!({})).toBe(true);
  });

  it('shows field with no permissions and admin user without modification', () => {
    const fields: Field[] = [makeField({ name: 'name' })];
    const { result } = renderHook(() => useFieldPermissions(fields, ['admin', 'manager']));

    expect(result.current).toHaveLength(1);
    expect(result.current[0]).toMatchObject({ name: 'name', disabled: false });
    expect(result.current[0].disabledWhen).toBeUndefined();
  });
});

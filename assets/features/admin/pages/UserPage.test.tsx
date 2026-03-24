// @vitest-environment jsdom

import { describe, expect, it } from 'vitest';
import { applyFieldOperationFormSemantics } from '@core/crud/fieldOperationSemantics';
import { resolveSmartCrudFields } from '@core/crud/resolveSmartCrudFields';
import type { Field } from '@core/field/Field';
import { FieldType } from '@core/field/FieldType';
import { userResource } from './userResource';

type OperationAwareField = Field & {
  operation?: Parameters<typeof applyFieldOperationFormSemantics>[2];
};

const getPlainPasswordField = () => {
  const directives =
    userResource.fieldContract?.source === 'hydra' ? userResource.fieldContract.directives : [];

  const plainPasswordDirective = directives.find(
    (directive) => directive.kind === 'append' && directive.field.name === 'plainPassword',
  );

  expect(plainPasswordDirective).toBeDefined();

  if (!plainPasswordDirective || plainPasswordDirective.kind !== 'append') {
    throw new Error('Expected plainPassword append directive to be defined.');
  }

  return plainPasswordDirective.field;
};

describe('userResource', () => {
  it('uses the canonical field contract with a create-only plainPassword field', () => {
    expect(userResource.fields).toEqual([]);
    expect(userResource.fieldContract).toMatchObject({
      source: 'hydra',
      strategy: 'augment',
    });

    const plainPasswordDirective = getPlainPasswordField();

    expect(plainPasswordDirective).toMatchObject({
      name: 'plainPassword',
      type: FieldType.PASSWORD,
      label: 'Contraseña',
      visible: false,
      visibleOnForm: true,
      operation: {
        only: 'create',
        required: { create: true },
      },
    });
  });

  it('requires plainPassword on create and hides it from normal edit forms', () => {
    const plainPasswordField = getPlainPasswordField();
    const field = plainPasswordField as Parameters<typeof applyFieldOperationFormSemantics>[0];

    const createField = applyFieldOperationFormSemantics(
      field,
      'create',
      plainPasswordField.operation,
    );
    expect(createField.visibleOnForm).toBe(true);
    expect(createField.hidden).toBe(false);
    expect(createField.required).toBe(true);

    const editField = applyFieldOperationFormSemantics(field, 'edit', plainPasswordField.operation);
    expect(editField.visibleOnForm).toBe(false);
    expect(editField.hidden).toBe(true);
    expect(editField.required).toBe(false);
  });

  it('keeps plainPassword form-visible when appropriate without rendering it in the users list', () => {
    expect(userResource.fieldContract?.source).toBe('hydra');

    const resolvedFields = resolveSmartCrudFields({
      baselineFields: [
        { name: 'id', label: 'ID', type: FieldType.NUMBER, isIdentity: true } as Field,
        { name: 'username', label: 'Usuario', type: FieldType.TEXT } as Field,
      ],
      contract: userResource.fieldContract!,
    });

    const plainPasswordField = resolvedFields.find(
      (field): field is OperationAwareField => field.name === 'plainPassword',
    );

    expect(plainPasswordField).toBeDefined();

    if (!plainPasswordField) {
      throw new Error('Expected resolved plainPassword field to be defined.');
    }

    expect(plainPasswordField).toMatchObject({
      visible: false,
      visibleOnForm: true,
      operation: {
        only: 'create',
        required: { create: true },
      },
    });

    const datagridFields = resolvedFields.filter(
      (field) => field.isIdentity || field.visible !== false,
    );
    expect(datagridFields.map((field) => field.name)).toEqual(['id', 'username']);

    const createField = applyFieldOperationFormSemantics(
      plainPasswordField,
      'create',
      plainPasswordField.operation,
    );
    expect(createField.visibleOnForm).toBe(true);
    expect(createField.hidden).toBe(false);
    expect(createField.required).toBe(true);

    const editField = applyFieldOperationFormSemantics(
      plainPasswordField,
      'edit',
      plainPasswordField.operation,
    );
    expect(editField.visible).toBe(false);
    expect(editField.visibleOnForm).toBe(false);
    expect(editField.hidden).toBe(true);
    expect(editField.required).toBe(false);
  });
});

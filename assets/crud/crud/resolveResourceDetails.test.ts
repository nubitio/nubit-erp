import { describe, expect, it } from 'vitest';
import type { ResourceConfig } from './ResourceConfig';
import { resolveResourceDetails } from './resolveResourceDetails';
import { textField } from '../field/FieldBuilders';

const field = (name: string) => textField().name(name).label(name).build();

const baseResource = (overrides: Partial<ResourceConfig> = {}): ResourceConfig => ({
  id: 'resource',
  title: 'Resource',
  apiUrl: '/api/resources',
  fields: [field('id')],
  ...overrides,
});

describe('resolveResourceDetails', () => {
  it('prefers explicit gridDetail and formDetail over legacy detail config', () => {
    const legacyField = field('legacy');
    const gridField = field('grid');
    const formField = field('form');

    const resolved = resolveResourceDetails(baseResource({
      detailUrl: '/legacy/{id}/items',
      detailFields: [legacyField],
      gridDetail: { url: '/grid/{id}/items', fields: [gridField] },
      formDetail: { url: '/form/{id}/items', fields: [formField], required: true },
    }));

    expect(resolved.gridDetail).toEqual({ url: '/grid/{id}/items', fields: [gridField] });
    expect(resolved.formDetail).toEqual({ url: '/form/{id}/items', fields: [formField], required: true });
  });

  it('maps legacy detailFields/detailUrl to both grid and form detail', () => {
    const detailField = field('item');

    const resolved = resolveResourceDetails(baseResource({
      detailUrl: '/legacy/{id}/items',
      detailFields: [detailField],
      detailPropertyName: 'items',
      detailAllowAdding: false,
      detailAllowDeleting: true,
      detailAllowUpdating: false,
      detailRequired: true,
    }));

    expect(resolved.gridDetail).toEqual({ url: '/legacy/{id}/items', fields: [detailField] });
    expect(resolved.formDetail).toEqual({
      url: '/legacy/{id}/items',
      fields: [detailField],
      propertyName: 'items',
      allowAdding: false,
      allowDeleting: true,
      allowUpdating: false,
      required: true,
    });
  });

  it('uses legacy gridDetailFields only for grid detail', () => {
    const formField = field('formItem');
    const gridField = field('gridItem');

    const resolved = resolveResourceDetails(baseResource({
      detailUrl: '/legacy/{id}/items',
      detailFields: [formField],
      gridDetailFields: [gridField],
    }));

    expect(resolved.gridDetail).toEqual({ url: '/legacy/{id}/items', fields: [gridField] });
    expect(resolved.formDetail?.fields).toEqual([formField]);
  });

  it('does not create a grid detail when legacy config has fields without url', () => {
    const detailField = field('item');

    const resolved = resolveResourceDetails(baseResource({ detailFields: [detailField] }));

    expect(resolved.gridDetail).toBeUndefined();
    expect(resolved.formDetail).toEqual({ fields: [detailField] });
  });
});

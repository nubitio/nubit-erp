// @vitest-environment jsdom
/**
 * Regression tests for BUG-006
 *
 * Root cause: CategoryPage.tsx calls `defineResource('api/categories')` (no
 * leading slash). SmartCrudPage must normalise the apiUrl to `/api/categories`
 * before passing it to CrudPage so that axios always issues absolute-path
 * requests regardless of the current browser URL.
 *
 * Coverage:
 *   normalizeApiUrl helper (extracted logic):
 *     - URL with leading slash is returned unchanged
 *     - URL without leading slash gets one prepended
 *     - Empty string becomes '/'
 *     - URL that is already fully qualified (https://) is returned unchanged
 *
 *   defineResource(string) overload — apiUrl preserved as-is (no normalization
 *   in defineResource itself; normalization lives in SmartCrudPage):
 *     - defineResource('api/categories') stores apiUrl as 'api/categories'
 *     - defineResource('/api/categories') stores apiUrl as '/api/categories'
 *     - id is derived from the last URL segment in both cases
 *
 *   SmartCrudPage apiUrl normalization integration (via rendered component):
 *     - renders without crashing when apiUrl has no leading slash
 *     - DxDataGrid receives an apiUrl that starts with '/'
 */

import { describe, it, expect, vi } from 'vitest';
import React from 'react';
import { render } from '@testing-library/react';
import { defineResource } from './defineResource';

// ---------------------------------------------------------------------------
// Module-level mocks
// ---------------------------------------------------------------------------

// Capture the url prop passed to DxDataGrid so we can assert it is normalised
let capturedDataGridUrl: string | null;

vi.mock('../datagrid/DxDataGrid', () => ({
  DxDataGrid: (props: any) => {
    capturedDataGridUrl = props.url;
    return React.createElement('div', { 'data-testid': 'dx-data-grid' });
  },
}));

vi.mock('../dialog/DxDialog', () => ({
  DxDialog: ({ children }: any) =>
    React.createElement('div', { 'data-testid': 'dx-dialog' }, children),
}));

vi.mock('../form/DxForm', () => ({
  DxForm: React.forwardRef((_props: any, _ref: any) =>
    React.createElement('div', { 'data-testid': 'dx-form' }),
  ),
}));

vi.mock('./CrudPage.scss', () => ({}));

vi.mock('@features/movement', () => ({
  createCrudEvents: (id: string) => ({
    ADD: `${id}:add`,
    EDIT: `${id}:edit`,
    DELETE: `${id}:delete`,
    SAVE: `${id}:save`,
    CANCEL: `${id}:cancel`,
    SUCCESS: `${id}:success`,
    LOADING: `${id}:loading`,
  }),
}));

// Mock Hydra schema hook so SmartCrudPage doesn't need a network
vi.mock('./hydra/useResourceSchema', () => ({
  useResourceSchema: (_url: string, manualFields: any) => ({
    fields: manualFields ?? [],
    isLoading: false,
    error: null,
  }),
}));

// Mock auth context
vi.mock('../../contexts/auth', () => ({
  useAuth: () => ({ user: null }),
}));

// Mock rules hooks (no-ops for this test)
vi.mock('./rules/useFieldPermissions', () => ({
  useFieldPermissions: (fields: any[]) => fields,
}));

vi.mock('./rules/useConditionalRules', () => ({
  useConditionalRules: () => [],
}));

vi.mock('./rules/useDependsOn', () => ({
  useDependsOn: () => undefined,
}));

vi.mock('./routing/useRouting', () => ({
  useRouting: () => ({
    initialRecordId: null,
    initialIsNew: false,
    initialFilters: {},
    syncFilters: () => {},
  }),
}));

vi.mock('@tanstack/react-query', () => ({
  useQueryClient: () => ({ invalidateQueries: vi.fn() }),
}));

// ---------------------------------------------------------------------------
// Import after mocks
// ---------------------------------------------------------------------------

import { SmartCrudPage } from './SmartCrudPage';

// ---------------------------------------------------------------------------
// Inline normalizeApiUrl — mirrors the logic in SmartCrudPage Step 6
// (tested in isolation so the logic is validated independently of React)
// ---------------------------------------------------------------------------

function normalizeApiUrl(url: string): string {
  if (url.startsWith('/') || url.startsWith('http://') || url.startsWith('https://')) {
    return url;
  }
  return `/${url}`;
}

// ---------------------------------------------------------------------------
// normalizeApiUrl unit tests
// ---------------------------------------------------------------------------

describe('normalizeApiUrl (BUG-006 logic)', () => {
  it('returns a URL that already has a leading slash unchanged', () => {
    expect(normalizeApiUrl('/api/categories')).toBe('/api/categories');
  });

  it('prepends "/" to a URL that lacks a leading slash', () => {
    expect(normalizeApiUrl('api/categories')).toBe('/api/categories');
  });

  it('prepends "/" to a single-segment URL without a slash', () => {
    expect(normalizeApiUrl('products')).toBe('/products');
  });

  it('converts empty string to "/"', () => {
    expect(normalizeApiUrl('')).toBe('/');
  });

  it('returns an http:// URL unchanged', () => {
    expect(normalizeApiUrl('http://example.com/api/items')).toBe('http://example.com/api/items');
  });

  it('returns an https:// URL unchanged', () => {
    expect(normalizeApiUrl('https://example.com/api/items')).toBe('https://example.com/api/items');
  });

  it('does not double-prefix a URL that already starts with "/"', () => {
    const result = normalizeApiUrl('/api/products');
    expect(result.startsWith('//')).toBe(false);
    expect(result).toBe('/api/products');
  });
});

// ---------------------------------------------------------------------------
// defineResource(string) overload — apiUrl stored as-is
// ---------------------------------------------------------------------------

describe('defineResource(string) — apiUrl storage (BUG-006)', () => {
  it('stores apiUrl exactly as given when it lacks a leading slash', () => {
    const resource = defineResource('api/categories');
    // defineResource itself must NOT normalise — SmartCrudPage does it
    expect(resource.apiUrl).toBe('api/categories');
  });

  it('stores apiUrl exactly as given when it already has a leading slash', () => {
    const resource = defineResource('/api/products');
    expect(resource.apiUrl).toBe('/api/products');
  });

  it('derives id from the last URL path segment (no leading slash)', () => {
    const resource = defineResource('api/categories');
    expect(resource.id).toBe('categories');
  });

  it('derives id from the last URL path segment (with leading slash)', () => {
    const resource = defineResource('/api/products');
    expect(resource.id).toBe('products');
  });

  it('accepts overrides when called with a string URL', () => {
    const resource = defineResource('api/categories');
    expect(resource.apiUrl).toBe('api/categories');
    expect(resource.id).toBe('categories');
  });
});

// ---------------------------------------------------------------------------
// SmartCrudPage integration — apiUrl normalization
// ---------------------------------------------------------------------------

describe('SmartCrudPage apiUrl normalization (BUG-006)', () => {
  it('renders without crashing when apiUrl has no leading slash', () => {
    const resource = defineResource({
      id: 'categories',
      title: 'Categorías',
      apiUrl: 'api/categories',
      fields: [],
    });
    expect(() => {
      const { unmount } = render(React.createElement(SmartCrudPage, { resource }));
      unmount();
    }).not.toThrow();
  });

  it('passes an apiUrl starting with "/" to DxDataGrid when resource.apiUrl has no leading slash', () => {
    capturedDataGridUrl = null;
    const resource = defineResource({
      id: 'categories',
      title: 'Categorías',
      apiUrl: 'api/categories',
      fields: [],
    });
    const { unmount } = render(React.createElement(SmartCrudPage, { resource }));

    // DxDataGrid must receive '/api/categories', NOT 'api/categories'
    expect(capturedDataGridUrl).toBe('/api/categories');
    unmount();
  });

  it('passes an unchanged apiUrl to DxDataGrid when resource.apiUrl already has a leading slash', () => {
    capturedDataGridUrl = null;
    const resource = defineResource({
      id: 'products',
      title: 'Productos',
      apiUrl: '/api/products',
      fields: [],
    });
    const { unmount } = render(React.createElement(SmartCrudPage, { resource }));

    expect(capturedDataGridUrl).toBe('/api/products');
    unmount();
  });

  it('DxDataGrid url never starts with "//" (no double-slash)', () => {
    capturedDataGridUrl = null;
    const resource = defineResource({
      id: 'categories',
      title: 'Categorías',
      apiUrl: '/api/categories',
      fields: [],
    });
    const { unmount } = render(React.createElement(SmartCrudPage, { resource }));

    expect(String(capturedDataGridUrl).startsWith('//')).toBe(false);
    unmount();
  });
});

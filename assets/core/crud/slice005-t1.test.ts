// @vitest-environment jsdom
/**
 * Unit tests for SLICE-005 T1
 *
 * Coverage:
 *   - parseHydraDoc: extracts supportedOperations from supportedOperation (compact wire key)
 *     (both 'hydra:method' and 'method' key variants inside each entry, uppercasing, missing block)
 *   - usePermissions: 4-level precedence waterfall
 *     1. permissions.canX (explicit override)
 *     2. allowX           (legacy boolean alias)
 *     3. supportedOperations (inferred from Hydra doc)
 *     4. platform defaults (all-true for canAdd/Edit/Delete)
 *   - Backward-compat: resource with no supportedOperation → all-true defaults unchanged
 */

import { describe, it, expect } from 'vitest';
import { renderHook } from '@testing-library/react';
import { parseHydraDoc } from './hydra/openApiParser';
import { usePermissions } from './usePermissions';
import type { HydraApiDoc } from './hydra/types';
import type { ResourceConfig } from './ResourceConfig';

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

type HydraOperationFixture = { 'hydra:method'?: string; method?: string };

/** Minimal HydraApiDoc with optional Entrypoint collection operations. */
function makeDoc(
  operations: HydraOperationFixture[],
  entrypointOperations: HydraOperationFixture[] = [],
): HydraApiDoc {
  return {
    '@id': '/api/docs.jsonld',
    '@type': 'ApiDocumentation',
    supportedClass: [
      {
        '@id': '#Entrypoint',
        '@type': 'Class',
        title: 'Entrypoint',
        supportedProperty: [
          {
            '@type': 'SupportedProperty',
            title: 'category',
            property: {
              '@id': '#Entrypoint/category',
              '@type': 'Link',
              label: 'category',
              range: [
                { '@id': 'Collection' },
                {
                  'owl:equivalentClass': {
                    'owl:onProperty': { '@id': 'member' },
                    'owl:allValuesFrom': { '@id': '#Category' },
                  },
                },
              ],
              supportedOperation: entrypointOperations,
            },
          },
        ],
      },
      {
        '@id': '#Category',
        '@type': 'Class',
        title: 'Category',
        supportedProperty: [],
        // Wire format: compact key `supportedOperation` (no `hydra:` prefix)
        supportedOperation: operations,
      },
    ],
  };
}

/** HydraApiDoc with no supportedOperation on the class. */
const docWithoutOperations: HydraApiDoc = {
  '@id': '/api/docs.jsonld',
  '@type': 'ApiDocumentation',
  supportedClass: [
    {
      '@id': '#Category',
      '@type': 'Class',
      title: 'Category',
      supportedProperty: [],
      // no 'supportedOperation' key
    },
  ],
};

const baseResource = (): ResourceConfig => ({
  id: 'category',
  title: 'Categorías',
  apiUrl: '/api/categories',
  fields: [],
});

// ---------------------------------------------------------------------------
// parseHydraDoc — supportedOperations extraction
// ---------------------------------------------------------------------------

describe('parseHydraDoc — supportedOperations', () => {
  it('returns an empty array when supportedOperation is absent', () => {
    const schemas = parseHydraDoc(docWithoutOperations);
    expect(schemas['Category'].supportedOperations).toEqual([]);
  });

  it('extracts methods from hydra:method key and uppercases them', () => {
    const doc = makeDoc([
      { 'hydra:method': 'GET' },
      { 'hydra:method': 'POST' },
      { 'hydra:method': 'DELETE' },
    ]);
    const schemas = parseHydraDoc(doc);
    expect(schemas['Category'].supportedOperations).toEqual(['GET', 'POST', 'DELETE']);
  });

  it('extracts methods from plain method key (legacy API Platform variant)', () => {
    const doc = makeDoc([{ method: 'get' }, { method: 'put' }, { method: 'patch' }]);
    const schemas = parseHydraDoc(doc);
    // normalised to uppercase
    expect(schemas['Category'].supportedOperations).toEqual(['GET', 'PUT', 'PATCH']);
  });

  it('hydra:method takes precedence over method when both are present', () => {
    const doc = makeDoc([{ 'hydra:method': 'POST', method: 'GET' }]);
    const schemas = parseHydraDoc(doc);
    expect(schemas['Category'].supportedOperations).toEqual(['POST']);
  });

  it('filters out entries with no method key', () => {
    // An entry with neither key should be silently skipped (after .filter(Boolean))
    const doc = makeDoc([{}, { 'hydra:method': 'DELETE' }]);
    const schemas = parseHydraDoc(doc);
    expect(schemas['Category'].supportedOperations).toEqual(['DELETE']);
  });

  it('returns an empty array when supportedOperation list is empty', () => {
    const doc = makeDoc([]);
    const schemas = parseHydraDoc(doc);
    expect(schemas['Category'].supportedOperations).toEqual([]);
  });

  it('full CRUD class has all expected methods', () => {
    const doc = makeDoc([
      { 'hydra:method': 'GET' },
      { 'hydra:method': 'POST' },
      { 'hydra:method': 'PUT' },
      { 'hydra:method': 'PATCH' },
      { 'hydra:method': 'DELETE' },
    ]);
    const schemas = parseHydraDoc(doc);
    expect(schemas['Category'].supportedOperations).toEqual([
      'GET',
      'POST',
      'PUT',
      'PATCH',
      'DELETE',
    ]);
  });

  it('merges collection POST from Entrypoint with item operations from the class', () => {
    const doc = makeDoc(
      [{ 'hydra:method': 'GET' }, { 'hydra:method': 'PATCH' }, { 'hydra:method': 'DELETE' }],
      [{ 'hydra:method': 'GET' }, { 'hydra:method': 'POST' }],
    );
    const schemas = parseHydraDoc(doc);

    expect(schemas['Category'].supportedOperations).toEqual(['GET', 'POST', 'PATCH', 'DELETE']);
  });

  it('read-only resource (GET only) has no POST/PUT/PATCH/DELETE', () => {
    const doc = makeDoc([{ 'hydra:method': 'GET' }]);
    const schemas = parseHydraDoc(doc);
    expect(schemas['Category'].supportedOperations).toEqual(['GET']);
    expect(schemas['Category'].supportedOperations).not.toContain('POST');
    expect(schemas['Category'].supportedOperations).not.toContain('DELETE');
  });
});

// ---------------------------------------------------------------------------
// usePermissions — 4-level waterfall
// ---------------------------------------------------------------------------

describe('usePermissions — 4-level precedence waterfall', () => {
  // ── Level 4 (platform defaults): no manual config, no operations ──────────

  it('L4 platform defaults — no ops, no overrides → canAdd/Edit/Delete=true', () => {
    const { result } = renderHook(() => usePermissions(baseResource(), []));
    expect(result.current.canAdd).toBe(true);
    expect(result.current.canEdit).toBe(true);
    expect(result.current.canDelete).toBe(true);
    expect(result.current.canExport).toBe(false);
    expect(result.current.canBulkDelete).toBe(false);
  });

  // ── Level 3 (inferred from ops): ops present, no manual config ────────────

  it('L3 inferred — POST → canAdd=true, GET only → canAdd=false', () => {
    const ops = ['GET', 'POST', 'PATCH'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canAdd).toBe(true);
  });

  it('L3 inferred — PATCH → canEdit=true', () => {
    const ops = ['GET', 'PATCH'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canEdit).toBe(true);
  });

  it('L3 inferred — PUT → canEdit=true', () => {
    const ops = ['GET', 'PUT'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canEdit).toBe(true);
  });

  it('L3 inferred — DELETE → canDelete=true', () => {
    const ops = ['GET', 'DELETE'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canDelete).toBe(true);
  });

  it('L3 inferred — GET only → canAdd=false, canEdit=false, canDelete=false', () => {
    const ops = ['GET'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canAdd).toBe(false);
    expect(result.current.canEdit).toBe(false);
    expect(result.current.canDelete).toBe(false);
  });

  it('L3 inferred — no POST in ops → canAdd=false', () => {
    const ops = ['GET', 'PUT', 'DELETE'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canAdd).toBe(false);
  });

  it('L3 inferred — neither PUT nor PATCH in ops → canEdit=false', () => {
    const ops = ['GET', 'POST', 'DELETE'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canEdit).toBe(false);
  });

  it('L3 inferred — no DELETE in ops → canDelete=false', () => {
    const ops = ['GET', 'POST', 'PUT'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canDelete).toBe(false);
  });

  it('L3 inferred — full CRUD ops → all three true', () => {
    const ops = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canAdd).toBe(true);
    expect(result.current.canEdit).toBe(true);
    expect(result.current.canDelete).toBe(true);
  });

  // ── Level 2 wins over Level 3 (allowX overrides inferred ops) ─────────────

  it('L2 allowAdd:false beats L3 inferred POST→true', () => {
    const resource: ResourceConfig = { ...baseResource(), allowAdd: false };
    const ops = ['GET', 'POST', 'PATCH', 'DELETE'];
    const { result } = renderHook(() => usePermissions(resource, ops));
    expect(result.current.canAdd).toBe(false);
  });

  it('L2 allowEdit:false beats L3 inferred PATCH→true', () => {
    const resource: ResourceConfig = { ...baseResource(), allowEdit: false };
    const ops = ['GET', 'POST', 'PATCH', 'DELETE'];
    const { result } = renderHook(() => usePermissions(resource, ops));
    expect(result.current.canEdit).toBe(false);
  });

  it('L2 allowDelete:false beats L3 inferred DELETE→true', () => {
    const resource: ResourceConfig = { ...baseResource(), allowDelete: false };
    const ops = ['GET', 'POST', 'PATCH', 'DELETE'];
    const { result } = renderHook(() => usePermissions(resource, ops));
    expect(result.current.canDelete).toBe(false);
  });

  it('L2 allowAdd:true overrides L3 inferred canAdd=false (no POST in ops)', () => {
    const resource: ResourceConfig = { ...baseResource(), allowAdd: true };
    const ops = ['GET', 'DELETE']; // no POST → inferred canAdd=false
    const { result } = renderHook(() => usePermissions(resource, ops));
    // allowAdd:true (L2) must win over inferred false (L3)
    expect(result.current.canAdd).toBe(true);
  });

  // ── Level 1 wins over Level 2 and 3 (permissions.canX highest precedence) ─

  it('L1 permissions.canAdd:true beats L2 allowAdd:false', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      allowAdd: false,
      permissions: { canAdd: true },
    };
    const ops = ['GET', 'POST', 'DELETE'];
    const { result } = renderHook(() => usePermissions(resource, ops));
    expect(result.current.canAdd).toBe(true);
  });

  it('L1 permissions.canAdd:false beats L3 inferred POST→true', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      permissions: { canAdd: false },
    };
    const ops = ['GET', 'POST', 'PATCH', 'DELETE'];
    const { result } = renderHook(() => usePermissions(resource, ops));
    expect(result.current.canAdd).toBe(false);
  });

  it('L1 permissions.canDelete callable returning false beats inferred DELETE→true', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      permissions: { canDelete: () => false },
    };
    const ops = ['GET', 'POST', 'DELETE'];
    const { result } = renderHook(() => usePermissions(resource, ops));
    expect(result.current.canDelete).toBe(false);
  });

  // ── canExport / canBulkDelete are not inferred from ops ───────────────────

  it('canExport is not affected by ops — stays false by default', () => {
    const ops = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canExport).toBe(false);
  });

  it('canBulkDelete is not affected by ops — stays false by default', () => {
    const ops = ['GET', 'POST', 'DELETE'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canBulkDelete).toBe(false);
  });
});

// ---------------------------------------------------------------------------
// Backward-compatibility scenarios
// ---------------------------------------------------------------------------

describe('usePermissions — backward compatibility', () => {
  it('BC-1: defineResource with no allowX and no ops → all-true defaults (unchanged behavior)', () => {
    // This is the pre-SLICE-005 baseline — no regression allowed
    const resource = baseResource();
    const { result } = renderHook(() => usePermissions(resource, []));
    expect(result.current.canAdd).toBe(true);
    expect(result.current.canEdit).toBe(true);
    expect(result.current.canDelete).toBe(true);
  });

  it('BC-2: defineResource with allowAdd:false → still canAdd=false (manual override wins)', () => {
    const resource: ResourceConfig = { ...baseResource(), allowAdd: false };
    const { result } = renderHook(() => usePermissions(resource, []));
    expect(result.current.canAdd).toBe(false);
    // other perms still true
    expect(result.current.canEdit).toBe(true);
    expect(result.current.canDelete).toBe(true);
  });

  it('BC-3: resource with no supportedOperation in Hydra (empty ops) → same as today (all-true)', () => {
    // Simulates a resource whose Hydra class has no hydra:supportedOperation block
    const ops: string[] = []; // useResourceSchema returns [] when no operations found
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canAdd).toBe(true);
    expect(result.current.canEdit).toBe(true);
    expect(result.current.canDelete).toBe(true);
  });

  it('BC-4: permissions.canX callable still takes highest precedence over ops', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      permissions: {
        canAdd: () => false,
        canEdit: () => true,
        canDelete: () => false,
      },
    };
    const ops = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']; // all ops present
    const { result } = renderHook(() => usePermissions(resource, ops));
    // callables must win regardless of ops
    expect(result.current.canAdd).toBe(false);
    expect(result.current.canEdit).toBe(true);
    expect(result.current.canDelete).toBe(false);
  });

  it('BC-5: CategoryPage behavior — GET+POST+PATCH inferred → canAdd=true, canEdit=true, canDelete=false', () => {
    // Simulates a CategoryPage resource with read-write but no delete
    const ops = ['GET', 'POST', 'PATCH'];
    const { result } = renderHook(() => usePermissions(baseResource(), ops));
    expect(result.current.canAdd).toBe(true);
    expect(result.current.canEdit).toBe(true);
    expect(result.current.canDelete).toBe(false);
  });
});

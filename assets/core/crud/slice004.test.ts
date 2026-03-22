// @vitest-environment jsdom
/**
 * Unit tests for SLICE-004 features — T004-6
 *
 * Coverage:
 *   - usePermissions        (3-tier precedence, callables, memoisation)
 *   - createScopedEventBus  (isolation, shape, scopeId, resourceId prefix)
 *   - useSelectionState     (initial state, selection, clear, identity field, stability)
 *   - useColumnPreset       (no presets, defaultPreset, setPreset, null preset,
 *                            localStorage persist, localStorage restore)
 *   - AuditTrailPanel       (visible=false, url=null, loading state, success state)
 */

import { describe, it, expect, vi, beforeEach, afterEach, beforeAll } from 'vitest';
import React from 'react';
import { act } from 'react';
import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';
import { initCoreI18n } from '../i18n/initCoreI18n';

// ---------------------------------------------------------------------------
// Module-level mocks — hoisted by Vitest before any imports
// ---------------------------------------------------------------------------

// Prevent real DevExtreme from being imported in the React component tree.
vi.mock('../datagrid/DxDataGrid', () => ({
  DxDataGrid: (_props: any) => React.createElement('div', { 'data-testid': 'dx-data-grid' }),
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

// createCrudEvents mock — mimics the real implementation used in production.
// The scoped prefix is "${resourceId}:${scopeId}" so event values are "${prefix}:${verb}".
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

// ---------------------------------------------------------------------------
// Imports — after mocks are registered
// ---------------------------------------------------------------------------
import { renderHook, render, screen, waitFor } from '@testing-library/react';
import { of } from 'rxjs';
import { usePermissions } from './usePermissions';
import { createScopedEventBus } from '../event/createScopedEventBus';
import { useSelectionState } from './useSelectionState';
import { useColumnPreset } from './useColumnPreset';
import { AuditTrailPanel } from './AuditTrailPanel';
import { businessHttpClient } from '@shared/api/network';
import type { ResourceConfig } from './ResourceConfig';
import type { AuditEntry } from './AuditTrail';
import type { ColumnPreset } from './ColumnPreset';

// ---------------------------------------------------------------------------
// Shared fixture helpers
// ---------------------------------------------------------------------------

const baseResource = (): ResourceConfig => ({
  id: 'product',
  title: 'Productos',
  apiUrl: '/api/products',
  fields: [],
});

// ---------------------------------------------------------------------------
// usePermissions
// ---------------------------------------------------------------------------

describe('usePermissions', () => {
  it('returns platform defaults when no permissions or allow* flags are set', () => {
    const resource = baseResource();
    const { result } = renderHook(() => usePermissions(resource));

    expect(result.current.canAdd).toBe(true);
    expect(result.current.canEdit).toBe(true);
    expect(result.current.canDelete).toBe(true);
    expect(result.current.canExport).toBe(false);
    expect(result.current.canBulkDelete).toBe(false);
  });

  it('allowAdd: false overrides the canAdd default (true)', () => {
    const resource: ResourceConfig = { ...baseResource(), allowAdd: false };
    const { result } = renderHook(() => usePermissions(resource));

    expect(result.current.canAdd).toBe(false);
    // other permissions remain at default
    expect(result.current.canEdit).toBe(true);
    expect(result.current.canDelete).toBe(true);
  });

  it('permissions.canDelete: false overrides the default (higher precedence than allowDelete)', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      allowDelete: true, // lower-precedence alias says allow
      permissions: { canDelete: false }, // higher-precedence override says deny
    };
    const { result } = renderHook(() => usePermissions(resource));

    expect(result.current.canDelete).toBe(false);
  });

  it('permissions.canEdit as callable is resolved to boolean', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      permissions: { canEdit: () => false },
    };
    const { result } = renderHook(() => usePermissions(resource));

    expect(result.current.canEdit).toBe(false);
  });

  it('permissions.canAdd: true wins over allowAdd: false', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      allowAdd: false, // lower-precedence alias says deny
      permissions: { canAdd: true }, // higher-precedence override says allow
    };
    const { result } = renderHook(() => usePermissions(resource));

    expect(result.current.canAdd).toBe(true);
  });

  it('callable permissions.canExport returning true resolves to true', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      permissions: { canExport: () => true },
    };
    const { result } = renderHook(() => usePermissions(resource));

    expect(result.current.canExport).toBe(true);
  });

  it('memoization: same object reference across re-renders when resource.id unchanged', () => {
    const resource = baseResource();
    const { result, rerender } = renderHook(() => usePermissions(resource));

    const firstRef = result.current;
    rerender();
    // useMemo on resource.id → same object reference returned
    expect(result.current).toBe(firstRef);
  });

  it('all five permission keys are present in the returned object', () => {
    const resource = baseResource();
    const { result } = renderHook(() => usePermissions(resource));

    expect(result.current).toHaveProperty('canAdd');
    expect(result.current).toHaveProperty('canEdit');
    expect(result.current).toHaveProperty('canDelete');
    expect(result.current).toHaveProperty('canExport');
    expect(result.current).toHaveProperty('canBulkDelete');
  });
});

// ---------------------------------------------------------------------------
// createScopedEventBus
// ---------------------------------------------------------------------------

describe('createScopedEventBus', () => {
  it('returns an object with FormEventNames shape (ADD, EDIT, DELETE, SAVE, CANCEL, SUCCESS, LOADING)', () => {
    const bus = createScopedEventBus('order');

    expect(bus).toHaveProperty('ADD');
    expect(bus).toHaveProperty('EDIT');
    expect(bus).toHaveProperty('DELETE');
    expect(bus).toHaveProperty('SAVE');
    expect(bus).toHaveProperty('CANCEL');
    expect(bus).toHaveProperty('SUCCESS');
    expect(bus).toHaveProperty('LOADING');
  });

  it('_scopeId field is present and is a non-empty string', () => {
    const bus = createScopedEventBus('invoice');

    expect(typeof bus._scopeId).toBe('string');
    expect(bus._scopeId.length).toBeGreaterThan(0);
  });

  it('two calls with the same resourceId return different event name strings (isolation)', () => {
    const bus1 = createScopedEventBus('product');
    const bus2 = createScopedEventBus('product');

    // Each call generates a fresh random scopeId → event strings differ
    expect(bus1.ADD).not.toBe(bus2.ADD);
    expect(bus1._scopeId).not.toBe(bus2._scopeId);
  });

  it('event names contain the resourceId as a prefix', () => {
    const resourceId = 'customer';
    const bus = createScopedEventBus(resourceId);

    // All event name strings should start with "customer:"
    expect(bus.ADD).toMatch(new RegExp(`^${resourceId}:`));
    expect(bus.EDIT).toMatch(new RegExp(`^${resourceId}:`));
    expect(bus.DELETE).toMatch(new RegExp(`^${resourceId}:`));
    expect(bus.SAVE).toMatch(new RegExp(`^${resourceId}:`));
  });

  it('_scopeId is embedded in the event name strings', () => {
    const bus = createScopedEventBus('sale');

    // The scoped event name format is: "${resourceId}:${scopeId}:${verb}"
    expect(bus.ADD).toContain(bus._scopeId);
    expect(bus.SAVE).toContain(bus._scopeId);
  });
});

// ---------------------------------------------------------------------------
// useSelectionState
// ---------------------------------------------------------------------------

describe('useSelectionState', () => {
  it('initial state: selectedIds=[], selectedCount=0, hasSelection=false', () => {
    const { result } = renderHook(() => useSelectionState('id'));

    expect(result.current.selectedIds).toEqual([]);
    expect(result.current.selectedCount).toBe(0);
    expect(result.current.hasSelection).toBe(false);
  });

  it('onSelectionChanged([{id:1},{id:2}]) → selectedIds=[1,2], selectedCount=2, hasSelection=true', () => {
    const { result } = renderHook(() => useSelectionState('id'));

    act(() => {
      result.current.onSelectionChanged([{ id: 1 }, { id: 2 }]);
    });

    expect(result.current.selectedIds).toEqual([1, 2]);
    expect(result.current.selectedCount).toBe(2);
    expect(result.current.hasSelection).toBe(true);
  });

  it('clearSelection() resets to initial state', () => {
    const { result } = renderHook(() => useSelectionState('id'));

    act(() => {
      result.current.onSelectionChanged([{ id: 99 }, { id: 100 }]);
    });
    expect(result.current.hasSelection).toBe(true);

    act(() => {
      result.current.clearSelection();
    });

    expect(result.current.selectedIds).toEqual([]);
    expect(result.current.selectedCount).toBe(0);
    expect(result.current.hasSelection).toBe(false);
  });

  it('reads identity from a custom field name (not just "id")', () => {
    const { result } = renderHook(() => useSelectionState('uuid'));

    act(() => {
      result.current.onSelectionChanged([
        { uuid: 'abc-123', name: 'Alice' },
        { uuid: 'def-456', name: 'Bob' },
      ]);
    });

    expect(result.current.selectedIds).toEqual(['abc-123', 'def-456']);
  });

  it('returns stable clearSelection reference across re-renders', () => {
    const { result, rerender } = renderHook(() => useSelectionState('id'));

    const firstClearRef = result.current.clearSelection;
    rerender();
    expect(result.current.clearSelection).toBe(firstClearRef);
  });

  it('returns stable onSelectionChanged reference across re-renders', () => {
    const { result, rerender } = renderHook(() => useSelectionState('id'));

    const firstOnSelectionRef = result.current.onSelectionChanged;
    rerender();
    expect(result.current.onSelectionChanged).toBe(firstOnSelectionRef);
  });
});

// ---------------------------------------------------------------------------
// useColumnPreset
// ---------------------------------------------------------------------------

const presets: ColumnPreset[] = [
  { key: 'full', label: 'Completa', columns: ['id', 'name', 'price', 'stock'] },
  { key: 'compact', label: 'Compacta', columns: ['name', 'price'] },
];

// Minimal in-memory localStorage shim
function makeLocalStorageShim() {
  const store: Record<string, string> = {};
  return {
    getItem: vi.fn((key: string) => store[key] ?? null),
    setItem: vi.fn((key: string, value: string) => {
      store[key] = value;
    }),
    removeItem: vi.fn((key: string) => {
      delete store[key];
    }),
    store,
  };
}

describe('useColumnPreset', () => {
  let lsShim: ReturnType<typeof makeLocalStorageShim>;

  beforeEach(() => {
    lsShim = makeLocalStorageShim();
    vi.stubGlobal('localStorage', lsShim);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('visibleColumns is null when no presets are defined', () => {
    const resource = baseResource(); // no columnPresets
    const { result } = renderHook(() => useColumnPreset(resource));

    expect(result.current.visibleColumns).toBeNull();
    expect(result.current.activePreset).toBeNull();
  });

  it('initialises from resource.defaultPreset on first render', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      columnPresets: presets,
      defaultPreset: 'compact',
    };
    const { result } = renderHook(() => useColumnPreset(resource));

    expect(result.current.activePreset).toBe('compact');
    expect(result.current.visibleColumns).toEqual(['name', 'price']);
  });

  it('setPreset("compact") updates activePreset and visibleColumns', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      columnPresets: presets,
    };
    const { result } = renderHook(() => useColumnPreset(resource));

    act(() => {
      result.current.setPreset('compact');
    });

    expect(result.current.activePreset).toBe('compact');
    expect(result.current.visibleColumns).toEqual(['name', 'price']);
  });

  it('setPreset(null) → visibleColumns is null', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      columnPresets: presets,
      defaultPreset: 'full',
    };
    const { result } = renderHook(() => useColumnPreset(resource));

    act(() => {
      result.current.setPreset(null);
    });

    expect(result.current.activePreset).toBeNull();
    expect(result.current.visibleColumns).toBeNull();
  });

  it('persists the preset key to localStorage at "column-preset:${resource.id}"', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      id: 'widget',
      columnPresets: presets,
    };
    const { result } = renderHook(() => useColumnPreset(resource));

    act(() => {
      result.current.setPreset('full');
    });

    expect(lsShim.setItem).toHaveBeenCalledWith('column-preset:widget', 'full');
  });

  it('reads from localStorage on mount (restore)', () => {
    // Pre-populate the shim store before the hook mounts
    lsShim.store['column-preset:product'] = 'compact';

    const resource: ResourceConfig = {
      ...baseResource(),
      id: 'product',
      columnPresets: presets,
    };
    const { result } = renderHook(() => useColumnPreset(resource));

    expect(result.current.activePreset).toBe('compact');
    expect(result.current.visibleColumns).toEqual(['name', 'price']);
  });

  it('ignores unknown key in localStorage (falls back to defaultPreset)', () => {
    lsShim.store['column-preset:product'] = 'nonexistent-key';

    const resource: ResourceConfig = {
      ...baseResource(),
      columnPresets: presets,
      defaultPreset: 'full',
    };
    const { result } = renderHook(() => useColumnPreset(resource));

    // stored key is not a valid preset → falls back to defaultPreset
    expect(result.current.activePreset).toBe('full');
  });

  it('calls localStorage.removeItem when setPreset(null) is called', () => {
    const resource: ResourceConfig = {
      ...baseResource(),
      id: 'item',
      columnPresets: presets,
      defaultPreset: 'full',
    };
    const { result } = renderHook(() => useColumnPreset(resource));

    act(() => {
      result.current.setPreset(null);
    });

    expect(lsShim.removeItem).toHaveBeenCalledWith('column-preset:item');
  });
});

// ---------------------------------------------------------------------------
// AuditTrailPanel
// ---------------------------------------------------------------------------

const noop = () => undefined;
const apiResponse = <T>(data: T) => ({ data, headers: {} });

// Initialise i18next + react-i18next once for the AuditTrailPanel suite.
// AuditTrailPanel now uses useCoreTranslation() (SLICE-005), so the 'core'
// namespace must be registered before any render.
beforeAll(async () => {
  if (!i18next.isInitialized) {
    await i18next.use(initReactI18next).init({
      lng: 'es',
      fallbackLng: 'es',
      ns: ['core'],
      defaultNS: 'core',
      resources: {},
      interpolation: { escapeValue: false },
    });
  }
  initCoreI18n();
});

describe('AuditTrailPanel', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('renders null when visible=false', () => {
    const { container } = render(
      React.createElement(AuditTrailPanel, {
        url: '/api/audit/1',
        visible: false,
        onClose: noop,
      }),
    );

    expect(container.firstChild).toBeNull();
  });

  it('shows empty-state message when url=null and visible=true', () => {
    render(
      React.createElement(AuditTrailPanel, {
        url: null,
        visible: true,
        onClose: noop,
      }),
    );

    expect(screen.getByText('Seleccione un registro...')).toBeTruthy();
  });

  it('renders the panel header (title + close button) when visible=true', () => {
    render(
      React.createElement(AuditTrailPanel, {
        url: null,
        visible: true,
        onClose: noop,
      }),
    );

    expect(screen.getByText('Historial de cambios')).toBeTruthy();
    expect(screen.getByLabelText('Cerrar historial de cambios')).toBeTruthy();
  });

  it('shows loading state immediately before fetch resolves', async () => {
    let resolveRequest!: (value: ReturnType<typeof apiResponse<AuditEntry[]>>) => void;
    const pendingRequest = new Promise<ReturnType<typeof apiResponse<AuditEntry[]>>>((res) => {
      resolveRequest = res;
    });
    vi.spyOn(businessHttpClient, 'get').mockReturnValueOnce({
      subscribe: (observer: {
        next?: (value: { data: AuditEntry[] }) => void;
        error?: (error: unknown) => void;
      }) => {
        pendingRequest.then(
          (value) => observer.next?.(value),
          (error) => observer.error?.(error),
        );
        return { unsubscribe: () => undefined };
      },
    } as never);

    render(
      React.createElement(AuditTrailPanel, {
        url: '/api/audit/1',
        visible: true,
        onClose: noop,
      }),
    );

    // Loading text must be visible synchronously after render
    expect(screen.getByText('Cargando...')).toBeTruthy();

    // Settle the promise inside act() so React can flush the resulting state update
    // without triggering the "not wrapped in act" warning.
    await act(async () => {
      resolveRequest(apiResponse([]));
      await pendingRequest;
    });
  });

  it('renders entries after fetch resolves with an AuditEntry array', async () => {
    const entries: AuditEntry[] = [
      {
        id: 1,
        timestamp: '2026-03-16T10:00:00Z',
        user: 'admin@test.com',
        action: 'update',
        changes: {
          price: { before: 100, after: 120 },
        },
      },
      {
        id: 2,
        timestamp: '2026-03-15T09:00:00Z',
        user: 'user@test.com',
        action: 'create',
        changes: {},
      },
    ];

    vi.spyOn(businessHttpClient, 'get').mockReturnValueOnce(of(apiResponse(entries)) as never);

    render(
      React.createElement(AuditTrailPanel, {
        url: '/api/audit/42',
        visible: true,
        onClose: noop,
      }),
    );

    // Wait for fetch to resolve and state to update
    await waitFor(() => {
      expect(screen.getByText('admin@test.com')).toBeTruthy();
    });

    expect(screen.getByText('user@test.com')).toBeTruthy();
    // Action labels should appear
    expect(screen.getByText('Actualizado')).toBeTruthy();
    expect(screen.getByText('Creado')).toBeTruthy();
  });

  it('renders error state when fetch fails', async () => {
    vi.spyOn(businessHttpClient, 'get').mockReturnValueOnce({
      subscribe: (observer: { error?: (error: unknown) => void }) => {
        observer.error?.(new Error('HTTP 500'));
        return { unsubscribe: () => undefined };
      },
    } as never);

    render(
      React.createElement(AuditTrailPanel, {
        url: '/api/audit/99',
        visible: true,
        onClose: noop,
      }),
    );

    await waitFor(() => {
      expect(screen.getByText('Error al cargar el historial.')).toBeTruthy();
    });
  });

  it('renders empty-list message when fetch returns an empty array', async () => {
    vi.spyOn(businessHttpClient, 'get').mockReturnValueOnce(of(apiResponse([])) as never);

    render(
      React.createElement(AuditTrailPanel, {
        url: '/api/audit/7',
        visible: true,
        onClose: noop,
      }),
    );

    await waitFor(() => {
      expect(screen.getByText('No hay entradas de historial.')).toBeTruthy();
    });
  });

  it('uses custom renderEntry when provided', async () => {
    const entry: AuditEntry = {
      id: 10,
      timestamp: '2026-03-16T12:00:00Z',
      user: 'admin',
      action: 'delete',
      changes: {},
    };

    vi.spyOn(businessHttpClient, 'get').mockReturnValueOnce(of(apiResponse([entry])) as never);

    const customRenderer = (e: AuditEntry) =>
      React.createElement('div', { 'data-testid': `custom-${e.id}` }, `Custom: ${e.user}`);

    render(
      React.createElement(AuditTrailPanel, {
        url: '/api/audit/10',
        visible: true,
        onClose: noop,
        renderEntry: customRenderer,
      }),
    );

    await waitFor(() => {
      expect(screen.getByTestId('custom-10')).toBeTruthy();
    });

    expect(screen.getByText('Custom: admin')).toBeTruthy();
  });
});

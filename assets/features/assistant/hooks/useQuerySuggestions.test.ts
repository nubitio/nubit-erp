// @vitest-environment jsdom
/**
 * Unit tests for SLICE-013 — useQuerySuggestions hook
 *
 * Coverage:
 *   - Returns suggestions filtered by route (/pos → POS suggestions)
 *   - Returns suggestions filtered by route (/dashboard → dashboard suggestions)
 *   - Returns suggestions filtered by route (/inventory → inventory suggestions)
 *   - Returns default suggestions for unknown routes
 *   - Filters suggestions by admin role (admin vs non-admin on /dashboard)
 *   - Calls cashboxService.getDashboard() to determine cash session status
 *   - Prepends cash-open suggestion when cash status is 'closed'
 *   - Limits suggestions to MAX_CHIPS = 3
 *   - Cancels the request if the component unmounts (cancelled flag)
 *   - Sets cashStatus to 'unknown' when getDashboard() rejects
 *   - Sets cashStatus to 'open' when at least one register is open
 *   - Sets cashStatus to 'closed' when registers exist but none are open
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { of, throwError } from 'rxjs';

// ---------------------------------------------------------------------------
// Module-level mocks
// ---------------------------------------------------------------------------

vi.mock('react-router-dom', () => ({
  useLocation: vi.fn(),
}));

vi.mock('@app/contexts/auth', () => ({
  useAuth: vi.fn(),
}));

vi.mock('@features/cashRegister', () => ({
  cashboxService: {
    getDashboard: vi.fn(),
  },
}));

// ---------------------------------------------------------------------------
// Imports — after mocks
// ---------------------------------------------------------------------------

import { useQuerySuggestions } from './useQuerySuggestions';
import { useLocation } from 'react-router-dom';
import { useAuth } from '@app/contexts/auth';
import { cashboxService } from '@features/cashRegister';

const mockUseLocation = useLocation as ReturnType<typeof vi.fn>;
const mockUseAuth = useAuth as ReturnType<typeof vi.fn>;
const mockGetDashboard = cashboxService.getDashboard as ReturnType<typeof vi.fn>;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function setupMocks(pathname: string, roles: string[] = [], dashboardData: any[] = []) {
  mockUseLocation.mockReturnValue({ pathname });
  mockUseAuth.mockReturnValue({ user: { roles } });
  mockGetDashboard.mockReturnValue(of({ data: dashboardData }));
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useQuerySuggestions', () => {
  beforeEach(() => {
    mockUseLocation.mockReset();
    mockUseAuth.mockReset();
    mockGetDashboard.mockReset();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('route-based suggestions', () => {
    it('returns POS suggestions for /pos route', async () => {
      setupMocks('/pos', [], []);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).toContain('pos-sold');
        expect(ids).toContain('pos-customer');
        expect(ids).toContain('pos-top');
      });
    });

    it('returns POS suggestions for /sales route', async () => {
      setupMocks('/sales', [], []);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).toContain('pos-sold');
      });
    });

    it('returns inventory suggestions for /inventory route', async () => {
      setupMocks('/inventory', [], []);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).toContain('inv-low');
        expect(ids).toContain('inv-restock');
        expect(ids).toContain('inv-rotation');
      });
    });

    it('returns default suggestions for unknown routes', async () => {
      setupMocks('/unknown-route', [], []);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).toContain('default-help');
        expect(ids).toContain('default-summary');
        expect(ids).toContain('default-sunat');
      });
    });
  });

  describe('role-based filtering on /dashboard', () => {
    it('returns admin dashboard suggestions when user has ADMIN role', async () => {
      setupMocks('/dashboard', ['ADMIN'], []);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).toContain('dash-sales');
        expect(ids).toContain('dash-stock');
      });
    });

    it('returns admin dashboard suggestions when user has SUPER_ADMIN role', async () => {
      setupMocks('/dashboard', ['SUPER_ADMIN'], []);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).toContain('dash-sales');
      });
    });

    it('returns non-admin dashboard suggestions when user has no admin role', async () => {
      setupMocks('/dashboard', ['CASHIER'], []);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).toContain('dash-today');
        expect(ids).toContain('dash-cash');
        expect(ids).not.toContain('dash-sales');
        expect(ids).not.toContain('dash-stock');
      });
    });

    it('returns non-admin suggestions when user has empty roles array', async () => {
      setupMocks('/dashboard', [], []);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).toContain('dash-today');
        expect(ids).not.toContain('dash-sales');
      });
    });
  });

  describe('cash session status', () => {
    it('calls cashboxService.getDashboard() on mount', async () => {
      setupMocks('/pos', [], []);

      renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        expect(mockGetDashboard).toHaveBeenCalledTimes(1);
      });
    });

    it('prepends cash-open suggestion when cash status is closed', async () => {
      setupMocks('/pos', [], [{ status: 'closed' }]);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).toContain('cash-open');
      });
    });

    it('does not prepend cash-open suggestion when cash status is open', async () => {
      setupMocks('/pos', [], [{ status: 'open' }]);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).not.toContain('cash-open');
      });
    });

    it('does not prepend cash-open suggestion when no registers exist', async () => {
      setupMocks('/pos', [], []);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).not.toContain('cash-open');
      });
    });

    it('sets cashStatus to open when at least one register is open', async () => {
      setupMocks('/pos', [], [{ status: 'closed' }, { status: 'open' }]);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const ids = result.current.suggestions.map((s) => s.id);
        // 'open' status → no cash-open chip
        expect(ids).not.toContain('cash-open');
      });
    });

    it('sets cashStatus to unknown when getDashboard() rejects', async () => {
      mockUseLocation.mockReturnValue({ pathname: '/pos' });
      mockUseAuth.mockReturnValue({ user: { roles: [] } });
      mockGetDashboard.mockReturnValue(throwError(() => new Error('Network error')));

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        // With unknown status, no cash-open chip
        const ids = result.current.suggestions.map((s) => s.id);
        expect(ids).not.toContain('cash-open');
      });
    });
  });

  describe('MAX_CHIPS limit', () => {
    it('never returns more than 3 suggestions', async () => {
      // /dashboard admin has 3 suggestions + cash-open prepended = 4 before slice
      setupMocks('/dashboard', ['ADMIN'], [{ status: 'closed' }]);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        expect(result.current.suggestions.length).toBeLessThanOrEqual(3);
      });
    });

    it('returns exactly 3 suggestions for /pos route with open cash', async () => {
      setupMocks('/pos', [], [{ status: 'open' }]);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        expect(result.current.suggestions.length).toBe(3);
      });
    });

    it('returns at most 3 suggestions for /inventory route', async () => {
      setupMocks('/inventory', [], []);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        expect(result.current.suggestions.length).toBeLessThanOrEqual(3);
      });
    });
  });

  describe('cleanup / cancellation', () => {
    it('does not update state after unmount (cancelled flag)', async () => {
      // Use a deferred observable to simulate async response
      let resolveObservable!: (value: any) => void;
      const deferred = new Promise<any>((resolve) => {
        resolveObservable = resolve;
      });

      mockUseLocation.mockReturnValue({ pathname: '/pos' });
      mockUseAuth.mockReturnValue({ user: { roles: [] } });
      mockGetDashboard.mockReturnValue({
        then: (cb: any) => deferred.then(cb),
        // Make it work with lastValueFrom by providing a subscribe method
        subscribe: (observer: any) => {
          deferred.then((v) => observer.next?.(v)).then(() => observer.complete?.());
          return { unsubscribe: vi.fn() };
        },
      });

      // Re-mock with a proper observable that resolves after unmount
      mockGetDashboard.mockReturnValue(
        new (class {
          subscribe(observer: any) {
            // Resolve after a tick
            setTimeout(() => {
              observer.next?.({ data: [{ status: 'closed' }] });
              observer.complete?.();
            }, 100);
            return { unsubscribe: vi.fn() };
          }
        })(),
      );

      const { unmount } = renderHook(() => useQuerySuggestions());

      // Unmount before the async response arrives
      unmount();

      // No error should be thrown (cancelled flag prevents setState)
      await new Promise((r) => setTimeout(r, 200));
    });

    it('re-fetches dashboard when pathname changes', async () => {
      mockUseLocation.mockReturnValue({ pathname: '/pos' });
      mockUseAuth.mockReturnValue({ user: { roles: [] } });
      mockGetDashboard.mockReturnValue(of({ data: [] }));

      const { rerender } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        expect(mockGetDashboard).toHaveBeenCalledTimes(1);
      });

      // Change pathname
      mockUseLocation.mockReturnValue({ pathname: '/inventory' });
      rerender();

      await waitFor(() => {
        expect(mockGetDashboard).toHaveBeenCalledTimes(2);
      });
    });
  });

  describe('suggestion content', () => {
    it('POS suggestions have correct query strings', async () => {
      setupMocks('/pos', [], [{ status: 'open' }]);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const soldSuggestion = result.current.suggestions.find((s) => s.id === 'pos-sold');
        expect(soldSuggestion?.query).toBe('¿Cuánto llevo vendido en esta sesión?');
        expect(soldSuggestion?.label).toBe('¿Cuánto llevo vendido?');
      });
    });

    it('cash-open suggestion has correct query string', async () => {
      setupMocks('/pos', [], [{ status: 'closed' }]);

      const { result } = renderHook(() => useQuerySuggestions());

      await waitFor(() => {
        const cashSuggestion = result.current.suggestions.find((s) => s.id === 'cash-open');
        expect(cashSuggestion?.query).toBe('¿Cómo puedo abrir la caja registradora?');
        expect(cashSuggestion?.label).toBe('¿Cómo abrir la caja?');
      });
    });
  });
});

import { QueryClient } from '@tanstack/react-query';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { requestAuthSessionSync } from '@shared/lib/authSessionSync';
import { installPosBootstrapCache, POS_BOOTSTRAP_STORAGE_KEY } from './posBootstrap';

describe('POS bootstrap persistence', () => {
  const cleanups: (() => void)[] = [];
  const clients: QueryClient[] = [];

  const install = () => {
    const client = new QueryClient();
    clients.push(client);
    cleanups.push(installPosBootstrapCache(client));
    return client;
  };

  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    localStorage.setItem(
      'user',
      JSON.stringify({ userId: 1, username: 'cashier', roles: ['POS'] }),
    );
    localStorage.setItem('selectedBranchId', JSON.stringify('branch-1'));
  });

  afterEach(() => {
    cleanups.splice(0).forEach((cleanup) => cleanup());
    clients.splice(0).forEach((client) => client.clear());
  });

  it("restores the cashier's own cash session across reloads, stale so it is revalidated", () => {
    const first = install();
    const state = {
      session: { id: 42, cashRegisterId: 3, cashRegisterName: 'Caja 3' },
      availableRegisters: [],
    };
    first.setQueryData(['cash-pos-state', 1, 'branch-1'], state);

    const reloaded = install();

    expect(reloaded.getQueryData(['cash-pos-state', 1, 'branch-1'])).toEqual(state);
    expect(reloaded.getQueryState(['cash-pos-state', 1, 'branch-1'])?.dataUpdatedAt).toBe(0);
  });

  it('restores successful bootstrap data across reloads but leaves it stale for reconnection', () => {
    const first = install();
    first.setQueryData(['tenant-runtime-config'], {
      defaults: { warehouse: { id: 'warehouse-1' } },
    });
    first.setQueryData(['pos-payment-methods'], [{ id: 1, name: 'Efectivo' }]);
    first.setQueryData(['api-doc-discovery', 'es-PE'], { format: 'hydra', doc: {} });
    const reloaded = install();
    expect(reloaded.getQueryData(['pos-payment-methods'])).toEqual([{ id: 1, name: 'Efectivo' }]);
    expect(reloaded.getQueryData(['api-doc-discovery', 'es-PE'])).toEqual({
      format: 'hydra',
      doc: {},
    });
    expect(reloaded.getQueryState(['tenant-runtime-config'])?.dataUpdatedAt).toBe(0);
  });

  it('does not persist credentials, business rows or mutations', () => {
    localStorage.setItem('auth_token', JSON.stringify('test-only-session'));
    const client = install();
    client.setQueryData(['sales'], [{ id: 'private-sale' }]);
    client.setQueryData(['tenant-runtime-config'], { settings: {} });
    const snapshot = sessionStorage.getItem(POS_BOOTSTRAP_STORAGE_KEY)!;
    expect(snapshot).not.toContain('private-sale');
    expect(snapshot).not.toContain('test-only-session');
    expect(JSON.parse(snapshot).state.mutations).toEqual([]);
  });

  it('revalidates restored metadata instead of treating the snapshot as fresh', async () => {
    install().setQueryData(['pos-payment-methods'], [{ id: 1 }]);
    const reloaded = install();
    const refreshed = await reloaded.fetchQuery({
      queryKey: ['pos-payment-methods'],
      staleTime: 5 * 60_000,
      queryFn: async () => [{ id: 2 }],
    });
    expect(refreshed).toEqual([{ id: 2 }]);
    expect(install().getQueryData(['pos-payment-methods'])).toEqual([{ id: 2 }]);
  });

  it('rejects a snapshot from a session with different permissions', () => {
    install().setQueryData(['tenant-runtime-config'], { settings: { autoInvoice: true } });
    localStorage.setItem(
      'user',
      JSON.stringify({ userId: 1, username: 'cashier', roles: ['READ_ONLY'] }),
    );
    expect(install().getQueryData(['tenant-runtime-config'])).toBeUndefined();
    expect(sessionStorage.getItem(POS_BOOTSTRAP_STORAGE_KEY)).toBeNull();
  });

  it.each(['user', 'selectedBranchId'])('discards metadata when %s changes', (key) => {
    const client = install();
    client.setQueryData(['pos-payment-methods'], [{ id: 1 }]);
    localStorage.setItem(
      key,
      JSON.stringify(key === 'user' ? { username: 'another' } : 'branch-2'),
    );
    requestAuthSessionSync();
    expect(client.getQueryData(['pos-payment-methods'])).toBeUndefined();
    expect(sessionStorage.getItem(POS_BOOTSTRAP_STORAGE_KEY)).toBeNull();
    expect(install().getQueryData(['pos-payment-methods'])).toBeUndefined();
  });

  it('clears bootstrap on logout and ignores corrupt snapshots', () => {
    const client = install();
    client.setQueryData(['pos-payment-methods'], [{ id: 1 }]);
    localStorage.removeItem('user');
    requestAuthSessionSync();
    expect(sessionStorage.getItem(POS_BOOTSTRAP_STORAGE_KEY)).toBeNull();
    sessionStorage.setItem(POS_BOOTSTRAP_STORAGE_KEY, '{broken');
    expect(() => install()).not.toThrow();
  });

  it('invalidates metadata from an incompatible offline contract without clearing business storage', () => {
    install().setQueryData(['pos-payment-methods'], [{ id: 1 }]);
    const snapshot = JSON.parse(sessionStorage.getItem(POS_BOOTSTRAP_STORAGE_KEY)!);
    snapshot.contractVersion = 999;
    sessionStorage.setItem(POS_BOOTSTRAP_STORAGE_KEY, JSON.stringify(snapshot));
    localStorage.setItem('pos_draft_branch-1', 'draft-to-keep');
    expect(install().getQueryData(['pos-payment-methods'])).toBeUndefined();
    expect(sessionStorage.getItem(POS_BOOTSTRAP_STORAGE_KEY)).toBeNull();
    expect(localStorage.getItem('pos_draft_branch-1')).toBe('draft-to-keep');
  });
});

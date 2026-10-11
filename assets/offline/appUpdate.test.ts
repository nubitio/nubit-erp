import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  applyAppUpdate,
  checkAppVersion,
  getAppUpdateState,
  installUpdateAwareFetch,
  requiresClientUpdate,
  runCriticalOperation,
} from './appUpdate';
import { exceptionalFetch } from '@shared/api/network';
import policy from '../../config/app-update.json';

describe('controlled application updates', () => {
  const request = vi.fn();
  const release = (minimumOfflineContractVersion = policy.offlineContractVersion) =>
    Response.json({ revision: null, minimumOfflineContractVersion });

  beforeEach(async () => {
    vi.stubGlobal('navigator', { onLine: true });
    request.mockReset().mockResolvedValue(release());
    vi.stubGlobal('fetch', request);
    await checkAppVersion();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('recognizes a required contract update separately from a compatible deployment', () => {
    expect(
      requiresClientUpdate({
        revision: 'new-app.js',
        minimumOfflineContractVersion: policy.offlineContractVersion,
      }),
    ).toBe(false);
    expect(
      requiresClientUpdate({
        revision: 'new-app.js',
        minimumOfflineContractVersion: policy.offlineContractVersion + 1,
      }),
    ).toBe(true);
  });

  it('does not activate an update during a checkout and releases the busy state on failure', async () => {
    let fail!: (error: Error) => void;
    const operation = runCriticalOperation(
      () =>
        new Promise<void>((_resolve, reject) => {
          fail = reject;
        }),
    );
    expect(getAppUpdateState().busy).toBe(1);
    await applyAppUpdate();
    expect(getAppUpdateState().updating).toBe(false);
    fail(new Error('payment failed'));
    await expect(operation).rejects.toThrow('payment failed');
    expect(getAppUpdateState().busy).toBe(0);
  });

  it('blocks new operations when the server requires an update but preserves the current operation', async () => {
    let finish!: () => void;
    const current = runCriticalOperation(
      () =>
        new Promise<void>((resolve) => {
          finish = resolve;
        }),
    );
    request.mockResolvedValue(release(policy.offlineContractVersion + 1));
    await checkAppVersion();
    expect(getAppUpdateState().required).toBe(true);
    const next = vi.fn().mockResolvedValue(undefined);
    await expect(runCriticalOperation(next)).rejects.toThrow('Actualiza');
    expect(next).not.toHaveBeenCalled();
    finish();
    await current;
    expect(getAppUpdateState().busy).toBe(0);
  });

  it('does not activate when another tab holds the critical-operation lock', async () => {
    vi.stubGlobal('navigator', {
      onLine: true,
      locks: { request: vi.fn(async (_name, _options, callback) => callback(null)) },
    });
    await applyAppUpdate();
    expect(navigator.locks.request).toHaveBeenCalledWith(
      'nubit-app-critical-operation',
      { mode: 'exclusive', ifAvailable: true },
      expect.any(Function),
    );
    expect(getAppUpdateState().updating).toBe(false);
    expect(getAppUpdateState().error).toContain('otra pestaña');
  });

  it('retains a required-update notice when the network disappears', async () => {
    request.mockResolvedValue(release(policy.offlineContractVersion + 1));
    await checkAppVersion();
    vi.stubGlobal('navigator', { onLine: false });
    await checkAppVersion();
    await applyAppUpdate();
    expect(getAppUpdateState().required).toBe(true);
    expect(getAppUpdateState().error).toContain('internet');
  });

  it('blocks CRUD writes while allowing reads and logout during a required update', async () => {
    request.mockResolvedValue(release(policy.offlineContractVersion + 1));
    await checkAppVersion();
    request.mockReset().mockResolvedValue(Response.json({}));
    installUpdateAwareFetch();
    await expect(
      exceptionalFetch('/api/products/1', { method: 'PATCH' }, 'sdk-required'),
    ).rejects.toThrow('Actualiza');
    expect(request).not.toHaveBeenCalled();
    await exceptionalFetch('/api/products', undefined, 'sdk-required');
    await exceptionalFetch('/api/auth/logout', { method: 'POST' }, 'sdk-required');
    expect(request).toHaveBeenCalledTimes(2);
  });

  it('sends the client contract and recognizes a server-side upgrade rejection', async () => {
    request
      .mockReset()
      .mockResolvedValue(Response.json({ errorCode: 'APP_UPDATE_REQUIRED' }, { status: 426 }));
    installUpdateAwareFetch();
    const response = await exceptionalFetch('/api/products/1', { method: 'PATCH' }, 'sdk-required');
    expect(new Headers(request.mock.calls[0][1].headers).get('X-App-Contract-Version')).toBe(
      String(policy.offlineContractVersion),
    );
    expect(getAppUpdateState().required).toBe(true);
    expect(await response.json()).toEqual({ errorCode: 'APP_UPDATE_REQUIRED' });
  });
});

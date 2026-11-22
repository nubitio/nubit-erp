import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { httpClient } from '@shared/api/HttpClient';
import {
  REFRESH_WHEN_REMAINING_MS,
  canonicalString,
  hmacSha256Hex,
  needsRefresh,
  readCredential,
  refreshOfflineCredential,
  sha256Hex,
  signOfflineSale,
  type OfflineCredential,
} from './offlineCredential';

vi.mock('@shared/api/HttpClient', () => ({ httpClient: { post: vi.fn() } }));
vi.mock('@nubitio/core', () => ({ getCoreApiBaseUrl: () => '/api/' }));
vi.mock('./outboxOwnership', () => ({ currentUserId: () => '7' }));

// The same vector as tests/Identity/Offline/OfflineSignatureTest.php: till and server must agree.
const VECTOR = {
  secretBase64: 'Ll+EAXHlBWi/euB9Qrm9/bdt0NkRonhTbu01mzKZ5Tg=',
  body: '{"writeItems":[{"unitPrice":"9.80","description":"POLLO ENTERO X KG"}],"note":"ñ"}',
  bodyHash: 'd50d2693cc3712f3661164bc96cdc4c42024feddb931b5ac10e89759c377cb01',
  idempotencyKey: '11111111-2222-3333-4444-555555555555',
  signedAt: 1_799_990_000,
  signature: 'db2656f676cda23ee77011107e5678545649cc62738c9cd9c8b6049643e870bf',
};

const credential = (overrides: Partial<OfflineCredential> = {}): OfflineCredential => ({
  id: 'cred-1',
  userId: 7,
  secret: VECTOR.secretBase64,
  issuedAt: new Date((VECTOR.signedAt - 600) * 1000).toISOString(),
  expiresAt: new Date(1_800_000_000 * 1000).toISOString(),
  ...overrides,
});

const store = (value: OfflineCredential) =>
  window.localStorage.setItem('pos_offline_credential_7', JSON.stringify(value));

beforeEach(() => {
  window.localStorage.clear();
  vi.mocked(httpClient.post).mockReset();
});
afterEach(() => vi.unstubAllGlobals());

describe('signature (shared vector with the server)', () => {
  it('hashes the body as UTF-8, like PHP does over the raw bytes', async () => {
    expect(await sha256Hex(VECTOR.body)).toBe(VECTOR.bodyHash);
  });

  it('builds the canonical string the server builds', () => {
    expect(canonicalString('cred-1', VECTOR.idempotencyKey, VECTOR.signedAt, VECTOR.bodyHash)).toBe(
      `v1\ncred-1\n${VECTOR.idempotencyKey}\n1799990000\n${VECTOR.bodyHash}`,
    );
  });

  it('signs with the derived key and yields the server signature', async () => {
    const canonical = canonicalString(
      'cred-1',
      VECTOR.idempotencyKey,
      VECTOR.signedAt,
      VECTOR.bodyHash,
    );
    expect(await hmacSha256Hex(VECTOR.secretBase64, canonical)).toBe(VECTOR.signature);
  });
});

describe('signOfflineSale', () => {
  it('returns the three headers the server reads', async () => {
    store(credential());

    const headers = await signOfflineSale({
      userId: '7',
      idempotencyKey: VECTOR.idempotencyKey,
      bodyText: VECTOR.body,
      nowMs: VECTOR.signedAt * 1000,
    });

    expect(headers).toEqual({
      'X-Offline-Credential': 'cred-1',
      'X-Offline-Signed-At': '1799990000',
      'X-Offline-Signature': VECTOR.signature,
    });
  });

  it('does not sign without a credential for that user', async () => {
    store(credential());
    expect(
      await signOfflineSale({
        userId: '8',
        idempotencyKey: VECTOR.idempotencyKey,
        bodyText: '{}',
        nowMs: 1,
      }),
    ).toBeNull();
    expect(
      await signOfflineSale({
        userId: null,
        idempotencyKey: VECTOR.idempotencyKey,
        bodyText: '{}',
        nowMs: 1,
      }),
    ).toBeNull();
  });

  it('does not sign with an expired credential: the sale goes unsigned instead of being refused', async () => {
    store(credential());
    expect(
      await signOfflineSale({
        userId: '7',
        idempotencyKey: VECTOR.idempotencyKey,
        bodyText: '{}',
        nowMs: (1_800_000_000 + 1) * 1000,
      }),
    ).toBeNull();
  });

  it('does not sign where Web Crypto is unavailable (an insecure context)', async () => {
    store(credential());
    vi.stubGlobal('crypto', {});
    expect(
      await signOfflineSale({
        userId: '7',
        idempotencyKey: VECTOR.idempotencyKey,
        bodyText: '{}',
        nowMs: VECTOR.signedAt * 1000,
      }),
    ).toBeNull();
  });

  it('a different body gives a different signature', async () => {
    store(credential());
    const sign = (bodyText: string) =>
      signOfflineSale({
        userId: '7',
        idempotencyKey: VECTOR.idempotencyKey,
        bodyText,
        nowMs: VECTOR.signedAt * 1000,
      });

    expect((await sign('{"a":1}'))?.['X-Offline-Signature']).not.toBe(
      (await sign('{"a":2}'))?.['X-Offline-Signature'],
    );
  });
});

describe('credential refresh', () => {
  it('needs one when none is held or it is about to expire', () => {
    const now = Date.parse('2026-11-22T00:00:00Z');
    expect(needsRefresh(null, now)).toBe(true);
    expect(
      needsRefresh(
        credential({ expiresAt: new Date(now + REFRESH_WHEN_REMAINING_MS - 1).toISOString() }),
        now,
      ),
    ).toBe(true);
    expect(
      needsRefresh(
        credential({ expiresAt: new Date(now + REFRESH_WHEN_REMAINING_MS + 60_000).toISOString() }),
        now,
      ),
    ).toBe(false);
  });

  it('asks the server once and keeps the answer', async () => {
    vi.mocked(httpClient.post).mockResolvedValue({
      data: credential({ expiresAt: new Date(Date.now() + 72 * 3600 * 1000).toISOString() }),
    } as never);

    await refreshOfflineCredential();
    await refreshOfflineCredential();

    expect(httpClient.post).toHaveBeenCalledTimes(1);
    expect(readCredential('7')?.id).toBe('cred-1');
  });

  it('never throws when the server refuses (no permission to sell offline) or is unreachable', async () => {
    vi.mocked(httpClient.post).mockRejectedValue(new Error('403'));

    await expect(refreshOfflineCredential()).resolves.toBeUndefined();
    expect(readCredential('7')).toBeNull();
  });
});

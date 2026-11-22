import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { currentUserId } from './outboxOwnership';

/**
 * Signing offline sales, so the server knows who made them whoever's session delivers them.
 *
 * While online, the signed-in user's till asks the server for a credential (a key valid a few
 * days, `POST /api/pos/offline-credential`) and keeps it. A sale made without connection is
 * signed when it is created — with the exact body that will be sent — and the signature
 * travels with it in the outbox. The server verifies it and attributes the sale to the signer.
 *
 * Must produce the same bytes as src/Identity/Offline/OfflineSignature.php; the fixed vector in
 * offlineCredential.test.ts is the same one asserted there.
 */
export interface OfflineCredential {
  id: string;
  userId: number;
  /** base64 of the 32-byte key. Stays in this browser, under the user's own sign-in. */
  secret: string;
  issuedAt: string;
  expiresAt: string;
}

/** Ask for a new credential when less than this is left, so one never runs out while online. */
export const REFRESH_WHEN_REMAINING_MS = 24 * 60 * 60 * 1000;

const storageKey = (userId: string) => `pos_offline_credential_${userId}`;

export const readCredential = (userId: string): OfflineCredential | null => {
  try {
    const raw = window.localStorage.getItem(storageKey(userId));
    return raw ? (JSON.parse(raw) as OfflineCredential) : null;
  } catch {
    return null;
  }
};

const storeCredential = (userId: string, credential: OfflineCredential) => {
  try {
    window.localStorage.setItem(storageKey(userId), JSON.stringify(credential));
  } catch {
    /* private mode / quota: sales are then queued unsigned and keep their owner binding */
  }
};

export const needsRefresh = (credential: OfflineCredential | null, nowMs: number): boolean =>
  credential === null || Date.parse(credential.expiresAt) - nowMs < REFRESH_WHEN_REMAINING_MS;

/** Gets a credential for the signed-in user if they have none, or it is about to expire. Never throws. */
export const refreshOfflineCredential = async (): Promise<void> => {
  const userId = currentUserId();
  if (userId === null || !needsRefresh(readCredential(userId), Date.now())) return;

  try {
    const { data } = await httpClient.post<OfflineCredential>(
      `${getCoreApiBaseUrl()}pos/offline-credential`,
      {},
      { errorPresentation: 'inline' },
    );
    storeCredential(userId, data);
  } catch {
    /* no permission to sell offline, or no connection: nothing to sign with, and that is fine */
  }
};

// ── Signature (mirrors OfflineSignature.php) ────────────────────────────────────────────

const encoder = new TextEncoder();

const toHex = (buffer: ArrayBuffer): string =>
  Array.from(new Uint8Array(buffer), (byte) => byte.toString(16).padStart(2, '0')).join('');

const fromBase64 = (value: string): Uint8Array<ArrayBuffer> =>
  Uint8Array.from(atob(value), (char) => char.charCodeAt(0));

export const sha256Hex = async (text: string): Promise<string> =>
  toHex(await crypto.subtle.digest('SHA-256', encoder.encode(text)));

export const hmacSha256Hex = async (secretBase64: string, message: string): Promise<string> => {
  const key = await crypto.subtle.importKey(
    'raw',
    fromBase64(secretBase64),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign'],
  );
  return toHex(await crypto.subtle.sign('HMAC', key, encoder.encode(message)));
};

export const canonicalString = (
  credentialId: string,
  idempotencyKey: string,
  signedAt: number,
  bodyHash: string,
): string => ['v1', credentialId, idempotencyKey, String(signedAt), bodyHash].join('\n');

export type OfflineSignatureHeaders = {
  'X-Offline-Credential': string;
  'X-Offline-Signed-At': string;
  'X-Offline-Signature': string;
};

/**
 * Signs a sale made now. Returns null when it cannot be signed — no Web Crypto (an insecure
 * context), no credential for this user, or an expired one — and the sale is then queued
 * unsigned, attributed to the signed-in user at delivery as before.
 */
export const signOfflineSale = async (input: {
  userId: string | null;
  idempotencyKey: string;
  bodyText: string;
  nowMs: number;
}): Promise<OfflineSignatureHeaders | null> => {
  if (input.userId === null || typeof crypto === 'undefined' || !crypto.subtle) return null;

  const credential = readCredential(input.userId);
  if (credential === null) return null;

  const signedAt = Math.floor(input.nowMs / 1000);
  if (signedAt > Date.parse(credential.expiresAt) / 1000) return null;

  try {
    const bodyHash = await sha256Hex(input.bodyText);
    const signature = await hmacSha256Hex(
      credential.secret,
      canonicalString(credential.id, input.idempotencyKey, signedAt, bodyHash),
    );
    return {
      'X-Offline-Credential': credential.id,
      'X-Offline-Signed-At': String(signedAt),
      'X-Offline-Signature': signature,
    };
  } catch {
    return null;
  }
};

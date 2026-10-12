export const AUTH_SESSION_STATE_KEY = 'efact_auth_session_v1';
export const AUTH_EPOCH_HEADER = 'X-Nubit-Auth-Epoch';

interface AuthSessionState {
  epoch: string;
  generation: string;
  active?: boolean;
  reason?: 'expired' | 'logout';
}

export function getAuthSessionState(): AuthSessionState {
  try {
    const stored = JSON.parse(localStorage.getItem(AUTH_SESSION_STATE_KEY) ?? 'null');
    if (typeof stored?.epoch === 'string' && typeof stored?.generation === 'string') return stored;
  } catch {
    /* Legacy sessions can still renew through their cookies. */
  }
  return { epoch: 'legacy', generation: 'legacy' };
}

const marker = () =>
  typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;

export function beginAuthSession(): void {
  localStorage.setItem(
    AUTH_SESSION_STATE_KEY,
    JSON.stringify({ epoch: marker(), generation: marker(), active: true }),
  );
}

export function advanceAuthGeneration(): void {
  localStorage.setItem(
    AUTH_SESSION_STATE_KEY,
    JSON.stringify({
      ...getAuthSessionState(),
      generation: marker(),
      active: true,
      reason: undefined,
    }),
  );
}

export function endAuthSession(reason: 'expired' | 'logout'): void {
  const previous = getAuthSessionState();
  const epoch = reason === 'logout' ? marker() : previous.epoch;
  localStorage.setItem(
    AUTH_SESSION_STATE_KEY,
    JSON.stringify({ ...previous, epoch, active: false, reason }),
  );
}

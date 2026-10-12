import { StorageUtils } from './StorageUtils';
import { endAuthSession, getAuthSessionState } from './authSessionState';
import toast from './SharedUtils';
let notifiedEpoch: string | null = null;

export const SESSION_EXPIRED_EVENT = 'efact:session-expired';

export function dispatchSessionExpiredEvent(): void {
  window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
}

export function clearAuthSessionStorage(): void {
  StorageUtils.removeItem('auth_token');
  StorageUtils.removeItem('user');
  StorageUtils.removeItem('selectedBranchId');
  StorageUtils.removeItem('selectedBranchName');
}

export function handleSessionExpired(): boolean {
  const epoch = getAuthSessionState().epoch;
  if (notifiedEpoch === epoch) return false;
  notifiedEpoch = epoch;
  endAuthSession('expired');
  clearAuthSessionStorage();
  toast('Su sesión ha expirado', 'error');
  dispatchSessionExpiredEvent();
  return true;
}

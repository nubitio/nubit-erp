import { StorageUtils } from './StorageUtils';

export const SESSION_EXPIRED_EVENT = 'efact:session-expired';

export function dispatchSessionExpiredEvent(): void {
  window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
}

export function clearAuthSessionStorage(): void {
  StorageUtils.removeItem('user');
  StorageUtils.removeItem('selectedBranchId');
  StorageUtils.removeItem('selectedBranchName');
}

export function handleSessionExpired(): void {
  clearAuthSessionStorage();
  dispatchSessionExpiredEvent();
}

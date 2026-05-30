export const SESSION_EXPIRED_EVENT = 'efact:session-expired';

export function dispatchSessionExpiredEvent(): void {
  window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
}

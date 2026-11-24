import { getCoreApiBaseUrl } from '@nubitio/core';

/** The closing report of a shift as a PDF, in a new tab. Only a closed shift has one. */
export function openClosureReport(sessionId: number | string) {
  window.open(`${getCoreApiBaseUrl()}cash-sessions/${sessionId}/closure-report`, '_blank');
}

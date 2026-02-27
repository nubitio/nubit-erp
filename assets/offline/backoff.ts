const RETRY_SCHEDULE_MS = [1000, 3000, 10000, 30000, 120000, 300000];

export const nextRetryDelayMs = (attempts: number) =>
  RETRY_SCHEDULE_MS[Math.min(Math.max(attempts - 1, 0), RETRY_SCHEDULE_MS.length - 1)];

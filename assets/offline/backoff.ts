/**
 * Retry backoff schedule for offline outbox sync.
 *
 * Designed for unstable connectivity in Peru (rural areas, power outages).
 * The schedule ramps quickly for transient errors, then plateaus at 10 minutes
 * so that prolonged outages (1–6 hours) are handled without exhausting the
 * attempt counter prematurely.
 *
 * Schedule (attempt → delay before next try):
 *   1 →  5s   (immediate retry after first failure)
 *   2 →  15s
 *   3 →  30s
 *   4 →  1min
 *   5 →  2min
 *   6 →  5min
 *   7+ → 10min (plateau — survives ~6h outage within MAX_SYNC_ATTEMPTS)
 */
const RETRY_SCHEDULE_MS = [
  5_000, // attempt 1 → retry in 5s
  15_000, // attempt 2 → retry in 15s
  30_000, // attempt 3 → retry in 30s
  60_000, // attempt 4 → retry in 1min
  120_000, // attempt 5 → retry in 2min
  300_000, // attempt 6 → retry in 5min
  600_000, // attempt 7+ → retry in 10min (plateau)
];

/**
 * Returns the delay in milliseconds before the next retry attempt.
 * Clamps to the last entry in the schedule once attempts exceed its length.
 */
export const nextRetryDelayMs = (attempts: number): number =>
  RETRY_SCHEDULE_MS[Math.min(Math.max(attempts - 1, 0), RETRY_SCHEDULE_MS.length - 1)];

/**
 * Maximum sync attempts before an outbox item is permanently failed.
 *
 * With a 10-minute plateau, 72 attempts ≈ 72 × 10min = ~12 hours of total
 * retry window (plus the ramp-up time for the first 6 attempts).
 * This covers typical power/connectivity outages in rural Peru.
 *
 * A merchant can always trigger a manual retry via retrySaleSync(), which
 * resets the counter and gives another full window.
 */
export const MAX_OUTBOX_ATTEMPTS = 72;

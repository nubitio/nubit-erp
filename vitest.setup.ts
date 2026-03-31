import { vi } from 'vitest';

// DevExtreme schedules async trial-panel work that touches `document` after
// jsdom teardown in some component tests. Stub the panel module in tests so
// Vitest doesn't report a false-positive unhandled rejection.
vi.mock('devextreme/cjs/__internal/core/license/trial_panel.client', () => ({}));

process.on('unhandledRejection', (reason) => {
  if (
    reason instanceof ReferenceError &&
    reason.message === 'document is not defined' &&
    String(reason.stack ?? '').includes('trial_panel.client.js')
  ) {
    return;
  }

  throw reason;
});

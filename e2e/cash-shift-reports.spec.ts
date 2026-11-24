import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

/**
 * The X cut and the Z report of a shift are read from one place and printed two ways: as the data behind the
 * on-screen ticket, and as ESC/POS commands for the till printer.
 */
test('the report of a shift is served as data and as ESC/POS commands for the printer', async ({
  page,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  const base = getE2EBaseUrl();

  const recent = await page.request.get(`${base}/api/cash-sessions/recent?limit=1`);
  expect(recent.ok(), await recent.text()).toBeTruthy();
  const [latest] = (await recent.json()) as { id: number }[];
  test.skip(latest === undefined, 'the branch has no shift to report on yet');

  const data = await page.request.get(`${base}/api/cash-sessions/${latest.id}/report`);
  expect(data.ok(), await data.text()).toBeTruthy();
  const report = (await data.json()) as {
    shift: { id: number; status: string };
    cash: { expected: string };
    documents: { rows: unknown[] };
  };
  expect(report.shift.id).toBe(latest.id);
  expect(report.cash.expected).toMatch(/^-?\d+\.\d{2}$/);

  const printed = await page.request.get(`${base}/api/cash-sessions/${latest.id}/print-report`);
  expect(printed.ok(), await printed.text()).toBeTruthy();
  expect(printed.headers()['content-type']).toContain('application/octet-stream');
  const bytes = await printed.body();
  // ESC @ (initialize the printer) first, then the title the cashier reads on the paper.
  expect([bytes[0], bytes[1]]).toEqual([0x1b, 0x40]);
  const text = bytes.toString('latin1');
  expect(text).toContain(report.shift.status === 'closed' ? 'REPORTE Z' : 'REPORTE X');
  expect(text).toContain('EFECTIVO');

  const none = await page.request.get(`${base}/api/cash-sessions/999999999/report`);
  expect(none.status()).toBe(404);
});

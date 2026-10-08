import { expect, type Page } from '@playwright/test';
import { parseApiCollection } from './api';
import { getE2EBaseUrl } from '../support/e2eSeed';

/** Capture the branch-scoped debt before a sale: the grid groups by customer. */
export async function customerReceivableBalance(page: Page, customerName: string): Promise<number> {
  const response = await page.request.get(
    `${getE2EBaseUrl()}/api/sales/reports/receivable-customers`,
    {
      headers: { Accept: 'application/ld+json' },
      params: { 'filter[0]': JSON.stringify(['customerName', 'contains', customerName]) },
    },
  );
  expect(response.ok(), await response.text()).toBeTruthy();
  const customers = parseApiCollection<{ customerName: string; totalBalance: string }>(
    await response.json(),
  );
  return Number(
    customers.find((customer) => customer.customerName === customerName)?.totalBalance ?? 0,
  );
}

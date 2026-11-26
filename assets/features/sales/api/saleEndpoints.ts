import { createEndpoints } from '@shared/api/endpoints';

// createEndpoints already resolves the base URL from CoreConfig via getCoreApiBaseUrl().
const sales = createEndpoints('sales');

const branchQuery = (branchId?: string) =>
  branchId ? `?${new URLSearchParams({ branchId })}` : '';

export const saleEndpoints = {
  ...sales,
  monthlySummary: () => `${sales.list()}/reports/monthly-summary`,
  items: (id: string | number) => `${sales.list()}/${id}/items`,
  receivableCustomers: (branchId?: string) =>
    `${sales.list()}/reports/receivable-customers${branchQuery(branchId)}`,
  receivableSummary: (branchId?: string) =>
    `${sales.list()}/reports/receivable-summary${branchQuery(branchId)}`,
};

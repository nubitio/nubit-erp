import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { createCrudEvents } from '@nubitio/crud';

export const expenseReportEvents = createCrudEvents('expense-report');
export const expenseReportApiUrl = `${API}expense-reports`;

export { expenseReportFormLayout } from './formLayout';

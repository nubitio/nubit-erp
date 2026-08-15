import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { createCrudEvents } from '@nubitio/crud';

export const bankAccountEvents = createCrudEvents('bank-account');
export const bankAccountApiUrl = `${API}bank-accounts`;

export const bankStatementImportEvents = createCrudEvents('bank-statement-import');
export const bankStatementImportApiUrl = `${API}bank-statement-imports`;

export const bankStatementLineApiUrl = `${API}bank-statement-lines`;

export const BANK_PROFILE_OPTIONS = [
  { value: 'BCP', label: 'BCP' },
  { value: 'BBVA', label: 'BBVA' },
  { value: 'INTERBANK', label: 'Interbank' },
  { value: 'GENERIC', label: 'Genérico / otro banco' },
];

export { bankAccountFormLayout } from './formLayout';

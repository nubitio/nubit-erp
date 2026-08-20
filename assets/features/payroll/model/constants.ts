import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { createCrudEvents } from '@nubitio/crud';

export const employeeEvents = createCrudEvents('employee');
export const employeeApiUrl = `${API}employees`;

export const payrollRunEvents = createCrudEvents('payroll-run');
export const payrollRunApiUrl = `${API}payroll-runs`;

export { employeeFormLayout } from './employeeFormLayout';
export { payrollRunFormLayout } from './payrollRunFormLayout';

import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

type PayrollRunActionResponse = unknown;

export interface PayrollRunRecord {
  id: string;
  company: unknown;
  period: string;
  type:
    | 'REGULAR'
    | 'GRATIFICATION_JULY'
    | 'GRATIFICATION_DECEMBER'
    | 'CTS_MAY'
    | 'CTS_NOVEMBER'
    | 'LIQUIDACION';
  status: 'DRAFT' | 'CALCULATED' | 'APPROVED' | 'PAID';
  calculatedAt: string | null;
  approvedAt: string | null;
  paidAt: string | null;
}

export interface PayslipEntryRecord {
  id: string;
  employee: { '@id'?: string } | string;
  grossSalary: string;
  afpMandatoryContribution: string;
  afpInsurancePremium: string;
  afpCommission: string;
  onpAmount: string;
  essaludEmployerContribution: string;
  bonificacionExtraordinaria: string;
  quintaCategoriaRetention: string;
  vacationTruncaAmount: string;
  gratificacionTruncaAmount: string;
  ctsTruncaAmount: string;
  netPay: string;
}

export interface EmployeeSummary {
  '@id': string;
  id: string;
  firstName: string;
  lastName: string;
  dni: string;
}

// Requests JSON-LD explicitly: the default `application/json` format
// serializes collections as a flat array and to-one associations as `[]`,
// dropping the hydra `member` envelope and the relation IRI we need to
// match employees back to their payslip entries.
const LD_JSON_HEADERS = { Accept: 'application/ld+json' };

const payrollRunService = () => {
  const get = (id: string) =>
    httpRequestData(httpClient.get<PayrollRunRecord>(`${API}payroll-runs/${id}`));

  const listEntries = (payrollRunId: string) =>
    httpRequestData(
      httpClient.get<{ member: PayslipEntryRecord[] }>(
        `${API}payslip-entries?payrollRun=${payrollRunId}`,
        {
          headers: LD_JSON_HEADERS,
        },
      ),
    ).then((data) => data.member ?? []);

  const listEmployees = () =>
    httpRequestData(
      httpClient.get<{ member: EmployeeSummary[] }>(`${API}employees`, {
        headers: LD_JSON_HEADERS,
      }),
    ).then((data) => data.member ?? []);

  const calculate = (id: string) =>
    httpClient.post<PayrollRunActionResponse>(API + `payroll-runs/${id}/calculate`, { id });

  const approve = (id: string) =>
    httpClient.post<PayrollRunActionResponse>(API + `payroll-runs/${id}/approve`, { id });

  const pay = (id: string, input: { paymentMethodId?: number; moneyAccountId?: string } = {}) =>
    httpClient.post<PayrollRunActionResponse>(API + `payroll-runs/${id}/pay`, {
      id,
      paymentMethodId: input.paymentMethodId,
      moneyAccountId: input.moneyAccountId,
    });

  const plameExportUrl = (id: string) => `${API}v1/payroll/${id}/plame`;

  return { get, listEntries, listEmployees, calculate, approve, pay, plameExportUrl };
};

export default payrollRunService;

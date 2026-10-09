import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

type ExpenseReportActionResponse = unknown;

const expenseReportService = () => {
  const approve = (id: string, note?: string) => {
    return httpClient.post<ExpenseReportActionResponse>(API + `expense-reports/${id}/approve`, {
      id,
      note,
    });
  };

  const reject = (id: string, note?: string) => {
    return httpClient.post<ExpenseReportActionResponse>(API + `expense-reports/${id}/reject`, {
      id,
      note,
    });
  };

  const reimburse = (id: string) => {
    return httpClient.post<ExpenseReportActionResponse>(API + `expense-reports/${id}/reimburse`, {
      id,
    });
  };

  return { approve, reject, reimburse };
};

export default expenseReportService;

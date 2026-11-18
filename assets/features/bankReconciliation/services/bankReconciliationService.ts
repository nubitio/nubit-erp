import { httpClient } from '@shared/api/HttpClient';

export interface UnreconciledMovement {
  id: number | string;
  amount: string;
  date: string | null;
  label: string;
  /** Treasury movements only: the money account the movement is on. */
  account?: string;
}

export interface UnreconciledMovementsResponse {
  supplierPayments: UnreconciledMovement[];
  salePayments: UnreconciledMovement[];
  cashMovements: UnreconciledMovement[];
  /**
   * With Treasury on, one list of the bank account's own movements replaces the three above (they come
   * back empty). `active` false means the old three are the data to show.
   */
  treasury?: { active: boolean; movements: UnreconciledMovement[] };
}

export interface BankStatementLineSuggestion {
  sourceType: 'supplier_payment' | 'sale_payment' | 'cash_movement' | 'treasury_movement';
  sourceId: number | string;
  amount: string;
  date: string;
  label: string;
  confidence: 'EXACT' | 'APPROXIMATE';
}

const bankReconciliationService = () => {
  const match = (lineId: string, sourceType: string, sourceId: number | string) => {
    return httpClient.post(`/api/bank-statement-lines/${lineId}/match`, {
      id: lineId,
      sourceType,
      sourceId,
    });
  };

  const unmatch = (lineId: string) => {
    return httpClient.post(`/api/bank-statement-lines/${lineId}/unmatch`, { id: lineId });
  };

  const ignore = (lineId: string) => {
    return httpClient.post(`/api/bank-statement-lines/${lineId}/ignore`, { id: lineId });
  };

  const suggestions = (lineId: string) => {
    return httpClient.get<BankStatementLineSuggestion[]>(
      `/api/bank-statement-lines/${lineId}/suggestions`,
    );
  };

  const unreconciledMovements = (from?: string, to?: string, bankAccountId?: string | null) => {
    return httpClient.get<UnreconciledMovementsResponse>(
      '/api/v1/bank-reconciliation/unreconciled-movements',
      {
        params: { from, to, bankAccountId: bankAccountId ?? undefined },
      },
    );
  };

  return { match, unmatch, ignore, suggestions, unreconciledMovements };
};

export default bankReconciliationService;

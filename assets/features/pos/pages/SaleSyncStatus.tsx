import React from 'react';
import type { LocalSale } from '@offline/types';

interface SaleSyncStatusProps {
  sale: LocalSale;
  onRetry: (saleId: string) => void;
}

const statusLabelMap: Record<LocalSale['status'], string> = {
  pending: 'pending',
  syncing: 'syncing',
  synced: 'synced',
  error: 'error',
};

export const SaleSyncStatus = ({ sale, onRetry }: SaleSyncStatusProps) => {
  return (
    <div className={`pos-sync-sale pos-sync-sale--${sale.status}`}>
      <div className="pos-sync-sale__row">
        <strong>{sale.serverCode ?? sale.correlativoTemporal}</strong>
        <span>{statusLabelMap[sale.status]}</span>
      </div>
      {sale.errorMessage && <div className="pos-sync-sale__error">{sale.errorMessage}</div>}
      {sale.status === 'error' && (
        <button type="button" className="pos-sync-sale__retry" onClick={() => onRetry(sale.id)}>
          Reintentar
        </button>
      )}
    </div>
  );
};

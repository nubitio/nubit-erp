import React, { useState } from 'react';
import type { LocalSale } from '@offline/types';
import { SaleSyncStatus } from './SaleSyncStatus';

interface PendingSalesIndicatorProps {
  pendingSalesCount: number;
  sales: LocalSale[];
  onRetrySale: (saleId: string) => void;
}

export const PendingSalesIndicator = ({
  pendingSalesCount,
  sales,
  onRetrySale,
}: PendingSalesIndicatorProps) => {
  const [open, setOpen] = useState(false);
  const pendingAndRecent = sales.filter((sale) => sale.status !== 'synced').slice(0, 10);

  return (
    <div className="pos-pending-sales">
      <button
        type="button"
        className="pos-pending-sales__button"
        onClick={() => setOpen((value) => !value)}
      >
        Ventas pendientes: {pendingSalesCount} · Ver detalle
      </button>
      {open && (
        <div className="pos-pending-sales__panel">
          {pendingAndRecent.length === 0 ? (
            <div className="pos-pending-sales__empty">Sin ventas pendientes.</div>
          ) : (
            pendingAndRecent.map((sale) => (
              <SaleSyncStatus key={sale.id} sale={sale} onRetry={onRetrySale} />
            ))
          )}
        </div>
      )}
    </div>
  );
};

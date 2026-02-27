import React from 'react';

interface ConnectivityBadgeProps {
  isOnline: boolean;
  isSyncing: boolean;
  pendingSalesCount: number;
}

export const ConnectivityBadge = ({
  isOnline,
  isSyncing,
  pendingSalesCount,
}: ConnectivityBadgeProps) => {
  if (isSyncing) {
    return (
      <div className="pos-net-badge is-syncing" aria-live="polite">
        <span>🟡</span>
        <span>Sincronizando ({pendingSalesCount} pendientes)</span>
      </div>
    );
  }

  if (isOnline) {
    return (
      <div className="pos-net-badge is-online" aria-live="polite">
        <span>🟢</span>
        <span>Conectado</span>
      </div>
    );
  }

  return (
    <div className="pos-net-badge is-offline" aria-live="polite">
      <span>🔴</span>
      <span>Sin conexión (modo offline)</span>
    </div>
  );
};

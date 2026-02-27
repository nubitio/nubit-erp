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
      <div
        className="pos-net-dot is-syncing"
        aria-live="polite"
        title={`Sincronizando — ${pendingSalesCount} venta${pendingSalesCount !== 1 ? 's' : ''} pendiente${pendingSalesCount !== 1 ? 's' : ''}`}
        aria-label={`Sincronizando, ${pendingSalesCount} pendientes`}
      />
    );
  }

  if (isOnline) {
    return (
      <div
        className="pos-net-dot is-online"
        aria-live="polite"
        title="Conectado"
        aria-label="Conectado"
      />
    );
  }

  return (
    <div
      className="pos-net-dot is-offline"
      aria-live="polite"
      title="Sin conexión — modo offline"
      aria-label="Sin conexión"
    />
  );
};

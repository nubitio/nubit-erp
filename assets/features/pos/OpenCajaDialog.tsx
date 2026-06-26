import React, { useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import toast from '@shared/lib/SharedUtils';

interface Props {
  cashRegisterId: number;
  onSuccess: (openedAt: string) => void;
  onCancel: () => void;
}

export function OpenCajaDialog({ cashRegisterId, onSuccess, onCancel }: Props) {
  const [openingBalance, setOpeningBalance] = useState('0.00');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const amount = parseFloat(openingBalance) || 0;
    setLoading(true);
    try {
      const response = await httpClient.post<{ openedAt?: string; opened_at?: string }>(
        getCoreApiBaseUrl() + `cash-registers/${cashRegisterId}/open`,
        { id: cashRegisterId, openingBalance: parseFloat(amount.toFixed(2)) },
      );
      const data = response.data as Record<string, unknown>;
      const openedAt =
        (data.openedAt as string | undefined) ??
        (data.opened_at as string | undefined) ??
        new Date().toISOString();
      toast('Caja abierta correctamente', 'success');
      onSuccess(openedAt);
    } catch {
      toast('No se pudo abrir la caja. Intenta nuevamente.', 'error');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 1000,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'rgba(0,0,0,0.4)',
      }}
      onClick={(e) => {
        if (e.target === e.currentTarget) onCancel();
      }}
    >
      <div
        style={{
          background: 'var(--nb-surface, #fff)',
          borderRadius: '12px',
          padding: '24px',
          width: '320px',
          boxShadow: '0 8px 32px rgba(0,0,0,0.18)',
        }}
      >
        <h3 style={{ margin: '0 0 16px', fontSize: '1.1rem', fontWeight: 600 }}>
          Abrir caja
        </h3>
        <form onSubmit={(e) => void handleSubmit(e)}>
          <label
            style={{
              display: 'block',
              fontSize: '0.875rem',
              fontWeight: 500,
              marginBottom: '6px',
              color: 'var(--nb-text-secondary, #666)',
            }}
          >
            Saldo inicial (S/)
          </label>
          <input
            type="number"
            min="0"
            step="0.01"
            value={openingBalance}
            onChange={(e) => setOpeningBalance(e.target.value)}
            onFocus={(e) => e.target.select()}
            autoFocus
            style={{
              width: '100%',
              padding: '10px 12px',
              fontSize: '1.25rem',
              border: '1.5px solid var(--nb-border, #e0e0e0)',
              borderRadius: '8px',
              marginBottom: '20px',
              boxSizing: 'border-box',
              outline: 'none',
            }}
          />
          <div style={{ display: 'flex', gap: '8px' }}>
            <button
              type="button"
              onClick={onCancel}
              disabled={loading}
              style={{
                flex: 1,
                padding: '10px',
                borderRadius: '8px',
                border: '1.5px solid var(--nb-border, #e0e0e0)',
                background: 'transparent',
                cursor: 'pointer',
                fontWeight: 500,
              }}
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={loading}
              style={{
                flex: 1,
                padding: '10px',
                borderRadius: '8px',
                border: 'none',
                background: 'var(--nb-primary, #2563eb)',
                color: '#fff',
                cursor: 'pointer',
                fontWeight: 600,
                opacity: loading ? 0.7 : 1,
              }}
            >
              {loading ? 'Abriendo…' : 'Abrir caja'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

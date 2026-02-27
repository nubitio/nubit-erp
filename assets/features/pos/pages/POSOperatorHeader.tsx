import React from 'react';
import { ArrowLeft, CheckCircle2, Package, Store, User, Zap } from 'lucide-react';
import { ThemeSwitcher } from '../../../components/library/theme-switcher/ThemeSwitcher';
import type { WarehouseOption } from './types';
import type { LocalSale } from '@offline/types';
import { ConnectivityBadge } from './ConnectivityBadge';
import { PendingSalesIndicator } from './PendingSalesIndicator';

interface POSOperatorHeaderProps {
  branchName: string;
  userName: string;
  warehouses: WarehouseOption[];
  selectedWarehouseId: string;
  setSelectedWarehouseId: (id: string) => void;
  cajaAbierta: boolean;
  setCajaAbierta: (v: boolean) => void;
  quickMode: boolean;
  setQuickMode: (v: boolean) => void;
  cartItemsCount: number;
  hasCartItems: boolean;
  lastSaleCode: string | null;
  isOnline: boolean;
  isSyncing: boolean;
  pendingSalesCount: number;
  outboxSales: LocalSale[];
  onRetrySale: (saleId: string) => void;
}

export function POSOperatorHeader({
  branchName,
  userName,
  warehouses,
  selectedWarehouseId,
  setSelectedWarehouseId,
  cajaAbierta,
  setCajaAbierta,
  quickMode,
  setQuickMode,
  cartItemsCount,
  hasCartItems,
  lastSaleCode,
  isOnline,
  isSyncing,
  pendingSalesCount,
  outboxSales,
  onRetrySale,
}: POSOperatorHeaderProps) {
  function handleWarehouseChange(newId: string) {
    if (hasCartItems && newId !== selectedWarehouseId) {
      if (!window.confirm('Cambiar de almacén vaciará el carrito. ¿Continuar?')) return;
    }
    setSelectedWarehouseId(newId);
  }

  return (
    <header className="pos-operator-header">
      {/* ── Left cluster: navigation + operational context ── */}
      <div className="pos-operator-header__left">
        <button
          type="button"
          className="pos-operator-header__back"
          onClick={() => window.history.back()}
          aria-label="Salir del POS"
        >
          <ArrowLeft size={14} />
        </button>

        <div className="pos-operator-header__sep" />

        <div className="pos-operator-header__ctx">
          <Store size={11} />
          <span className="pos-operator-header__label">Sucursal</span>
          <strong>{branchName || 'Principal'}</strong>
        </div>

        <div className="pos-operator-header__sep" />

        {/* Caja status pill */}
        <div className={`pos-caja-badge ${cajaAbierta ? 'is-open' : 'is-closed'}`}>
          <span className="pos-caja-badge__dot" />
          <span>Caja {cajaAbierta ? 'Abierta' : 'Cerrada'}</span>
        </div>

        {warehouses.length > 1 && (
          <>
            <div className="pos-operator-header__sep" />
            <div className="pos-operator-header__ctx">
              <Package size={11} />
              <span className="pos-operator-header__label">Almacén</span>
              <select
                className="pos-operator-header__select"
                value={selectedWarehouseId}
                onChange={(e) => handleWarehouseChange(e.target.value)}
                aria-label="Almacén activo"
              >
                {warehouses.map((w) => (
                  <option key={w.id} value={w.id}>
                    {w.name}
                  </option>
                ))}
              </select>
            </div>
          </>
        )}

        <div className="pos-operator-header__sep" />

        <div className="pos-operator-header__ctx">
          <User size={11} />
          <span className="pos-operator-header__label">Cajero</span>
          <strong>{userName || '—'}</strong>
        </div>
      </div>

      {/* ── Right cluster: status + controls ── */}
      <div className="pos-operator-header__right">
        {lastSaleCode && (
          <div
            className="pos-operator-header__last-sale"
            title={`Última venta: ${lastSaleCode}`}
            aria-live="polite"
          >
            <CheckCircle2 size={11} />
            <span>{lastSaleCode}</span>
          </div>
        )}

        <ConnectivityBadge
          isOnline={isOnline}
          isSyncing={isSyncing}
          pendingSalesCount={pendingSalesCount}
        />

        <PendingSalesIndicator
          pendingSalesCount={pendingSalesCount}
          sales={outboxSales}
          onRetrySale={onRetrySale}
        />

        {/* Quick mode toggle */}
        <button
          type="button"
          className={`pos-mode-btn ${quickMode ? 'is-active' : ''}`}
          onClick={() => setQuickMode(!quickMode)}
          title="Modo caja rápida — oculta cliente y nota"
          aria-pressed={quickMode}
        >
          <Zap size={12} />
          <span>Rápido</span>
        </button>

        {/* Caja opening/closing */}
        {cajaAbierta ? (
          <button
            type="button"
            className="pos-caja-action-btn is-close"
            onClick={() => {
              if (cartItemsCount > 0) {
                if (!window.confirm('Hay productos en el carrito. ¿Cerrar caja igualmente?')) return;
              }
              setCajaAbierta(false);
            }}
            title="Cerrar caja"
          >
            Cerrar caja
          </button>
        ) : (
          <button
            type="button"
            className="pos-caja-action-btn is-open"
            onClick={() => setCajaAbierta(true)}
          >
            Abrir caja
          </button>
        )}

        <div className="pos-operator-header__sep" />
        <ThemeSwitcher />
      </div>
    </header>
  );
}

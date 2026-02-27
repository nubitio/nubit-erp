import React, { useEffect, useState } from 'react';
import { ArrowLeft, HelpCircle, Maximize2, Minimize2, Package, Store, User } from 'lucide-react';
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
  cajaOpenedAt: string | null;
  cartItemsCount: number;
  hasCartItems: boolean;
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
  cajaOpenedAt,
  cartItemsCount,
  hasCartItems,
  isOnline,
  isSyncing,
  pendingSalesCount,
  outboxSales,
  onRetrySale,
}: POSOperatorHeaderProps) {
  const [showShortcuts, setShowShortcuts] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(!!document.fullscreenElement);

  useEffect(() => {
    const handler = () => setIsFullscreen(!!document.fullscreenElement);
    document.addEventListener('fullscreenchange', handler);
    return () => document.removeEventListener('fullscreenchange', handler);
  }, []);

  function toggleFullscreen() {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(() => {});
    } else {
      document.exitFullscreen().catch(() => {});
    }
  }

  function handleWarehouseChange(newId: string) {
    if (hasCartItems && newId !== selectedWarehouseId) {
      if (!window.confirm('Cambiar de almacén vaciará el carrito. ¿Continuar?')) return;
    }
    setSelectedWarehouseId(newId);
  }

  return (
    <header className="pos-operator-header">
      {/* ── Left: context data ── */}
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
          <span>{cajaAbierta ? 'Abierta' : 'Cerrada'}</span>
          {cajaAbierta && cajaOpenedAt && (
            <span className="pos-caja-badge__time">
              {new Date(cajaOpenedAt).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })}
            </span>
          )}
        </div>

        {warehouses.length > 0 && (
          <>
            <div className="pos-operator-header__sep" />
            <div className="pos-operator-header__ctx">
              <Package size={11} />
              <span className="pos-operator-header__label">Almacén</span>
              {warehouses.length === 1 ? (
                <strong>{warehouses[0].name}</strong>
              ) : (
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
              )}
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

      {/* ── Right: compact status + actions ── */}
      <div className="pos-operator-header__right">
        {/* Connectivity: icon-only dot, tooltip on hover */}
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

        <div className="pos-operator-header__sep" />

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

        <button
          type="button"
          className="pos-shortcuts-btn"
          onClick={toggleFullscreen}
          aria-label={isFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'}
          title={isFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'}
        >
          {isFullscreen ? <Minimize2 size={14} /> : <Maximize2 size={14} />}
        </button>

        <button
          type="button"
          className="pos-shortcuts-btn"
          onClick={(e) => { e.stopPropagation(); setShowShortcuts(true); }}
          aria-label="Atajos de teclado"
          title="Atajos de teclado"
        >
          <HelpCircle size={14} />
        </button>
      </div>

      {/* ── Shortcuts modal ── */}
      {showShortcuts && (
        <div
          className="pos-shortcuts-backdrop"
          role="dialog"
          aria-modal="true"
          aria-label="Atajos de teclado"
          onClick={(e) => { if (e.target === e.currentTarget) setShowShortcuts(false); }}
        >
          <div className="pos-shortcuts-modal">
            <div className="pos-shortcuts-modal__header">
              <h3>Atajos de teclado</h3>
              <button
                type="button"
                className="pos-shortcuts-modal__close"
                onClick={() => setShowShortcuts(false)}
                aria-label="Cerrar"
              >
                ×
              </button>
            </div>
            <ul className="pos-shortcuts-list">
              {([
                ['F2', 'Enfocar buscador de productos'],
                ['F3', 'Seleccionar cliente'],
                ['Enter', 'Cobrar (cuando el carrito tiene items)'],
                ['Esc', 'Cancelar venta y vaciar carrito'],
                ['Scanner → Enter', 'Añadir producto por código de barras'],
              ] as [string, string][]).map(([key, desc]) => (
                <li key={key} className="pos-shortcuts-list__item">
                  <kbd className="pos-shortcuts-list__key">{key}</kbd>
                  <span>{desc}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}
    </header>
  );
}

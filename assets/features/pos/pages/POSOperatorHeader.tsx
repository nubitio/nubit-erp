import React, { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ThemeSwitcher } from '@app/shell/components';
import type { WarehouseOption } from './types';
import type { LocalSale } from '@offline/types';
import { ConnectivityBadge } from './ConnectivityBadge';
import { PendingSalesIndicator } from './PendingSalesIndicator';
import { ConfirmDialog } from '@shared/components';

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
  const navigate = useNavigate();
  const [showShortcuts, setShowShortcuts] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(!!document.fullscreenElement);

  // ── Confirm dialogs state ──────────────────────────────────────────────────
  const [warehouseConfirmOpen, setWarehouseConfirmOpen] = useState(false);
  const pendingWarehouseIdRef = useRef<string | null>(null);
  const [closeCajaConfirmOpen, setCloseCajaConfirmOpen] = useState(false);

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
      pendingWarehouseIdRef.current = newId;
      setWarehouseConfirmOpen(true);
      return;
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
          onClick={() => navigate(-1)}
          aria-label="Salir del POS"
        >
          <i className="ph ph-arrow-left" aria-hidden="true" />
        </button>

        <div className="pos-operator-header__sep" />

        <div className="pos-operator-header__ctx">
          <i className="ph ph-storefront" aria-hidden="true" />
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
              {new Date(cajaOpenedAt).toLocaleTimeString('es', {
                hour: '2-digit',
                minute: '2-digit',
              })}
            </span>
          )}
        </div>

        {warehouses.length > 0 && (
          <>
            <div className="pos-operator-header__sep" />
            <div className="pos-operator-header__ctx">
              <i className="ph ph-package" aria-hidden="true" />
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
          <i className="ph ph-user" aria-hidden="true" />
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
                setCloseCajaConfirmOpen(true);
                return;
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
          {isFullscreen ? (
            <i className="ph ph-arrows-in" aria-hidden="true" />
          ) : (
            <i className="ph ph-arrows-out" aria-hidden="true" />
          )}
        </button>

        <button
          type="button"
          className="pos-shortcuts-btn"
          onClick={(e) => {
            e.stopPropagation();
            setShowShortcuts(true);
          }}
          aria-label="Atajos de teclado"
          title="Atajos de teclado"
        >
          <i className="ph ph-question" aria-hidden="true" />
        </button>
      </div>

      {/* ── Shortcuts modal ── */}
      {showShortcuts && (
        <div
          className="pos-shortcuts-backdrop"
          role="dialog"
          aria-modal="true"
          aria-label="Atajos de teclado"
          onClick={(e) => {
            if (e.target === e.currentTarget) setShowShortcuts(false);
          }}
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
              {(
                [
                  ['F2', 'Enfocar buscador de productos'],
                  ['F3', 'Seleccionar cliente'],
                  ['Enter', 'Cobrar (cuando el carrito tiene items)'],
                  ['Esc', 'Cancelar venta y vaciar carrito'],
                  ['Scanner → Enter', 'Añadir producto por código de barras'],
                ] as [string, string][]
              ).map(([key, desc]) => (
                <li key={key} className="pos-shortcuts-list__item">
                  <kbd className="pos-shortcuts-list__key">{key}</kbd>
                  <span>{desc}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}

      {/* ── Warehouse change confirmation ── */}
      <ConfirmDialog
        open={warehouseConfirmOpen}
        title="Cambiar almacén"
        message="Cambiar de almacén vaciará el carrito. ¿Continuar?"
        confirmLabel="Continuar"
        cancelLabel="Cancelar"
        variant="warning"
        onConfirm={() => {
          setWarehouseConfirmOpen(false);
          if (pendingWarehouseIdRef.current !== null) {
            setSelectedWarehouseId(pendingWarehouseIdRef.current);
            pendingWarehouseIdRef.current = null;
          }
        }}
        onCancel={() => {
          setWarehouseConfirmOpen(false);
          pendingWarehouseIdRef.current = null;
        }}
      />

      {/* ── Close caja confirmation ── */}
      <ConfirmDialog
        open={closeCajaConfirmOpen}
        title="Cerrar caja"
        message="Hay productos en el carrito. ¿Cerrar caja igualmente?"
        confirmLabel="Cerrar caja"
        cancelLabel="Cancelar"
        variant="danger"
        onConfirm={() => {
          setCloseCajaConfirmOpen(false);
          setCajaAbierta(false);
        }}
        onCancel={() => setCloseCajaConfirmOpen(false)}
      />
    </header>
  );
}

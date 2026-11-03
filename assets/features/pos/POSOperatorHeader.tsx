import React, { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ThemeSwitcher } from '@nubitio/ui';
import type { WarehouseOption } from './types';
import type { LocalSale } from '@offline/types';
import { ConnectivityBadge } from './ConnectivityBadge';
import { PendingSalesIndicator } from './PendingSalesIndicator';
import { AppDialog, AppDropdown, ConfirmDialog } from '@nubitio/ui';
import { HardwareAgentDialog } from './HardwareAgentDialog';

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
  showWarehouseSelector: boolean;
  onOpenCaja?: () => void;
  assistantEnabled?: boolean;
  onAssistantClick?: () => void;
  heldSalesCount?: number;
  onOpenHeldSales?: () => void;
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
  showWarehouseSelector,
  onOpenCaja,
  assistantEnabled = false,
  onAssistantClick,
  heldSalesCount = 0,
  onOpenHeldSales,
}: POSOperatorHeaderProps) {
  const navigate = useNavigate();
  const [showShortcuts, setShowShortcuts] = useState(false);
  const [showHardware, setShowHardware] = useState(false);
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
              {new Date(cajaOpenedAt).toLocaleTimeString('es-PE', {
                hour: '2-digit',
                minute: '2-digit',
                timeZone: 'America/Lima',
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
              {!showWarehouseSelector || warehouses.length === 1 ? (
                <strong>
                  {warehouses.find((warehouse) => warehouse.id === selectedWarehouseId)?.name ??
                    warehouses[0].name}
                </strong>
              ) : (
                <AppDropdown
                  id="pos-warehouse-select"
                  value={selectedWarehouseId}
                  options={warehouses.map((warehouse) => ({
                    value: warehouse.id,
                    label: warehouse.name,
                  }))}
                  onChange={handleWarehouseChange}
                  variant="compact"
                  showFieldLabel={false}
                  className="pos-operator-header__warehouse-dropdown"
                  placeholder="Almacén"
                />
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

        {onOpenHeldSales && (
          <button
            type="button"
            className="pos-caja-action-btn"
            onClick={onOpenHeldSales}
            data-testid="pos-held-sales-btn"
          >
            <i className="ph ph-pause-circle" aria-hidden="true" /> Ventas en espera (
            {heldSalesCount})
          </button>
        )}

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
            onClick={() => (onOpenCaja ? onOpenCaja() : setCajaAbierta(true))}
          >
            Abrir caja
          </button>
        )}

        {assistantEnabled && onAssistantClick ? (
          <>
            <div className="pos-operator-header__sep" />
            <button
              type="button"
              className="pos-shortcuts-btn"
              onClick={onAssistantClick}
              aria-label="Asistente de IA"
              title="Asistente de IA (Ctrl+K)"
            >
              <i className="ph ph-sparkle" aria-hidden="true" />
            </button>
          </>
        ) : null}

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
          onClick={() => setShowHardware(true)}
          aria-label="Hardware local"
          title="Hardware local (impresora y balanza)"
        >
          <i className="ph ph-plugs" aria-hidden="true" />
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

      <HardwareAgentDialog open={showHardware} onClose={() => setShowHardware(false)} />

      {/* ── Shortcuts modal ── */}
      <AppDialog
        open={showShortcuts}
        title="Atajos de teclado"
        onClose={() => setShowShortcuts(false)}
      >
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
      </AppDialog>

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

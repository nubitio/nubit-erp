import React, { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  AppDropdown,
  Badge,
  Button,
  ConfirmDialog,
  ContextMenu,
  DescriptionList,
  Row,
  Stack,
  useTheme,
  type ContextMenuItem,
} from '@nubitio/ui';
import type { WarehouseOption } from './types';
import type { LocalSale } from '@offline/types';
import { ConnectivityBadge } from './ConnectivityBadge';
import { PendingSalesIndicator } from './PendingSalesIndicator';
import { HardwareAgentDialog } from './HardwareAgentDialog';

interface POSOperatorHeaderProps {
  /**
   * `bar` is the slim desktop strip: only what the cashier needs during a sale, with the rest in a
   * menu. `panel` is a vertical list of every control, for the options dialog on small screens.
   */
  variant?: 'bar' | 'panel';
  branchName: string;
  userName: string;
  warehouses: WarehouseOption[];
  selectedWarehouseId: string;
  setSelectedWarehouseId: (id: string) => void;
  cajaAbierta: boolean;
  /** The server has not answered yet, so "open" or "closed" would be a guess. */
  cajaChecking?: boolean;
  /** Name of the register the cashier has open, shown in the status pill. */
  cajaName?: string | null;
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
  /** Starts the count and closing of the cashier's session. */
  onCloseCaja?: () => void;
  /** Opens the partial cut (Reporte X) and the cash in/out of the shift. */
  onCashMovements?: () => void;
  assistantEnabled?: boolean;
  onAssistantClick?: () => void;
  heldSalesCount?: number;
  onOpenHeldSales?: () => void;
  onShowShortcuts: () => void;
}

export function POSOperatorHeader({
  variant = 'bar',
  branchName,
  userName,
  warehouses,
  selectedWarehouseId,
  setSelectedWarehouseId,
  cajaAbierta,
  cajaChecking = false,
  cajaName = null,
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
  onCloseCaja,
  onCashMovements,
  assistantEnabled = false,
  onAssistantClick,
  heldSalesCount = 0,
  onOpenHeldSales,
  onShowShortcuts,
}: POSOperatorHeaderProps) {
  const navigate = useNavigate();
  const [showHardware, setShowHardware] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(!!document.fullscreenElement);

  // ── Confirm dialogs state ──────────────────────────────────────────────────
  const [warehouseConfirmOpen, setWarehouseConfirmOpen] = useState(false);
  const pendingWarehouseIdRef = useRef<string | null>(null);
  const [closeCajaConfirmOpen, setCloseCajaConfirmOpen] = useState(false);
  const { mode: themeMode, switchTheme } = useTheme();

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

  function requestCloseCaja() {
    if (cartItemsCount > 0) {
      setCloseCajaConfirmOpen(true);
      return;
    }
    onCloseCaja?.();
  }

  function handleWarehouseChange(newId: string) {
    if (hasCartItems && newId !== selectedWarehouseId) {
      pendingWarehouseIdRef.current = newId;
      setWarehouseConfirmOpen(true);
      return;
    }
    setSelectedWarehouseId(newId);
  }

  const cajaTime =
    cajaAbierta && cajaOpenedAt
      ? new Date(cajaOpenedAt).toLocaleTimeString('es-PE', {
          hour: '2-digit',
          minute: '2-digit',
          timeZone: 'America/Lima',
        })
      : null;

  const warehouseName =
    warehouses.find((warehouse) => warehouse.id === selectedWarehouseId)?.name ??
    warehouses[0]?.name;

  const warehouseSelectable = showWarehouseSelector && warehouses.length > 1;

  // Secondary actions: a menu on the bar, a list of buttons in the options panel.
  const menuItems: ContextMenuItem[] = [
    ...(cajaAbierta
      ? [
          ...(onCashMovements
            ? [
                {
                  label: 'Movimientos y reporte X',
                  icon: 'ph ph-arrows-left-right',
                  onClick: onCashMovements,
                },
              ]
            : []),
          ...(onCloseCaja
            ? [
                {
                  label: 'Cerrar caja',
                  icon: 'ph ph-lock-key',
                  danger: true,
                  onClick: requestCloseCaja,
                },
              ]
            : []),
        ]
      : []),
    {
      label: isFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa',
      icon: isFullscreen ? 'ph ph-arrows-in' : 'ph ph-arrows-out',
      onClick: toggleFullscreen,
      separator: cajaAbierta && Boolean(onCashMovements || onCloseCaja),
    },
    {
      label:
        themeMode === 'dark'
          ? 'Tema: oscuro'
          : themeMode === 'light'
            ? 'Tema: claro'
            : 'Tema: sistema',
      icon:
        themeMode === 'dark' ? 'ph ph-moon' : themeMode === 'light' ? 'ph ph-sun' : 'ph ph-desktop',
      onClick: switchTheme,
    },
    {
      label: 'Hardware local',
      icon: 'ph ph-plugs',
      onClick: () => setShowHardware(true),
    },
    {
      label: 'Atajos de teclado',
      icon: 'ph ph-question',
      onClick: onShowShortcuts,
    },
  ];

  const dialogs = (
    <>
      <HardwareAgentDialog open={showHardware} onClose={() => setShowHardware(false)} />

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
        message="Hay productos en el carrito. Se conservan para el próximo turno. ¿Cerrar caja igualmente?"
        confirmLabel="Cerrar caja"
        cancelLabel="Cancelar"
        variant="warning"
        onConfirm={() => {
          setCloseCajaConfirmOpen(false);
          onCloseCaja?.();
        }}
        onCancel={() => setCloseCajaConfirmOpen(false)}
      />
    </>
  );

  if (variant === 'panel') {
    const connectionLabel = isSyncing ? 'Sincronizando' : isOnline ? 'Conectado' : 'Sin conexión';
    return (
      <Stack gap={4} className="pos-options">
        <DescriptionList
          items={[
            { term: 'Sucursal', value: branchName || 'Principal' },
            ...(warehouses.length > 0
              ? [
                  {
                    term: 'Almacén',
                    value: warehouseSelectable ? (
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
                        ariaLabel="Almacén"
                        placeholder="Almacén"
                      />
                    ) : (
                      warehouseName
                    ),
                  },
                ]
              : []),
            { term: 'Cajero', value: userName || '—' },
          ]}
        />

        <Row gap={2} wrap>
          <Badge variant={cajaChecking ? 'warning' : cajaAbierta ? 'success' : 'danger'}>
            {cajaChecking ? 'Verificando caja' : cajaAbierta ? 'Caja abierta' : 'Caja cerrada'}
            {cajaAbierta && cajaName ? ` · ${cajaName}` : ''}
            {cajaTime ? ` · ${cajaTime}` : ''}
          </Badge>
          <Badge variant={isSyncing ? 'warning' : isOnline ? 'success' : 'danger'} outlined>
            {connectionLabel}
          </Badge>
        </Row>

        {onOpenHeldSales && (
          <Button
            variant="secondary"
            fullWidth
            icon="ph ph-pause-circle"
            onClick={onOpenHeldSales}
            data-testid="pos-held-sales-btn"
          >
            Ventas en espera{heldSalesCount > 0 ? ` (${heldSalesCount})` : ''}
          </Button>
        )}

        {pendingSalesCount > 0 && (
          <PendingSalesIndicator
            pendingSalesCount={pendingSalesCount}
            sales={outboxSales}
            onRetrySale={onRetrySale}
          />
        )}

        {!cajaAbierta && !cajaChecking && (
          <Button variant="primary" fullWidth onClick={onOpenCaja}>
            Abrir caja
          </Button>
        )}

        {assistantEnabled && onAssistantClick && (
          <Button variant="secondary" fullWidth icon="ph ph-sparkle" onClick={onAssistantClick}>
            Asistente de IA
          </Button>
        )}

        {menuItems.map((item) => (
          <Button
            key={item.label}
            variant={item.danger ? 'danger' : 'secondary'}
            fullWidth
            icon={item.icon}
            onClick={item.onClick}
          >
            {item.label}
          </Button>
        ))}

        {dialogs}
      </Stack>
    );
  }

  return (
    <header className="pos-operator-header pos-operator-header--bar">
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

        <div className="pos-operator-header__ctx pos-operator-header__ctx--branch">
          <i className="ph ph-storefront" aria-hidden="true" />
          <span className="pos-operator-header__label">Sucursal</span>
          <strong>{branchName || 'Principal'}</strong>
        </div>

        <div className="pos-operator-header__sep" />

        {/* Caja status pill */}
        <div
          className={`pos-caja-badge ${cajaChecking ? 'is-checking' : cajaAbierta ? 'is-open' : 'is-closed'}`}
          data-testid="pos-caja-badge"
        >
          <span className="pos-caja-badge__dot" />
          <span>
            {cajaChecking
              ? 'Verificando'
              : cajaAbierta
                ? `Abierta${cajaName ? ` · ${cajaName}` : ''}`
                : 'Cerrada'}
          </span>
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

        <div className="pos-operator-header__ctx pos-operator-header__ctx--cashier">
          <i className="ph ph-user" aria-hidden="true" />
          <span className="pos-operator-header__label">Cajero</span>
          <strong>{userName || '—'}</strong>
        </div>
      </div>

      {/* ── Right: status + actions ── */}
      <div className="pos-operator-header__right">
        <ConnectivityBadge
          isOnline={isOnline}
          isSyncing={isSyncing}
          pendingSalesCount={pendingSalesCount}
        />

        {/* Only worth the space when something is waiting or failed (the count includes errors). */}
        {pendingSalesCount > 0 && (
          <PendingSalesIndicator
            pendingSalesCount={pendingSalesCount}
            sales={outboxSales}
            onRetrySale={onRetrySale}
          />
        )}

        {onOpenHeldSales && (
          <button
            type="button"
            className="pos-caja-action-btn is-neutral"
            onClick={onOpenHeldSales}
            data-testid="pos-held-sales-btn"
          >
            <i className="ph ph-pause-circle" aria-hidden="true" /> Ventas en espera
            {heldSalesCount > 0 ? ` (${heldSalesCount})` : ''}
          </button>
        )}

        {!cajaAbierta && !cajaChecking && (
          <button type="button" className="pos-caja-action-btn is-open" onClick={onOpenCaja}>
            Abrir caja
          </button>
        )}

        {assistantEnabled && onAssistantClick ? (
          <button
            type="button"
            className="pos-shortcuts-btn"
            onClick={onAssistantClick}
            aria-label="Asistente de IA"
            title="Asistente de IA (Ctrl+K)"
          >
            <i className="ph ph-sparkle" aria-hidden="true" />
          </button>
        ) : null}

        <ContextMenu triggerLabel="Más opciones" items={menuItems} />
      </div>

      {dialogs}
    </header>
  );
}

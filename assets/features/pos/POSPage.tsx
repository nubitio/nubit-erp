/* eslint-disable react-hooks/set-state-in-effect -- POS initialization synchronizes branch and cash-session state */
import React, { lazy, Suspense, useContext, useEffect, useMemo, useRef, useState } from 'react';
import './POSPage.scss';
import './POSDesign.scss';
import './HardwareAgentDialog.scss';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { useSessionInfo } from '@shared/sessionInfo';
import { createInvoicePrinter, InvoicePrinter } from '@features/sales';
import { ThemeContext } from '@assets/theme/theme';
import { POSOperatorHeader } from './POSOperatorHeader';
import { POSShortcutsDialog } from './POSShortcutsDialog';
import { POSCatalogPanel } from './POSCatalogPanel';
import { POSCheckoutPanel } from './POSCheckoutPanel';
import { productService } from '@features/product/services/productService';
import { usePosCustomers } from './usePosCustomers';
import { usePosCheckout } from './usePosCheckout';
import { usePosCatalogData } from './usePosCatalogData';
import { usePosPriceSync } from './usePosPriceSync';
import { refreshOfflineCredential } from '@offline/offlineCredential';
import { parsePriceListOverrides } from './lib/parsePriceListOverrides';
import { normalizeText, toNumber } from './posHelpers';
import { useConnectivity } from '@offline/hooks/useConnectivity';
import { useOutboxSync } from '@offline/hooks/useOutboxSync';
import { AppDialog, Button, ConfirmDialog } from '@nubitio/ui';
import {
  buildHeldSale,
  formatTicketNumber,
  nextTicketNumber,
  readHeldSales,
  writeHeldSales,
  type HeldSale,
} from './lib/heldSales';
import { useFeature, useTenantRuntimeConfig } from '@shared/hooks';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useCashSession } from '@features/cashRegister/hooks/useCashSession';
import { CashOpenDialog } from '@features/cashRegister/components/CashOpenDialog';
import { hasPermission } from '@shared/permissions';
import { useSettingsDialog } from '@app/profile';

const Assistant = lazy(() => import('@features/assistant/Assistant'));
// Counting the drawer and the cut are used once a shift: they are not worth the first paint.
const CashCloseDialog = lazy(() =>
  import('@features/cashRegister/components/CashCloseDialog').then((module) => ({
    default: module.CashCloseDialog,
  })),
);
const CashMovementsDialog = lazy(() =>
  import('@features/cashRegister/components/CashMovementsDialog').then((module) => ({
    default: module.CashMovementsDialog,
  })),
);

interface POSPageProps {
  /** Ends the session. Injected by the app, which owns authentication. */
  onSignOut?: () => void;
  /** Where the header's back button goes; null when the user has nowhere else to go. */
  exitTo?: string | null;
}

const POSPage = ({ onSignOut, exitTo = null }: POSPageProps) => {
  const themeContext = useContext(ThemeContext);
  const resolvedTheme = themeContext?.theme ?? 'light';

  const { currentBranchId, currentBranchName, currentUserName } = useSessionInfo();
  const branchId = String(currentBranchId ?? '');
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const allowLinePriceOverride =
    (runtimeConfig?.ui.pos?.allowLinePriceOverride ?? true) && hasPermission('pos.price_override');
  const allowCatalogPriceEdit =
    (runtimeConfig?.ui.pos?.allowCatalogPriceEdit ?? true) && hasPermission('catalog.edit');
  const allowDiscount = hasPermission('pos.discount');
  const assistantEnabled = useFeature('ai_assistant');
  const [assistantOpen, setAssistantOpen] = useState(false);

  const [activeCategory, setActiveCategory] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const stockPolicy = runtimeConfig?.settings.negativeStockPolicy ?? 'STRICT';
  const enforceStockCheck = stockPolicy === 'STRICT';
  // The cash session is the server's to decide: sales are tied to the one this user opened.
  const cashSession = useCashSession();
  const { open: openSettings } = useSettingsDialog();
  const cajaAbierta = cashSession.session !== null;
  const cajaOpenedAt = cashSession.session?.openedAt ?? null;
  const [openCajaDialogOpen, setOpenCajaDialogOpen] = useState(false);
  const [closeCajaDialogOpen, setCloseCajaDialogOpen] = useState(false);
  const [cashMovementsOpen, setCashMovementsOpen] = useState(false);
  const [quickMode, setQuickMode] = useState(false);
  const [focusCustomerTrigger, setFocusCustomerTrigger] = useState(0);
  // Counters the checkout panel reacts to: it owns the dialog / the line being edited.
  const [newCustomerTrigger, setNewCustomerTrigger] = useState(0);
  const [editLastQtyTrigger, setEditLastQtyTrigger] = useState(0);
  const [shortcutsOpen, setShortcutsOpen] = useState(false);
  const [contextOpen, setContextOpen] = useState(false);
  const mobileHeading = useRef<HTMLHeadingElement>(null);
  const previousPanel = useRef<'catalog' | 'cart'>('catalog');
  const [activePanel, setActivePanel] = useState<'catalog' | 'cart'>('catalog');
  const [confirmClearCartOpen, setConfirmClearCartOpen] = useState(false);
  const [paymentOpen, setPaymentOpen] = useState(false);
  const [heldOpen, setHeldOpen] = useState(false);
  const heldStoreId = branchId || 'default-store';
  const [heldSales, setHeldSales] = useState<HeldSale[]>(() => readHeldSales(heldStoreId));
  const [ticketNumber, setTicketNumber] = useState(() => nextTicketNumber(heldStoreId));
  const [catalogPriceUpdatingId, setCatalogPriceUpdatingId] = useState<string | null>(null);

  useEffect(() => {
    if (previousPanel.current !== activePanel) {
      if (activePanel === 'cart') mobileHeading.current?.focus();
      else searchInputRef.current?.focus();
    }
    previousPanel.current = activePanel;
  }, [activePanel]);

  const invoicePrinterRef = useRef<InvoicePrinter>(createInvoicePrinter());
  const searchInputRef = useRef<HTMLInputElement>(null);
  // Barcode scanner: rapidly-typed chars (< 80 ms apart) followed by Enter
  const barcodeBufferRef = useRef<string>('');
  const barcodeLastKeyRef = useRef<number>(0);

  const {
    loading,
    loadError,
    categories,
    menuData,
    usingOfflineCatalog,
    lastUpdatedAt,
    paymentMethods,
    warehouses,
    selectedWarehouseId,
    setSelectedWarehouseId,
    updateProductPrice,
    refreshCatalog,
  } = usePosCatalogData({ currentBranchId: branchId, runtimeConfig });

  const customersState = usePosCustomers();
  const clearSelectedCustomer = customersState.clearSelectedCustomer;

  // ── Price list override ───────────────────────────────────────────────────────
  // When the selected customer has a price list, fetch the resolved prices from
  // GET /api/price-lists/{id}/apply and overlay them onto the catalog.
  // The base catalog (menuData / offline DB) is never mutated.
  const [priceListOverrides, setPriceListOverrides] = useState<Map<string, string>>(new Map());
  // Bumped when a price list changes (Mercure / polling) to re-read the tariff in use.
  const [priceListRefreshTick, setPriceListRefreshTick] = useState(0);
  const priceListKeyRef = useRef('');

  useEffect(() => {
    const priceListId = customersState.priceListId;
    const key = `${customersState.selectedCustomerId ?? ''}:${priceListId ?? ''}`;
    // A refresh of the same tariff keeps the prices on screen if it fails; only a
    // change of customer or tariff starts from scratch.
    const isRefresh = priceListKeyRef.current === key;
    priceListKeyRef.current = key;

    if (!priceListId) {
      setPriceListOverrides(new Map());
      return;
    }

    let cancelled = false;
    const controller = new AbortController();

    httpClient
      .get<unknown>(`${getCoreApiBaseUrl()}price-lists/${priceListId}/apply`, {
        signal: controller.signal,
      })
      .then((response) => {
        if (cancelled) return;
        setPriceListOverrides(parsePriceListOverrides(response.data));
      })
      .catch(() => {
        if (!cancelled && !isRefresh) setPriceListOverrides(new Map());
      });

    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [customersState.selectedCustomerId, customersState.priceListId, priceListRefreshTick]);

  // The whole catalogue at the prices in force for the selected customer. The cart is
  // reconciled against this (not against what the search filter happens to show).
  const pricedMenu = useMemo(
    () =>
      menuData.map((item) => {
        const override = priceListOverrides.get(item.id);
        return override !== undefined ? { ...item, salePrice: override } : item;
      }),
    [menuData, priceListOverrides],
  );

  const filteredMenu = pricedMenu.filter((item) => {
    const matchesCategory = !activeCategory || item.category.id === activeCategory;
    if (!matchesCategory) return false;
    const query = normalizeText(searchQuery);
    if (!query) return true;
    const fields = [item.name, item.sku ?? '', item.barcode ?? ''];
    return fields.some((value) => normalizeText(value).includes(query));
  });

  const { isOnline } = useConnectivity();
  usePosPriceSync(() => {
    void refreshCatalog();
    setPriceListRefreshTick((tick) => tick + 1);
    // Keeps the key offline sales are signed with from running out while online (cheap: it only
    // asks the server when none is held or the one held is about to expire).
    void refreshOfflineCredential();
  }, isOnline);

  useEffect(() => {
    if (isOnline) void refreshOfflineCredential();
  }, [isOnline]);
  const { pendingSalesCount, isSyncing, recentSales, retrySale, refresh, processNow } =
    useOutboxSync();

  const {
    cart,
    paymentLines,
    setPaymentLines,
    discountPercent,
    setDiscountPercent,
    note,
    setNote,
    isProcessingSale,
    lastCheckoutSucceeded,
    checkoutError,
    lastSaleCode,
    lastSaleDocumentType,
    lastSaleTotal,
    lastSaleId,
    lastSaleReceiptUrl,
    lastSaleTaxDocumentId,
    lastSaleCustomerEmail,
    emitInvoice,
    setEmitInvoice,
    requestedVoucherType,
    setRequestedVoucherType,
    canRequestFactura,
    creditSale,
    creditAmount,
    currency,
    setCurrency,
    exchangeRate,
    setExchangeRate,
    addToCart,
    removeFromCart,
    updateQuantity,
    updateLinePrice,
    updateLineDescription,
    clearCart,
    restoreOrder,
    cartItemsCount,
    subtotal,
    discountAmount,
    total,
    payableTotal,
    cashRoundingAdjustment,
    includedTax,
    changeAmount,
    checkoutDisabled,
    handleCheckout,
    getEffectiveStock,
    igvRate,
    readScaleForLine,
    scaleReady,
    scaleReadingIds,
    syncCartPricesFromCatalog,
  } = usePosCheckout({
    tenantId: window.location.hostname || 'default-tenant',
    storeId: branchId || 'default-store',
    selectedWarehouseId,
    selectedCustomerId: customersState.selectedCustomerId,
    selectedCustomer: customersState.selectedCustomer,
    paymentMethods,
    enforceStockCheck,
    warnOnNegativeStock: stockPolicy === 'WARNING_ONLY',
    visibleProducts: filteredMenu,
    pricedCatalog: pricedMenu,
    isOnline,
    useWarehouseStockChecks: runtimeConfig?.ui.inventory.showWarehouseSelector === true,
    onQueuedSaleCreated: async () => {
      await refresh();
      await processNow();
    },
    printSaleInvoice: (saleId: string) => invoicePrinterRef.current.printSaleInvoice(saleId),
    onCashSessionRequired: () => {
      // The server says this user has no open session: learn it, and show the closed state.
      void cashSession.refresh();
      setActivePanel('cart');
    },
    autoInvoice: runtimeConfig?.settings.autoInvoice !== false,
    autoPrint: runtimeConfig?.settings.autoPrint !== false,
    cashRoundingEnabled: runtimeConfig?.settings.cashRoundingEnabled === true,
    onSaleCompleted: () => {
      customersState.resetSelectedCustomer();
      setTicketNumber(nextTicketNumber(heldStoreId));
    },
  });

  const updateHeldSales = (next: HeldSale[]) => {
    setHeldSales(next);
    writeHeldSales(heldStoreId, next);
  };

  const snapshotCurrentOrder = (): HeldSale =>
    buildHeldSale({
      number: ticketNumber,
      cart: cart.map(({ photo: _photo, ...rest }) => rest),
      discountPercent,
      note,
      customerId: customersState.selectedCustomerId,
      customerName: customersState.selectedCustomer?.businessName ?? null,
      total,
    });

  const holdCurrentOrder = () => {
    if (cart.length === 0) return;
    updateHeldSales([...heldSales, snapshotCurrentOrder()]);
    clearCart();
    customersState.resetSelectedCustomer();
    setTicketNumber(nextTicketNumber(heldStoreId));
    toast(`Venta ${formatTicketNumber(ticketNumber)} en espera`, 'success');
  };

  // The key handler is registered once per dependency change; it reaches the latest closure here.
  const holdCurrentOrderRef = useRef(holdCurrentOrder);
  useEffect(() => {
    holdCurrentOrderRef.current = holdCurrentOrder;
  });

  const resumeHeldSale = (id: string) => {
    const target = heldSales.find((sale) => sale.id === id);
    if (!target) return;
    // Resuming never loses the order in progress: it takes the vacated place in the queue.
    const rest = heldSales.filter((sale) => sale.id !== id);
    updateHeldSales(cart.length > 0 ? [...rest, snapshotCurrentOrder()] : rest);
    restoreOrder({
      cart: target.cart as typeof cart,
      discountPercent: target.discountPercent,
      note: target.note,
    });
    if (target.customerId != null) customersState.selectCustomer(String(target.customerId));
    else customersState.resetSelectedCustomer();
    setTicketNumber(target.number);
    setHeldOpen(false);
    setActivePanel('cart');
  };

  useEffect(() => {
    syncCartPricesFromCatalog();
  }, [syncCartPricesFromCatalog]);

  const formatMoney = (value: number) =>
    NumberUtils.formatCurrency(Number(toNumber(value).toFixed(2)), currency);

  const handleCatalogPriceEdit = async (item: (typeof menuData)[number], nextPrice: number) => {
    const normalizedPrice = Number(nextPrice.toFixed(2));
    if (!Number.isFinite(normalizedPrice) || normalizedPrice < 0) {
      toast('El precio debe ser mayor o igual a cero', 'warning');
      return;
    }

    setCatalogPriceUpdatingId(item.id);
    try {
      await productService.updateSalePrice(item.id, normalizedPrice);
      updateProductPrice(item.id, normalizedPrice);
      toast(`Precio de "${item.name}" actualizado`, 'success');
    } catch {
      toast('No se pudo actualizar el precio del producto', 'error');
    } finally {
      setCatalogPriceUpdatingId(null);
    }
  };

  // ── Notify once if a draft cart was restored ──────────────────────────────────────────────
  const draftNotifiedRef = useRef(false);
  useEffect(() => {
    if (!draftNotifiedRef.current && cart.length > 0) {
      draftNotifiedRef.current = true;
      toast(
        `Orden anterior restaurada (${cart.length} ${cart.length === 1 ? 'producto' : 'productos'})`,
        'info',
      );
    }
    // Only on mount — intentional empty-ish deps
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    invoicePrinterRef.current.initialize().catch(() => {
      toast(
        'No hay backend de impresion disponible. La venta se registrara sin impresion automatica.',
        'warning',
      );
    });
  }, []);

  // ── Global keyboard shortcuts ─────────────────────────────────────────────────────────────
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.defaultPrevented) return;
      const target = e.target as HTMLElement;
      if (
        Array.from(
          document.querySelectorAll<HTMLElement>('[role="dialog"], [role="alertdialog"]'),
        ).some(
          (dialog) =>
            dialog.getClientRects().length > 0 && getComputedStyle(dialog).visibility !== 'hidden',
        )
      )
        return;
      if (
        e
          .composedPath()
          .some(
            (node) =>
              node instanceof HTMLElement &&
              ['dialog', 'alertdialog'].includes(node.getAttribute('role') ?? ''),
          )
      )
        return;
      const isInput = Boolean(target.closest('input, textarea, select, [contenteditable="true"]'));
      const isInteractive = Boolean(
        target.closest('button, a, [role="button"], [role="option"], [role="radio"]'),
      );

      // ? → shortcuts list (not while typing, and never in the middle of a scanner burst)
      if (e.key === '?' && !isInput && !isInteractive && barcodeBufferRef.current.length === 0) {
        e.preventDefault();
        setShortcutsOpen(true);
        return;
      }

      // Barcode scanner: single printable char arriving fast → buffer; Enter → resolve
      if (
        e.key.length === 1 &&
        !e.ctrlKey &&
        !e.altKey &&
        !e.metaKey &&
        !isInput &&
        !isInteractive
      ) {
        const now = Date.now();
        if (now - barcodeLastKeyRef.current > 100) barcodeBufferRef.current = '';
        barcodeBufferRef.current += e.key;
        barcodeLastKeyRef.current = now;
      }

      // F2 → focus search
      if (e.key === 'F2') {
        e.preventDefault();
        searchInputRef.current?.focus();
        barcodeBufferRef.current = '';
        return;
      }

      // Shift+F3 → new customer
      if (e.key === 'F3' && e.shiftKey) {
        e.preventDefault();
        if (!hasPermission('customers.create')) return;
        setActivePanel('cart');
        setQuickMode(false); // the customer section hosts the button
        setNewCustomerTrigger((n) => n + 1);
        return;
      }

      // F3 → open & focus customer field
      if (e.key === 'F3') {
        e.preventDefault();
        setActivePanel('cart');
        setQuickMode(false); // ensure customer section is visible
        setFocusCustomerTrigger((n) => n + 1);
        return;
      }

      // Enter → try barcode match first, then checkout
      if (e.key === 'Enter' && !isInput && !e.shiftKey && !e.ctrlKey) {
        const scanned = barcodeBufferRef.current.trim();
        barcodeBufferRef.current = '';
        if (scanned.length >= 3) {
          const match = menuData.find(
            (item) =>
              normalizeText(item.barcode ?? '') === normalizeText(scanned) ||
              normalizeText(item.sku ?? '') === normalizeText(scanned),
          );
          if (match) {
            e.preventDefault();
            void addToCart(match);
            return;
          }
          // An unknown scan must never activate the focused product or charge an order.
          e.preventDefault();
          setSearchQuery(scanned);
          setActivePanel('catalog');
          searchInputRef.current?.focus();
          return;
        }
        if (isInteractive) return;
        if (paymentOpen) {
          // Second Enter inside the payment dialog confirms the charge.
          if (!checkoutDisabled && cajaAbierta) {
            e.preventDefault();
            handleCheckout();
          }
        } else if (cajaAbierta && cart.length > 0) {
          e.preventDefault();
          setPaymentOpen(true);
        }
        return;
      }

      // F4 → hold the current sale · Shift+F4 → list held sales (Alt+F4 stays the OS's)
      if (e.key === 'F4' && !e.altKey) {
        e.preventDefault();
        if (e.shiftKey) setHeldOpen(true);
        else if (!paymentOpen && cart.length > 0) holdCurrentOrderRef.current();
        return;
      }

      // F8 → edit the quantity of the last line
      if (e.key === 'F8') {
        e.preventDefault();
        if (cart.length > 0) {
          setActivePanel('cart');
          setEditLastQtyTrigger((n) => n + 1);
        }
        return;
      }

      // F9 → charge (opens the payment dialog)
      if (e.key === 'F9') {
        e.preventDefault();
        if (!paymentOpen) {
          if (cajaAbierta && cart.length > 0) setPaymentOpen(true);
        } else if (!checkoutDisabled && cajaAbierta) {
          handleCheckout();
        }
        return;
      }

      // Esc → clear cart with confirmation
      if (e.key === 'Escape' && !isInput) {
        if (assistantOpen) {
          e.preventDefault();
          setAssistantOpen(false);
          return;
        }
        if (cart.length > 0) {
          setConfirmClearCartOpen(true);
        }
      }

      // Ctrl+K opens the copilot; ignore while a barcode burst is in the buffer.
      if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K') && assistantEnabled) {
        if (barcodeBufferRef.current.length > 0) return;
        e.preventDefault();
        setAssistantOpen((open) => !open);
      }
    }
    document.addEventListener('keydown', onKey, true);
    return () => document.removeEventListener('keydown', onKey, true);
  }, [
    checkoutDisabled,
    cajaAbierta,
    cart.length,
    handleCheckout,
    paymentOpen,
    clearCart,
    menuData,
    addToCart,
    assistantEnabled,
    assistantOpen,
  ]);

  const selectedWarehouse = warehouses.find((w) => w.id === selectedWarehouseId);
  const assistantScreenExtra = useMemo(
    () => ({
      cartCount: cart.length,
      cartItemsCount,
      total,
      customer: customersState.selectedCustomer
        ? {
            id: customersState.selectedCustomer.id,
            name: customersState.selectedCustomer.businessName,
          }
        : null,
      cajaAbierta,
    }),
    [cart.length, cartItemsCount, total, customersState.selectedCustomer, cajaAbierta],
  );

  const renderOperatorHeader = (variant: 'bar' | 'panel') => (
    <POSOperatorHeader
      variant={variant}
      branchName={currentBranchName}
      userName={currentUserName}
      warehouses={warehouses}
      selectedWarehouseId={selectedWarehouseId}
      setSelectedWarehouseId={setSelectedWarehouseId}
      cajaAbierta={cajaAbierta}
      cajaChecking={cashSession.isLoading}
      cajaName={cashSession.session?.cashRegisterName ?? null}
      cajaOpenedAt={cajaOpenedAt}
      cartItemsCount={cartItemsCount}
      hasCartItems={cart.length > 0}
      isOnline={isOnline}
      isSyncing={isSyncing}
      pendingSalesCount={pendingSalesCount}
      outboxSales={recentSales}
      onRetrySale={(saleId) => {
        void retrySale(saleId);
      }}
      showWarehouseSelector={
        runtimeConfig?.ui.inventory.showWarehouseSelector ?? warehouses.length > 1
      }
      onOpenCaja={() => setOpenCajaDialogOpen(true)}
      onCloseCaja={() => setCloseCajaDialogOpen(true)}
      onCashMovements={() => setCashMovementsOpen(true)}
      onOpenProfile={() => openSettings('account')}
      onSignOut={onSignOut}
      exitTo={exitTo}
      assistantEnabled={assistantEnabled}
      onAssistantClick={() => setAssistantOpen((open) => !open)}
      heldSalesCount={heldSales.length}
      onOpenHeldSales={() => setHeldOpen(true)}
      onShowShortcuts={() => setShortcutsOpen(true)}
    />
  );

  return (
    <div className="pos-shell" data-theme={resolvedTheme} data-active-panel={activePanel}>
      {/* ── Operational context header ── */}
      <div className="pos-desktop-context">{renderOperatorHeader('bar')}</div>
      <AppDialog
        open={contextOpen}
        title="Opciones del punto de venta"
        onClose={() => setContextOpen(false)}
        className="pos-context-dialog"
      >
        {renderOperatorHeader('panel')}
      </AppDialog>

      <header className="pos-mobile-heading">
        <div>
          <p className="pos-mobile-heading__status">
            <span className={`pos-mobile-heading__dot${!isOnline ? ' is-offline' : ''}`} />
            {isOnline
              ? cashSession.isLoading
                ? 'Verificando caja'
                : cajaAbierta
                  ? 'Turno abierto'
                  : 'Caja cerrada'
              : 'Sin conexión'}{' '}
            · {currentBranchName || 'Sucursal'}
          </p>
          <div className="pos-mobile-heading__title">
            {activePanel === 'cart' && (
              <button
                type="button"
                onClick={() => setActivePanel('catalog')}
                aria-label="Volver a productos"
              >
                <i className="ph ph-arrow-left" aria-hidden="true" />
              </button>
            )}
            <h1 ref={mobileHeading} tabIndex={-1}>
              {activePanel === 'cart' ? 'Cobrar' : 'Nueva venta'}
            </h1>
          </div>
        </div>
        <div className="pos-mobile-heading__actions">
          {activePanel === 'cart' && cart.length > 0 && (
            <button type="button" className="pos-mobile-heading__clear" onClick={clearCart}>
              Vaciar
            </button>
          )}
          <button
            type="button"
            onClick={() => setContextOpen(true)}
            aria-label="Opciones del punto de venta"
          >
            <i className="ph ph-dots-three" aria-hidden="true" />
          </button>
        </div>
      </header>

      {/* ── Left: catalogue ── */}
      <POSCatalogPanel
        loading={loading}
        loadError={loadError}
        searchQuery={searchQuery}
        setSearchQuery={setSearchQuery}
        cartItemsCount={cartItemsCount}
        filteredMenu={filteredMenu}
        categories={categories}
        activeCategory={activeCategory}
        setActiveCategory={setActiveCategory}
        onAddToCart={addToCart}
        onCatalogPriceEdit={allowCatalogPriceEdit ? handleCatalogPriceEdit : undefined}
        catalogPriceUpdatingId={catalogPriceUpdatingId}
        getItemQuantity={(id) => cart.find((cartItem) => cartItem.id === id)?.quantity || 0}
        getEffectiveStock={getEffectiveStock}
        enforceStockCheck={enforceStockCheck}
        searchInputRef={searchInputRef}
        usingOfflineCatalog={usingOfflineCatalog}
        lastCatalogSyncAt={lastUpdatedAt}
      />

      {cart.length > 0 && activePanel === 'catalog' && (
        <button
          type="button"
          className="pos-mobile-cart-summary"
          onClick={() => setActivePanel('cart')}
          aria-label={`Revisar orden y cobrar ${formatMoney(payableTotal)}`}
          data-testid="pos-review-order"
        >
          <span className="pos-mobile-cart-summary__icon">
            <i className="ph ph-shopping-cart" aria-hidden="true" />
          </span>
          <span className="pos-mobile-cart-summary__amount">
            <small>
              {cartItemsCount} ítems · {cart.length} {cart.length === 1 ? 'producto' : 'productos'}
            </small>
            <strong>{formatMoney(payableTotal)}</strong>
          </span>
          <span>
            Cobrar <i className="ph ph-arrow-right" aria-hidden="true" />
          </span>
        </button>
      )}

      {/* ── Right: checkout ── */}
      <POSCheckoutPanel
        cart={cart}
        cartItemsCount={cartItemsCount}
        clearCart={clearCart}
        selectedWarehouseName={selectedWarehouse?.name ?? ''}
        cajaAbierta={cajaAbierta}
        quickMode={quickMode}
        selectedCustomer={customersState.selectedCustomer}
        onCustomerChange={customersState.selectCustomer}
        clearSelectedCustomer={clearSelectedCustomer}
        focusCustomerTrigger={focusCustomerTrigger}
        newCustomerTrigger={newCustomerTrigger}
        editLastQtyTrigger={editLastQtyTrigger}
        lastSaleCode={lastSaleCode}
        lastSaleDocumentType={lastSaleDocumentType}
        lastSaleTotal={lastSaleTotal}
        lastSaleReceiptUrl={lastSaleReceiptUrl}
        lastSaleTaxDocumentId={lastSaleTaxDocumentId}
        lastSaleCustomerEmail={lastSaleCustomerEmail}
        onPrintLastSale={() => {
          if (lastSaleId) {
            void invoicePrinterRef.current.printSaleInvoice(lastSaleId);
          }
        }}
        selectedCustomerId={customersState.selectedCustomerId}
        note={note}
        setNote={setNote}
        updateQuantity={updateQuantity}
        updateLinePrice={updateLinePrice}
        allowLinePriceOverride={allowLinePriceOverride}
        pieceCountEnabled={runtimeConfig?.ui.pos?.pieceCountEnabled === true}
        allowDiscount={allowDiscount}
        updateLineDescription={updateLineDescription}
        removeFromCart={removeFromCart}
        formatMoney={formatMoney}
        paymentMethods={paymentMethods}
        paymentLines={paymentLines}
        setPaymentLines={setPaymentLines}
        emitInvoice={emitInvoice}
        setEmitInvoice={setEmitInvoice}
        requestedVoucherType={requestedVoucherType}
        setRequestedVoucherType={setRequestedVoucherType}
        canRequestFactura={canRequestFactura}
        creditSale={creditSale}
        creditAmount={creditAmount}
        currency={currency}
        setCurrency={setCurrency}
        exchangeRate={exchangeRate}
        setExchangeRate={setExchangeRate}
        discountPercent={discountPercent}
        setDiscountPercent={setDiscountPercent}
        subtotal={subtotal}
        discountAmount={discountAmount}
        includedTax={includedTax}
        total={total}
        payableTotal={payableTotal}
        cashRoundingAdjustment={cashRoundingAdjustment}
        igvRate={igvRate}
        changeAmount={changeAmount}
        checkoutDisabled={checkoutDisabled}
        handleCheckout={handleCheckout}
        isProcessingSale={isProcessingSale}
        lastCheckoutSucceeded={lastCheckoutSucceeded}
        checkoutError={checkoutError}
        syncSales={recentSales}
        onRetrySale={(saleId) => {
          void retrySale(saleId);
        }}
        onReadScaleWeight={(id) => {
          void readScaleForLine(id);
        }}
        scaleReady={scaleReady}
        scaleReadingIds={scaleReadingIds}
        ticketLabel={formatTicketNumber(ticketNumber)}
        onHold={holdCurrentOrder}
        paymentOpen={paymentOpen}
        setPaymentOpen={setPaymentOpen}
      />

      <POSShortcutsDialog
        open={shortcutsOpen}
        onClose={() => setShortcutsOpen(false)}
        assistantEnabled={assistantEnabled}
      />

      <AppDialog
        open={heldOpen}
        title="Ventas en espera"
        onClose={() => setHeldOpen(false)}
        className="pos-held-dialog"
      >
        {heldSales.length === 0 ? (
          <p>No hay ventas en espera.</p>
        ) : (
          <ul className="pos-held-list">
            {heldSales.map((sale) => (
              <li key={sale.id} className="pos-held-list__item" data-testid="pos-held-sale">
                <div>
                  <strong>Venta {formatTicketNumber(sale.number)}</strong>
                  <span>
                    {sale.customerName ?? 'Sin cliente'} · {sale.cart.length}{' '}
                    {sale.cart.length === 1 ? 'producto' : 'productos'} ·{' '}
                    {new Date(sale.heldAt).toLocaleTimeString('es-PE', {
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </span>
                </div>
                <strong>{formatMoney(sale.total)}</strong>
                <Button size="sm" variant="primary" onClick={() => resumeHeldSale(sale.id)}>
                  Retomar
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => updateHeldSales(heldSales.filter((item) => item.id !== sale.id))}
                >
                  Descartar
                </Button>
              </li>
            ))}
          </ul>
        )}
      </AppDialog>

      {assistantEnabled && assistantOpen ? (
        <div className="pos-assistant-overlay" role="dialog" aria-label="Asistente de IA">
          <div className="pos-assistant-overlay__panel">
            <div className="pos-assistant-overlay__header">
              <strong>Asistente de IA</strong>
              <button
                type="button"
                className="pos-shortcuts-btn"
                onClick={() => setAssistantOpen(false)}
                aria-label="Cerrar asistente"
              >
                <i className="ph ph-x" aria-hidden="true" />
              </button>
            </div>
            {!isOnline ? (
              <p className="pos-assistant-overlay__offline">El asistente necesita conexión.</p>
            ) : (
              <Suspense fallback={null}>
                <Assistant showToolbar={false} screenExtra={assistantScreenExtra} />
              </Suspense>
            )}
          </div>
        </div>
      ) : null}

      {/* ── Clear cart confirmation (triggered by Esc key) ── */}
      <ConfirmDialog
        open={confirmClearCartOpen}
        title="Cancelar venta"
        message="¿Cancelar la venta y vaciar el carrito?"
        confirmLabel="Vaciar carrito"
        cancelLabel="Cancelar"
        variant="danger"
        onConfirm={() => {
          setConfirmClearCartOpen(false);
          clearCart();
        }}
        onCancel={() => setConfirmClearCartOpen(false)}
      />
      <CashOpenDialog
        open={openCajaDialogOpen}
        registers={cashSession.availableRegisters}
        busyRegisters={cashSession.busyRegisters}
        onClose={() => setOpenCajaDialogOpen(false)}
        onOpened={async () => {
          await cashSession.refresh();
          setOpenCajaDialogOpen(false);
        }}
      />
      {cashSession.session && (
        <Suspense fallback={null}>
          {closeCajaDialogOpen && (
            <CashCloseDialog
              open
              sessionId={cashSession.session.id}
              onClose={() => setCloseCajaDialogOpen(false)}
              onClosed={async () => {
                setCloseCajaDialogOpen(false);
                await cashSession.refresh();
              }}
            />
          )}
          {cashMovementsOpen && (
            <CashMovementsDialog
              open
              sessionId={cashSession.session.id}
              registerName={cashSession.session.cashRegisterName}
              onClose={() => setCashMovementsOpen(false)}
            />
          )}
        </Suspense>
      )}
    </div>
  );
};

export default POSPage;
export { POSPage };

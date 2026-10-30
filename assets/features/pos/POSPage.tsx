/* eslint-disable react-hooks/set-state-in-effect -- POS initialization synchronizes branch and cash-session state */
import React, {
  lazy,
  Suspense,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import './POSPage.scss';
import './POSDesign.scss';
import './HardwareAgentDialog.scss';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { useSessionInfo } from '@shared/sessionInfo';
import { createInvoicePrinter, InvoicePrinter } from '@features/sales';
import { ThemeContext } from '@assets/theme/theme';
import { POSOperatorHeader } from './POSOperatorHeader';
import { POSCatalogPanel } from './POSCatalogPanel';
import { POSCheckoutPanel } from './POSCheckoutPanel';
import { productService } from '@features/product/services/productService';
import { usePosCustomers } from './usePosCustomers';
import { usePosCheckout } from './usePosCheckout';
import { usePosCatalogData } from './usePosCatalogData';
import { normalizeText, toMoneyString, toNumber } from './posHelpers';
import { useConnectivity } from '@offline/hooks/useConnectivity';
import { useOutboxSync } from '@offline/hooks/useOutboxSync';
import { AppDialog, ConfirmDialog } from '@nubitio/ui';
import { useFeature, useTenantRuntimeConfig } from '@shared/hooks';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { OpenCajaDialog } from './OpenCajaDialog';
import { hasPermission } from '@shared/permissions';

const Assistant = lazy(() => import('@features/assistant/Assistant'));

const POSPage = () => {
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
  const [cajaAbierta, setCajaAbiertaState] = useState(
    () => localStorage.getItem('pos_caja_abierta') !== 'false',
  );
  const [cashRegisterId, setCashRegisterId] = useState<number | null>(null);
  const [openCajaDialogOpen, setOpenCajaDialogOpen] = useState(false);
  const [quickMode, setQuickMode] = useState(false);
  const [focusCustomerTrigger, setFocusCustomerTrigger] = useState(0);
  const [contextOpen, setContextOpen] = useState(false);
  const mobileHeading = useRef<HTMLHeadingElement>(null);
  const previousPanel = useRef<'catalog' | 'cart'>('catalog');
  const [activePanel, setActivePanel] = useState<'catalog' | 'cart'>('catalog');
  const [confirmClearCartOpen, setConfirmClearCartOpen] = useState(false);
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
  } = usePosCatalogData({ currentBranchId: branchId, runtimeConfig });

  const customersState = usePosCustomers();
  const clearSelectedCustomer = customersState.clearSelectedCustomer;

  // ── Price list override ───────────────────────────────────────────────────────
  // When the selected customer has a price list, fetch the resolved prices from
  // GET /api/price-lists/{id}/apply and overlay them onto the catalog.
  // The base catalog (menuData / offline DB) is never mutated.
  const [priceListOverrides, setPriceListOverrides] = useState<Map<string, string>>(new Map());

  useEffect(() => {
    const priceListId = customersState.priceListId;

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
        const raw = response.data;
        const items: Array<{ id: string; price: string }> = Array.isArray(raw)
          ? (raw as Array<{ id: string; price: string }>)
          : (((raw as Record<string, unknown>).member as
              Array<{ id: string; price: string }> | undefined) ??
            ((raw as Record<string, unknown>)['hydra:member'] as
              Array<{ id: string; price: string }> | undefined) ??
            []);
        const map = new Map<string, string>();
        for (const item of items) {
          const price = toMoneyString(item.price, '');
          if (price !== '') map.set(String(item.id), price);
        }
        setPriceListOverrides(map);
      })
      .catch(() => {
        if (!cancelled) setPriceListOverrides(new Map());
      });

    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [customersState.selectedCustomerId, customersState.priceListId]);

  const filteredMenu = menuData
    .filter((item) => {
      const matchesCategory = !activeCategory || item.category.id === activeCategory;
      if (!matchesCategory) return false;
      const query = normalizeText(searchQuery);
      if (!query) return true;
      const fields = [item.name, item.sku ?? '', item.barcode ?? ''];
      return fields.some((value) => normalizeText(value).includes(query));
    })
    .map((item) => {
      const override = priceListOverrides.get(item.id);
      return override !== undefined ? { ...item, salePrice: override } : item;
    });

  const { isOnline } = useConnectivity();
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
    isOnline,
    useWarehouseStockChecks: runtimeConfig?.ui.inventory.showWarehouseSelector === true,
    onQueuedSaleCreated: async () => {
      await refresh();
      await processNow();
    },
    printSaleInvoice: (saleId: string) => invoicePrinterRef.current.printSaleInvoice(saleId),
    onCashSessionRequired: () => {
      setCajaAbierta(false);
      setActivePanel('cart');
    },
    autoInvoice: runtimeConfig?.settings.autoInvoice !== false,
    autoPrint: runtimeConfig?.settings.autoPrint !== false,
    cashRoundingEnabled: runtimeConfig?.settings.cashRoundingEnabled === true,
    onSaleCompleted: customersState.resetSelectedCustomer,
  });

  useEffect(() => {
    syncCartPricesFromCatalog();
  }, [priceListOverrides, syncCartPricesFromCatalog]);

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

  // ── Caja session timestamp ────────────────────────────────────────────────────────────────
  const [cajaOpenedAt, setCajaOpenedAt] = useState<string | null>(() => {
    if (localStorage.getItem('pos_caja_abierta') === 'false') {
      return null;
    }

    const existing = localStorage.getItem('pos_caja_opened_at');
    if (existing) {
      return existing;
    }

    const now = new Date().toISOString();
    localStorage.setItem('pos_caja_opened_at', now);
    return now;
  });

  const setCajaAbierta = useCallback(
    (nextOpen: boolean) => {
      setCajaAbiertaState(nextOpen);
      localStorage.setItem('pos_caja_abierta', String(nextOpen));

      if (!nextOpen) {
        localStorage.removeItem('pos_caja_opened_at');
        setCajaOpenedAt(null);
        return;
      }

      const openedAt = localStorage.getItem('pos_caja_opened_at') ?? new Date().toISOString();
      localStorage.setItem('pos_caja_opened_at', openedAt);
      setCajaOpenedAt(openedAt);
    },
    [setCajaOpenedAt],
  );

  // ── Sync caja state with server on mount ─────────────────────────────────────────────────
  // localStorage defaults to 'open'; verify against the real cash-registers/dashboard so
  // the POS header always reflects actual server state.
  useEffect(() => {
    httpClient
      .get<{ id: number; status: string; openedAt?: string | null }[]>(
        getCoreApiBaseUrl() + 'cash-registers/dashboard',
      )
      .then((response) => {
        const rows = response.data;
        if (!Array.isArray(rows) || rows.length === 0) return;
        // Always store the first register id so "Abrir caja" knows which register to open.
        setCashRegisterId(rows[0].id);
        const openRow = rows.find((r) => r.status === 'open');
        if (openRow) {
          setCashRegisterId(openRow.id);
          if (!cajaAbierta) {
            setCajaAbiertaState(true);
            localStorage.setItem('pos_caja_abierta', 'true');
          }
          if (openRow.openedAt) {
            localStorage.setItem('pos_caja_opened_at', openRow.openedAt);
            setCajaOpenedAt(openRow.openedAt);
          }
        } else {
          // No open session on the server — correct the local state.
          setCajaAbiertaState(false);
          localStorage.setItem('pos_caja_abierta', 'false');
          localStorage.removeItem('pos_caja_opened_at');
          setCajaOpenedAt(null);
        }
      })
      .catch(() => {
        // Network error: keep the localStorage value rather than flipping state.
      });
    // Run once on mount only.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

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
        if (!checkoutDisabled && cajaAbierta && cart.length > 0) {
          e.preventDefault();
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

  const operatorHeader = (
    <POSOperatorHeader
      branchName={currentBranchName}
      userName={currentUserName}
      warehouses={warehouses}
      selectedWarehouseId={selectedWarehouseId}
      setSelectedWarehouseId={setSelectedWarehouseId}
      cajaAbierta={cajaAbierta}
      setCajaAbierta={setCajaAbierta}
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
      assistantEnabled={assistantEnabled}
      onAssistantClick={() => setAssistantOpen((open) => !open)}
    />
  );

  return (
    <div className="pos-shell" data-theme={resolvedTheme} data-active-panel={activePanel}>
      {/* ── Operational context header ── */}
      <div className="pos-desktop-context">{operatorHeader}</div>
      <AppDialog
        open={contextOpen}
        title="Opciones del punto de venta"
        onClose={() => setContextOpen(false)}
        className="pos-context-dialog"
      >
        {operatorHeader}
      </AppDialog>

      <header className="pos-mobile-heading">
        <div>
          <p className="pos-mobile-heading__status">
            <span className={`pos-mobile-heading__dot${!isOnline ? ' is-offline' : ''}`} />
            {isOnline ? (cajaAbierta ? 'Turno abierto' : 'Caja cerrada') : 'Sin conexión'} ·{' '}
            {currentBranchName || 'Sucursal'}
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
        lastSaleCode={lastSaleCode}
        lastSaleDocumentType={lastSaleDocumentType}
        lastSaleTotal={lastSaleTotal}
        lastSaleReceiptUrl={lastSaleReceiptUrl}
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
      />

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
      {openCajaDialogOpen && cashRegisterId !== null && (
        <OpenCajaDialog
          cashRegisterId={cashRegisterId}
          onSuccess={(openedAt) => {
            setOpenCajaDialogOpen(false);
            setCajaAbierta(true);
            localStorage.setItem('pos_caja_opened_at', openedAt);
            setCajaOpenedAt(openedAt);
          }}
          onCancel={() => setOpenCajaDialogOpen(false)}
        />
      )}
    </div>
  );
};

export default POSPage;
export { POSPage };

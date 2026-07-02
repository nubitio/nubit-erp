import React, { useCallback, useContext, useEffect, useRef, useState } from 'react';
import './POSPage.scss';
import './HardwareAgentDialog.scss';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { useSessionInfo } from '@shared/sessionInfo';
import { Setting, settings } from '@shared/settings';
import { createInvoicePrinter, InvoicePrinter } from '@features/sales';
import { ThemeContext } from '@assets/theme/theme';
import { POSOperatorHeader } from './POSOperatorHeader';
import { POSCatalogPanel } from './POSCatalogPanel';
import { POSCheckoutPanel } from './POSCheckoutPanel';
import { productService } from '@features/product/services/productService';
import { usePosCustomers } from './usePosCustomers';
import { usePosCheckout } from './usePosCheckout';
import { usePosCatalogData } from './usePosCatalogData';
import { isCashPaymentMethod, normalizeText, parseBooleanSetting, toNumber } from './posHelpers';
import { useConnectivity } from '@offline/hooks/useConnectivity';
import { useOutboxSync } from '@offline/hooks/useOutboxSync';
import { ConfirmDialog } from '@nubitio/ui';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { OpenCajaDialog } from './OpenCajaDialog';

const POSPage = () => {
  const themeContext = useContext(ThemeContext);
  const resolvedTheme = themeContext?.theme ?? 'light';

  const { currentBranchId, currentBranchName, currentUserName } = useSessionInfo();
  const branchId = String(currentBranchId ?? '');
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const allowLinePriceOverride = runtimeConfig?.ui.pos?.allowLinePriceOverride ?? true;
  const allowCatalogPriceEdit = runtimeConfig?.ui.pos?.allowCatalogPriceEdit ?? true;

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
  const [activePanel, setActivePanel] = useState<'catalog' | 'cart'>('catalog');
  const [confirmClearCartOpen, setConfirmClearCartOpen] = useState(false);
  const [catalogPriceUpdatingId, setCatalogPriceUpdatingId] = useState<string | null>(null);

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
    selectedPaymentMethodId,
    setSelectedPaymentMethodId,
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
  const [priceListOverrides, setPriceListOverrides] = useState<Map<string, number>>(new Map());

  useEffect(() => {
    const selectedCustomer = customersState.customerOptions.find(
      (c) => c.id === customersState.selectedCustomerId,
    );
    const priceListId = selectedCustomer?.priceListId;

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
          : ((raw as Record<string, unknown>)['hydra:member'] as Array<{ id: string; price: string }> ?? []);
        const map = new Map<string, number>();
        for (const item of items) {
          const price = parseFloat(item.price);
          if (Number.isFinite(price)) map.set(String(item.id), price);
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
  }, [customersState.selectedCustomerId, customersState.customerOptions]);

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

  const selectedPaymentMethod =
    paymentMethods.find((method) => method.id === selectedPaymentMethodId) ?? null;
  const isCashPaymentSelected = isCashPaymentMethod(selectedPaymentMethod);
  const { isOnline } = useConnectivity();
  const { pendingSalesCount, isSyncing, recentSales, retrySale, refresh, processNow } =
    useOutboxSync();

  const {
    cart,
    amountReceived,
    setAmountReceived,
    discountPercent,
    setDiscountPercent,
    note,
    setNote,
    isProcessingSale,
    lastCheckoutSucceeded,
    lastSaleCode,
    emitInvoice,
    setEmitInvoice,
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
    includedTax,
    amountReceivedNumber,
    changeAmount,
    amountMissing,
    quickCashActions,
    checkoutDisabled,
    handleCheckout,
    getEffectiveStock,
    igvRate,
    readScaleForLine,
    scaleReady,
  } = usePosCheckout({
    tenantId: window.location.hostname || 'default-tenant',
    storeId: branchId || 'default-store',
    selectedWarehouseId,
    selectedCustomerId: customersState.selectedCustomerId,
    selectedPaymentMethodId,
    selectedPaymentMethodName: selectedPaymentMethod?.name,
    isCashPaymentSelected,
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
  });

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

  const setCajaAbierta = useCallback((nextOpen: boolean) => {
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
  }, [setCajaOpenedAt]);

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

  // ── Load settings ─────────────────────────────────────────────────────────────────────────
  useEffect(() => {
    (async () => {
      const autoInvoiceSetting = await settings.get(Setting.AUTO_INVOICE);
      setEmitInvoice(parseBooleanSetting(autoInvoiceSetting?.value, false));
    })();
  }, [setEmitInvoice]);

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
      const tag = (e.target as HTMLElement).tagName;
      const isInput = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';

      // Barcode scanner: single printable char arriving fast → buffer; Enter → resolve
      if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey && !isInput) {
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
        }
        if (!checkoutDisabled && cajaAbierta && cart.length > 0) {
          e.preventDefault();
          handleCheckout();
        }
        return;
      }

      // Esc → clear cart with confirmation
      if (e.key === 'Escape' && !isInput) {
        if (cart.length > 0) {
          setConfirmClearCartOpen(true);
        }
      }
    }
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [checkoutDisabled, cajaAbierta, cart.length, handleCheckout, clearCart, menuData, addToCart]);

  const selectedWarehouse = warehouses.find((w) => w.id === selectedWarehouseId);

  return (
    <div className="pos-shell" data-theme={resolvedTheme} data-active-panel={activePanel}>
      {/* ── Operational context header ── */}
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
        showWarehouseSelector={runtimeConfig?.ui.inventory.showWarehouseSelector ?? warehouses.length > 1}
        onOpenCaja={() => setOpenCajaDialogOpen(true)}
      />

      {/* ── Mobile tab bar (hidden on desktop via CSS) ── */}
      <div className="pos-mobile-tabs" role="tablist">
        <button
          type="button"
          role="tab"
          aria-selected={activePanel === 'catalog'}
          className={`pos-mobile-tabs__btn${activePanel === 'catalog' ? ' is-active' : ''}`}
          onClick={() => setActivePanel('catalog')}
        >
          <i className="ph ph-grid-four" aria-hidden="true" />
          <span>Productos</span>
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={activePanel === 'cart'}
          className={`pos-mobile-tabs__btn${activePanel === 'cart' ? ' is-active' : ''}`}
          onClick={() => setActivePanel('cart')}
        >
          <i className="ph ph-shopping-cart" aria-hidden="true" />
          <span>Carrito</span>
          {cartItemsCount > 0 && <span className="pos-mobile-tabs__badge">{cartItemsCount}</span>}
        </button>
      </div>

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

      {/* ── Right: checkout ── */}
      <POSCheckoutPanel
        cart={cart}
        cartItemsCount={cartItemsCount}
        clearCart={clearCart}
        selectedWarehouseName={selectedWarehouse?.name ?? ''}
        cajaAbierta={cajaAbierta}
        quickMode={quickMode}
        customerSearch={customersState.customerSearch}
        setCustomerSearch={customersState.setCustomerSearch}
        clearSelectedCustomer={clearSelectedCustomer}
        focusCustomerTrigger={focusCustomerTrigger}
        lastSaleCode={lastSaleCode}
        onPrintLastSale={() => {
          if (lastSaleCode) {
            void invoicePrinterRef.current.printSaleInvoice(lastSaleCode);
          }
        }}
        selectedCustomerId={customersState.selectedCustomerId}
        setSelectedCustomerId={customersState.setSelectedCustomerId}
        customerOptions={customersState.customerOptions}
        customersLoading={customersState.customersLoading}
        customersHasMore={customersState.customersHasMore}
        customersPage={customersState.customersPage}
        deferredCustomerSearch={customersState.deferredCustomerSearch}
        loadCustomers={customersState.loadCustomers}
        note={note}
        setNote={setNote}
        updateQuantity={updateQuantity}
        updateLinePrice={updateLinePrice}
        allowLinePriceOverride={allowLinePriceOverride}
        updateLineDescription={updateLineDescription}
        removeFromCart={removeFromCart}
        formatMoney={formatMoney}
        paymentMethods={paymentMethods}
        selectedPaymentMethodId={selectedPaymentMethodId}
        setSelectedPaymentMethodId={(id) => setSelectedPaymentMethodId(id)}
        emitInvoice={emitInvoice}
        setEmitInvoice={setEmitInvoice}
        currency={currency}
        setCurrency={setCurrency}
        exchangeRate={exchangeRate}
        setExchangeRate={setExchangeRate}
        discountPercent={discountPercent}
        setDiscountPercent={setDiscountPercent}
        isCashPaymentSelected={isCashPaymentSelected}
        amountReceived={amountReceived}
        setAmountReceived={setAmountReceived}
        quickCashActions={quickCashActions}
        subtotal={subtotal}
        discountAmount={discountAmount}
        includedTax={includedTax}
        total={total}
        igvRate={igvRate}
        amountReceivedNumber={amountReceivedNumber}
        amountMissing={amountMissing}
        changeAmount={changeAmount}
        checkoutDisabled={checkoutDisabled}
        handleCheckout={handleCheckout}
        isProcessingSale={isProcessingSale}
        lastCheckoutSucceeded={lastCheckoutSucceeded}
        syncSales={recentSales}
        onRetrySale={(saleId) => {
          void retrySale(saleId);
        }}
        onReadScaleWeight={(id) => {
          void readScaleForLine(id);
        }}
        scaleReady={scaleReady}
      />

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

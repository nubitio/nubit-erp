import React, { useContext, useEffect, useRef, useState } from 'react';
import './POSPage.scss';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { userInformation } from '@core/common/UserInformation';
import { Setting, settings } from '@app/Settings';
import { createInvoicePrinter, InvoicePrinter } from '@features/sale';
import { ThemeContext } from '@assets/theme/theme';
import { POSOperatorHeader } from './POSOperatorHeader';
import { POSCatalogPanel } from './POSCatalogPanel';
import { POSCheckoutPanel } from './POSCheckoutPanel';
import { usePosCustomers } from './usePosCustomers';
import { usePosCheckout } from './usePosCheckout';
import { usePosCatalogData } from './usePosCatalogData';
import { isCashPaymentMethod, normalizeText, parseBooleanSetting, toNumber } from './posHelpers';
import { useConnectivity } from '@offline/hooks/useConnectivity';
import { useOutboxSync } from '@offline/hooks/useOutboxSync';
import { ConfirmDialog } from '@shared/components';

type QzInvoicePrinter = InvoicePrinter;

const POSPage = () => {
  const themeContext = useContext(ThemeContext);
  const resolvedTheme = themeContext?.theme ?? 'light';

  const { currentBranchId, currentBranchName, currentUserName } = userInformation();
  const branchId = String(currentBranchId ?? '');

  const [activeCategory, setActiveCategory] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [enforceStockCheck, setEnforceStockCheck] = useState(true);
  const [cajaAbierta, setCajaAbierta] = useState(
    () => localStorage.getItem('pos_caja_abierta') !== 'false',
  );
  const [quickMode, setQuickMode] = useState(false);
  const [focusCustomerTrigger, setFocusCustomerTrigger] = useState(0);
  const [activePanel, setActivePanel] = useState<'catalog' | 'cart'>('catalog');
  const [confirmClearCartOpen, setConfirmClearCartOpen] = useState(false);

  const qzInvoicePrinterRef = useRef<QzInvoicePrinter>(createInvoicePrinter());
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
  } = usePosCatalogData({ currentBranchId: branchId });

  const customersState = usePosCustomers();
  const clearSelectedCustomer = customersState.clearSelectedCustomer;

  const filteredMenu = menuData.filter((item) => {
    const matchesCategory = !activeCategory || item.category.id === activeCategory;
    if (!matchesCategory) return false;
    const query = normalizeText(searchQuery);
    if (!query) return true;
    const fields = [item.name, item.sku ?? '', item.barcode ?? ''];
    return fields.some((value) => normalizeText(value).includes(query));
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
  } = usePosCheckout({
    tenantId: window.location.hostname || 'default-tenant',
    storeId: branchId || 'default-store',
    selectedWarehouseId,
    selectedCustomerId: customersState.selectedCustomerId,
    selectedPaymentMethodId,
    selectedPaymentMethodName: selectedPaymentMethod?.name,
    isCashPaymentSelected,
    enforceStockCheck,
    visibleProducts: filteredMenu,
    isOnline,
    onQueuedSaleCreated: async () => {
      await refresh();
      await processNow();
    },
    printSaleInvoice: (saleId: string) => qzInvoicePrinterRef.current.printSaleInvoice(saleId),
  });

  const formatMoney = (value: number) =>
    NumberUtils.formatCurrency(Number(toNumber(value).toFixed(2)), currency);

  // ── Caja session timestamp ────────────────────────────────────────────────────────────────
  const [cajaOpenedAt, setCajaOpenedAt] = useState<string | null>(() =>
    localStorage.getItem('pos_caja_opened_at'),
  );

  // ── Persist caja state ─────────────────────────────────────────────────────────────────────
  useEffect(() => {
    localStorage.setItem('pos_caja_abierta', String(cajaAbierta));
    if (cajaAbierta) {
      if (!localStorage.getItem('pos_caja_opened_at')) {
        const now = new Date().toISOString();
        localStorage.setItem('pos_caja_opened_at', now);
        setCajaOpenedAt(now);
      }
    } else {
      localStorage.removeItem('pos_caja_opened_at');
      setCajaOpenedAt(null);
    }
  }, [cajaAbierta]);

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
      const checkStockSetting = await settings.get(Setting.CHECK_STOCK);
      setEnforceStockCheck(parseBooleanSetting(checkStockSetting?.value, true));
      const autoInvoiceSetting = await settings.get(Setting.AUTO_INVOICE);
      setEmitInvoice(parseBooleanSetting(autoInvoiceSetting?.value, false));
    })();
  }, [setEmitInvoice]);

  useEffect(() => {
    qzInvoicePrinterRef.current.initialize().catch(() => {
      toast('QZ Tray no disponible. La venta se registrara sin impresion automatica.', 'warning');
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
            void qzInvoicePrinterRef.current.printSaleInvoice(lastSaleCode);
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
        amountReceivedNumber={amountReceivedNumber}
        amountMissing={amountMissing}
        changeAmount={changeAmount}
        checkoutDisabled={checkoutDisabled}
        handleCheckout={handleCheckout}
        isProcessingSale={isProcessingSale}
        syncSales={recentSales}
        onRetrySale={(saleId) => {
          void retrySale(saleId);
        }}
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
    </div>
  );
};

export default POSPage;
export { POSPage };

import React, { useContext, useEffect, useRef, useState } from 'react';
import './POSPage.scss';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { userInformation } from '@platform/common/UserInformation';
import { Setting, settings } from '@assets/Settings';
import { createQzInvoicePrinter } from '@features/sale';
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

type QzInvoicePrinter = ReturnType<typeof createQzInvoicePrinter>;

const POSPage = () => {
  const themeContext = useContext(ThemeContext);
  const resolvedTheme = themeContext?.theme ?? 'light';

  const { currentBranchId, currentBranchName, currentUserName } = userInformation();
  const branchId = String(currentBranchId ?? '');

  const [activeCategory, setActiveCategory] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [enforceStockCheck, setEnforceStockCheck] = useState(true);
  const [cajaAbierta, setCajaAbierta] = useState(true);
  const [quickMode, setQuickMode] = useState(false);

  const qzInvoicePrinterRef = useRef<QzInvoicePrinter>(createQzInvoicePrinter());
  const searchInputRef = useRef<HTMLInputElement>(null);

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
  const { pendingSalesCount, isSyncing, recentSales, retrySale, refresh, processNow } = useOutboxSync();

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
    NumberUtils.formatCurrency(Number(toNumber(value).toFixed(2)));

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

      // F2 → focus search
      if (e.key === 'F2') {
        e.preventDefault();
        searchInputRef.current?.focus();
        return;
      }

      // Enter → checkout when nothing is focused and cart has items
      if (e.key === 'Enter' && !isInput && !e.shiftKey && !e.ctrlKey) {
        if (!checkoutDisabled && cajaAbierta && cart.length > 0) {
          e.preventDefault();
          handleCheckout();
        }
        return;
      }

      // Esc → clear cart with confirmation
      if (e.key === 'Escape' && !isInput) {
        if (cart.length > 0) {
          if (window.confirm('¿Cancelar la venta y vaciar el carrito?')) {
            clearCart();
          }
        }
      }
    }
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [checkoutDisabled, cajaAbierta, cart.length, handleCheckout, clearCart]);

  const selectedWarehouse = warehouses.find((w) => w.id === selectedWarehouseId);

  return (
    <div className="pos-shell" data-theme={resolvedTheme}>
      {/* ── Operational context header ── */}
      <POSOperatorHeader
        branchName={currentBranchName}
        userName={currentUserName}
        warehouses={warehouses}
        selectedWarehouseId={selectedWarehouseId}
        setSelectedWarehouseId={setSelectedWarehouseId}
        cajaAbierta={cajaAbierta}
        setCajaAbierta={setCajaAbierta}
        quickMode={quickMode}
        setQuickMode={setQuickMode}
        cartItemsCount={cartItemsCount}
        hasCartItems={cart.length > 0}
        lastSaleCode={lastSaleCode}
        isOnline={isOnline}
        isSyncing={isSyncing}
        pendingSalesCount={pendingSalesCount}
        outboxSales={recentSales}
        onRetrySale={(saleId) => {
          void retrySale(saleId);
        }}
      />

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
    </div>
  );
};

export default POSPage;

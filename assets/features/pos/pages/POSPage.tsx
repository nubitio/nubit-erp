import React, { useEffect, useRef, useState } from 'react';
import './POSPage.scss';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { userInformation } from '@platform/common/UserInformation';
import { Setting, settings } from '@assets/Settings';
import { createQzInvoicePrinter } from '@features/sale';
import { POSCatalogPanel } from './POSCatalogPanel';
import { POSCheckoutPanel } from './POSCheckoutPanel';
import { usePosCustomers } from './usePosCustomers';
import { usePosCheckout } from './usePosCheckout';
import { usePosCatalogData } from './usePosCatalogData';
import { isCashPaymentMethod, normalizeText, parseBooleanSetting, toNumber } from './posHelpers';

type QzInvoicePrinter = ReturnType<typeof createQzInvoicePrinter>;

const POSPage = () => {
  const currentBranchId = String(userInformation().currentBranchId ?? '');
  const [activeCategory, setActiveCategory] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [enforceStockCheck, setEnforceStockCheck] = useState(true);

  const qzInvoicePrinterRef = useRef<QzInvoicePrinter>(createQzInvoicePrinter());

  const {
    loading,
    loadError,
    categories,
    menuData,
    paymentMethods,
    selectedPaymentMethodId,
    setSelectedPaymentMethodId,
    warehouses,
    selectedWarehouseId,
    setSelectedWarehouseId,
  } = usePosCatalogData({ currentBranchId });

  const customersState = usePosCustomers();
  const filteredMenu = menuData.filter((item) => {
    const matchesCategory = !activeCategory || item.category.id === activeCategory;
    if (!matchesCategory) {
      return false;
    }

    const query = normalizeText(searchQuery);
    if (!query) {
      return true;
    }

    const fields = [item.name, item.sku ?? '', item.barcode ?? ''];
    return fields.some((value) => normalizeText(value).includes(query));
  });

  const selectedPaymentMethod =
    paymentMethods.find((method) => method.id === selectedPaymentMethodId) ?? null;
  const isCashPaymentSelected = isCashPaymentMethod(selectedPaymentMethod);

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
    selectedWarehouseId,
    selectedCustomerId: customersState.selectedCustomerId,
    selectedPaymentMethodId,
    isCashPaymentSelected,
    enforceStockCheck,
    visibleProducts: filteredMenu,
    printSaleInvoice: (saleId: string) => qzInvoicePrinterRef.current.printSaleInvoice(saleId),
  });
  const selectedCategoryLabel =
    activeCategory === null ? 'Todos' : categories.find((category) => category.id === activeCategory)?.name;

  const formatMoney = (value: number) => NumberUtils.formatCurrency(Number(toNumber(value).toFixed(2)));

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

  return (
    <div className="pos-shell">
      <POSCatalogPanel
        loading={loading}
        loadError={loadError}
        searchQuery={searchQuery}
        setSearchQuery={setSearchQuery}
        cartItemsCount={cartItemsCount}
        selectedCategoryLabel={selectedCategoryLabel}
        filteredMenu={filteredMenu}
        lastSaleCode={lastSaleCode}
        categories={categories}
        activeCategory={activeCategory}
        setActiveCategory={setActiveCategory}
        onAddToCart={addToCart}
        onRemoveFromCart={removeFromCart}
        getItemQuantity={(id) => cart.find((cartItem) => cartItem.id === id)?.quantity || 0}
        getEffectiveStock={getEffectiveStock}
        enforceStockCheck={enforceStockCheck}
      />

      <POSCheckoutPanel
        cart={cart}
        cartItemsCount={cartItemsCount}
        clearCart={clearCart}
        warehouses={warehouses}
        selectedWarehouseId={selectedWarehouseId}
        setSelectedWarehouseId={setSelectedWarehouseId}
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
      />
    </div>
  );
};

export default POSPage;

import { useEffect, useState } from 'react';
import toast, { getIRI } from '@shared/lib/SharedUtils';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import type { CartItem, PosItem } from './types';
import { IGV_RATE, nowUtcIso, todayBusinessDate, toNumber } from './posHelpers';
import { useWarehouseStock } from './useWarehouseStock';
import { createLocalSaleAndEnqueue } from '@offline/outbox';
import { offlineDb } from '@offline/db';
import { useCurrencySettings } from '@shared/hooks/useCurrencySettings';

const getActionableSaleErrorMessage = (sale: { errorCode?: string; errorMessage?: string }) => {
  if (sale.errorCode === 'SALE_CASH_SESSION_REQUIRED') {
    return 'Caja cerrada: abre una caja antes de registrar ventas.';
  }

  return sale.errorMessage ?? 'La venta fue rechazada por el servidor.';
};

type UsePosCheckoutParams = {
  tenantId: string;
  storeId: string;
  selectedWarehouseId: string;
  selectedCustomerId: number | null;
  selectedPaymentMethodId: number | null;
  selectedPaymentMethodName?: string;
  isCashPaymentSelected: boolean;
  enforceStockCheck: boolean;
  visibleProducts: PosItem[];
  isOnline: boolean;
  onQueuedSaleCreated: () => Promise<void>;
  printSaleInvoice: (saleId: string) => Promise<void>;
  onCashSessionRequired?: () => void;
};

export const usePosCheckout = ({
  tenantId,
  storeId,
  selectedWarehouseId,
  selectedCustomerId,
  selectedPaymentMethodId,
  selectedPaymentMethodName,
  isCashPaymentSelected,
  enforceStockCheck,
  visibleProducts,
  isOnline,
  onQueuedSaleCreated,
  printSaleInvoice,
  onCashSessionRequired,
}: UsePosCheckoutParams) => {
  // ── Draft-persistence helpers ────────────────────────────────────────────
  const storageKey = `pos_draft_${storeId || 'default'}`;

  function readDraft() {
    try {
      const raw = localStorage.getItem(storageKey);
      if (!raw) return null;
      return JSON.parse(raw) as { cart: CartItem[]; note: string; discountPercent: number };
    } catch {
      return null;
    }
  }

  const draft = readDraft();

  const [cart, setCart] = useState<CartItem[]>(() => draft?.cart ?? []);
  const [amountReceived, setAmountReceived] = useState('');
  const [discountPercent, setDiscountPercent] = useState(() => draft?.discountPercent ?? 0);
  const [note, setNote] = useState(() => draft?.note ?? '');
  const [isProcessingSale, setIsProcessingSale] = useState(false);
  const [lastSaleCode, setLastSaleCode] = useState<string | null>(null);
  const [emitInvoice, setEmitInvoice] = useState(false);
  const { currency, exchangeRate, setCurrency, setExchangeRate } = useCurrencySettings();

  // Persist cart draft on every change (strip photo to keep storage lean)
  useEffect(() => {
    if (cart.length === 0 && !note && discountPercent === 0) {
      localStorage.removeItem(storageKey);
      return;
    }
    const payload = {
      cart: cart.map(({ photo: _photo, ...rest }) => rest),
      note,
      discountPercent,
    };
    try {
      localStorage.setItem(storageKey, JSON.stringify(payload));
    } catch {
      // Safari private / quota — silently ignore
    }
  }, [cart, note, discountPercent, storageKey]);

  const { fetchWarehouseStock, getEffectiveStock } = useWarehouseStock(
    selectedWarehouseId,
    cart,
    visibleProducts,
  );

  const addToCart = async (item: PosItem) => {
    const tracksInventory = item.trackInventory !== false;
    const warehouseStock = selectedWarehouseId
      ? await fetchWarehouseStock(item.id, selectedWarehouseId)
      : undefined;
    const effectiveStock = warehouseStock ?? item.stock;

    if (
      tracksInventory &&
      enforceStockCheck &&
      typeof effectiveStock === 'number' &&
      effectiveStock <= 0
    ) {
      toast(`"${item.name}" sin stock disponible`, 'warning');
      return;
    }

    setCart((prevCart) => {
      const existingItem = prevCart.find((cartItem) => cartItem.id === item.id);
      if (existingItem) {
        if (
          tracksInventory &&
          enforceStockCheck &&
          typeof effectiveStock === 'number' &&
          existingItem.quantity >= effectiveStock
        ) {
          toast(`Stock máximo alcanzado para "${item.name}"`, 'warning');
          return prevCart;
        }

        return prevCart.map((cartItem) =>
          cartItem.id === item.id ? { ...cartItem, quantity: cartItem.quantity + 1 } : cartItem,
        );
      }

      return [...prevCart, { ...item, stock: effectiveStock, quantity: 1 }];
    });
  };

  const removeFromCart = (id: string) => {
    setCart((prevCart) =>
      prevCart
        .map((cartItem) =>
          cartItem.id === id ? { ...cartItem, quantity: cartItem.quantity - 1 } : cartItem,
        )
        .filter((cartItem) => cartItem.quantity > 0),
    );
  };

  const updateQuantity = async (id: string, nextQuantity: number) => {
    if (nextQuantity <= 0) {
      removeFromCart(id);
      return;
    }

    const currentItem = cart.find((item) => item.id === id);
    if (!currentItem) {
      return;
    }

    const warehouseStock = selectedWarehouseId
      ? await fetchWarehouseStock(id, selectedWarehouseId)
      : undefined;
    const effectiveStock = warehouseStock ?? currentItem.stock;
    const tracksInventory = currentItem.trackInventory !== false;

    setCart((prevCart) =>
      prevCart.map((item) => {
        if (item.id !== id) {
          return item;
        }

        if (tracksInventory && enforceStockCheck && typeof effectiveStock === 'number') {
          const quantity = Math.min(nextQuantity, effectiveStock);
          if (quantity < nextQuantity) {
            toast(`Stock máximo alcanzado para "${item.name}"`, 'warning');
          }
          return { ...item, stock: effectiveStock, quantity };
        }

        return { ...item, stock: effectiveStock, quantity: nextQuantity };
      }),
    );
  };

  const clearCart = () => {
    setCart([]);
    setAmountReceived('');
    setDiscountPercent(0);
    setNote('');
    localStorage.removeItem(storageKey);
  };

  const cartItemsCount = cart.reduce((sum, item) => sum + item.quantity, 0);
  const subtotal = cart.reduce((sum, item) => sum + toNumber(item.salePrice) * item.quantity, 0);
  const discountAmount = subtotal * (discountPercent / 100);
  const total = Math.max(0, subtotal - discountAmount);
  const includedTax = total * (IGV_RATE / (1 + IGV_RATE));
  const amountReceivedNumber = isCashPaymentSelected
    ? Number.parseFloat(amountReceived || '0') || 0
    : total;
  const changeAmount = isCashPaymentSelected ? Math.max(0, amountReceivedNumber - total) : 0;
  const amountMissing = isCashPaymentSelected ? Math.max(0, total - amountReceivedNumber) : 0;
  const quickCashActions = [
    { label: 'Exacto', value: total },
    { label: '+ 5', value: total + 5 },
    { label: '+ 10', value: total + 10 },
    { label: '+ 20', value: total + 20 },
  ];

  const checkoutDisabled =
    cart.length === 0 ||
    isProcessingSale ||
    !selectedWarehouseId ||
    !selectedPaymentMethodId ||
    (isCashPaymentSelected && amountReceivedNumber < total);

  const handleCheckout = async () => {
    if (checkoutDisabled) {
      if (cart.length === 0) {
        toast('Agrega productos al carrito', 'warning');
        return;
      }
      if (!selectedWarehouseId) {
        toast('Selecciona un almacén', 'warning');
        return;
      }
      if (!selectedPaymentMethodId) {
        toast('Selecciona un método de pago', 'warning');
        return;
      }
      if (isCashPaymentSelected && amountReceivedNumber < total) {
        toast('El monto recibido es menor al total', 'warning');
      }
      return;
    }

    setIsProcessingSale(true);

    try {
      const salePayload = {
        issuedAt: nowUtcIso(),
        issuedDate: todayBusinessDate(),
        warehouse: getIRI(`${API}warehouses`, selectedWarehouseId),
        customer: selectedCustomerId
          ? getIRI(`${API}customers`, String(selectedCustomerId))
          : null,
        paymentMethod: getIRI(
          `${API}payment-methods`,
          String(selectedPaymentMethodId),
        ),
        payments: [
          {
            payment_method_id: Number(selectedPaymentMethodId),
            amount: total,
            tendered_amount: isCashPaymentSelected ? amountReceivedNumber : total,
            change_amount: isCashPaymentSelected ? changeAmount : 0,
          },
        ],
        note: note.trim() || null,
        discountPercent: discountPercent.toFixed(2),
        emitInvoice,
        currency,
        exchangeRate: currency === 'USD' ? exchangeRate : '1.000000',
        grandTotal: total.toFixed(2),
        writeItems: cart.map((item) => ({
          product: getIRI(`${API}products`, String(item.id)),
          qty: Number(item.quantity),
          unitPrice: toNumber(item.salePrice).toFixed(2),
          discountAmount: '0.00',
          netAmount: (toNumber(item.salePrice) * item.quantity).toFixed(2),
        })),
      };

      const { sale } = await createLocalSaleAndEnqueue({
        tenantId,
        storeId,
        warehouseId: selectedWarehouseId,
        endpointUrl: `${API}sales`,
        requestBody: salePayload,
        items: cart.map((item) => ({
          productId: item.id,
          name: item.name,
          quantity: item.quantity,
          unitPrice: toNumber(item.salePrice),
          lineTotal: toNumber(item.salePrice) * item.quantity,
        })),
        payments: [
          {
            methodId: Number(selectedPaymentMethodId),
            methodName: selectedPaymentMethodName,
            amount: total,
            amountReceived: isCashPaymentSelected ? amountReceivedNumber : total,
            changeAmount: isCashPaymentSelected ? changeAmount : 0,
          },
        ],
        totals: {
          subtotal,
          igv: includedTax,
          discount: discountAmount,
          total,
        },
      });

      if (!isOnline) {
        toast('Venta guardada localmente', 'success');
        toast('Pendiente de sincronización', 'warning');
        setLastSaleCode(sale.correlativoTemporal);
        clearCart();
        await onQueuedSaleCreated();
        return;
      }

      setLastSaleCode(sale.correlativoTemporal);
      await onQueuedSaleCreated();
      const syncedSale = await offlineDb.sales.get(sale.id);
      if (syncedSale?.serverSaleId) {
        toast(`Venta registrada (${syncedSale.serverCode ?? sale.correlativoTemporal})`, 'success');
        clearCart();
        printSaleInvoice(syncedSale.serverSaleId).catch(() => {
          toast('Venta sincronizada, pero no se pudo imprimir el ticket.', 'warning');
        });
      } else if (syncedSale?.status === 'error') {
        if (syncedSale.errorCode === 'SALE_CASH_SESSION_REQUIRED') {
          onCashSessionRequired?.();
        }
        toast(getActionableSaleErrorMessage(syncedSale), 'error', 6000);
      } else {
        toast(`Venta registrada localmente (${sale.correlativoTemporal})`, 'success');
        clearCart();
      }
    } finally {
      setIsProcessingSale(false);
    }
  };

  return {
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
  };
};

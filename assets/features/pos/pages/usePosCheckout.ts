import { useState } from 'react';
import toast from '@shared/lib/SharedUtils';
import { getIRI } from '@shared/lib/SharedUtils';
import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@platform/common/Constants';
import type { CartItem, PosItem } from './types';
import { IGV_RATE, toLocalIsoString, toNumber } from './posHelpers';
import { useWarehouseStock } from './useWarehouseStock';

type UsePosCheckoutParams = {
  selectedWarehouseId: string;
  selectedCustomerId: number | null;
  selectedPaymentMethodId: number | null;
  isCashPaymentSelected: boolean;
  enforceStockCheck: boolean;
  visibleProducts: PosItem[];
  printSaleInvoice: (saleId: string) => Promise<void>;
};

export const usePosCheckout = ({
  selectedWarehouseId,
  selectedCustomerId,
  selectedPaymentMethodId,
  isCashPaymentSelected,
  enforceStockCheck,
  visibleProducts,
  printSaleInvoice,
}: UsePosCheckoutParams) => {
  const [cart, setCart] = useState<CartItem[]>([]);
  const [amountReceived, setAmountReceived] = useState('');
  const [discountPercent, setDiscountPercent] = useState(0);
  const [note, setNote] = useState('');
  const [isProcessingSale, setIsProcessingSale] = useState(false);
  const [lastSaleCode, setLastSaleCode] = useState<string | null>(null);
  const [emitInvoice, setEmitInvoice] = useState(false);

  const { fetchWarehouseStock, getEffectiveStock } = useWarehouseStock(
    selectedWarehouseId,
    cart,
    visibleProducts,
  );

  const addToCart = async (item: PosItem) => {
    const warehouseStock = selectedWarehouseId ? await fetchWarehouseStock(item.id, selectedWarehouseId) : undefined;
    const effectiveStock = warehouseStock ?? item.stock;

    if (enforceStockCheck && typeof effectiveStock === 'number' && effectiveStock <= 0) {
      toast(`"${item.name}" sin stock disponible`, 'warning');
      return;
    }

    setCart((prevCart) => {
      const existingItem = prevCart.find((cartItem) => cartItem.id === item.id);
      if (existingItem) {
        if (
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
        .map((cartItem) => (cartItem.id === id ? { ...cartItem, quantity: cartItem.quantity - 1 } : cartItem))
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

    const warehouseStock = selectedWarehouseId ? await fetchWarehouseStock(id, selectedWarehouseId) : undefined;
    const effectiveStock = warehouseStock ?? currentItem.stock;

    setCart((prevCart) =>
      prevCart.map((item) => {
        if (item.id !== id) {
          return item;
        }

        if (enforceStockCheck && typeof effectiveStock === 'number') {
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
  };

  const cartItemsCount = cart.reduce((sum, item) => sum + item.quantity, 0);
  const subtotal = cart.reduce((sum, item) => sum + toNumber(item.salePrice) * item.quantity, 0);
  const discountAmount = subtotal * (discountPercent / 100);
  const total = Math.max(0, subtotal - discountAmount);
  const includedTax = total * (IGV_RATE / (1 + IGV_RATE));
  const amountReceivedNumber = isCashPaymentSelected ? Number.parseFloat(amountReceived || '0') || 0 : total;
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
    !selectedCustomerId ||
    !selectedPaymentMethodId ||
    (isCashPaymentSelected && amountReceivedNumber < total);

  const handleCheckout = () => {
    if (checkoutDisabled) {
      if (cart.length === 0) {
        toast('Agrega productos al carrito', 'warning');
        return;
      }
      if (!selectedWarehouseId) {
        toast('Selecciona un almacen', 'warning');
        return;
      }
      if (!selectedCustomerId) {
        toast('Selecciona un cliente', 'warning');
        return;
      }
      if (!selectedPaymentMethodId) {
        toast('Selecciona un metodo de pago', 'warning');
        return;
      }
      if (isCashPaymentSelected && amountReceivedNumber < total) {
        toast('El monto recibido es menor al total', 'warning');
      }
      return;
    }

    setIsProcessingSale(true);

    const salePayload = {
      fecha: toLocalIsoString(new Date()),
      almacen: getIRI(`${Constants.API_BASE_URL}warehouses`, selectedWarehouseId),
      cliente: getIRI(`${Constants.API_BASE_URL}customers`, String(selectedCustomerId)),
      tipo_pago: getIRI(`${Constants.API_BASE_URL}payment-methods`, String(selectedPaymentMethodId)),
      glosa: note.trim() || null,
      descuento: discountPercent.toFixed(2),
      emit_invoice: emitInvoice,
      items: cart.map((item) => ({
        producto: getIRI(`${Constants.API_BASE_URL}products`, String(item.id)),
        cantidad: Number(item.quantity),
        precio_unitario: toNumber(item.salePrice).toFixed(2),
        descuento: '0.00',
        precio_total: (toNumber(item.salePrice) * item.quantity).toFixed(2),
      })),
    };

    httpClient
      .post<any>(`${Constants.API_BASE_URL}movements`, salePayload)
      .subscribe({
        next: (response) => {
          const created = response.data;
          const saleCode =
            [created?.serie, created?.numero].filter(Boolean).join('-') ||
            created?.invoice ||
            created?.id ||
            `POS-${Date.now()}-${Math.floor(Math.random() * 9000 + 1000)}`;

          setLastSaleCode(String(saleCode));
          toast(`Venta registrada (${saleCode})`, 'success');
          clearCart();

          if (created?.id) {
            printSaleInvoice(String(created.id)).catch(() => {
              toast('Venta registrada, pero no se pudo imprimir el ticket.', 'warning');
            });
          }
        },
      })
      .add(() => {
        setIsProcessingSale(false);
      });
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

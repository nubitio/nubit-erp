import { useCallback, useEffect, useState } from 'react';
import toast, { getIRI } from '@shared/lib/SharedUtils';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import type { CartItem, PaymentMethodOption, PosItem } from './types';
import { isCashPaymentMethod, isWeightUnitCode, nowUtcIso, todayBusinessDate, toNumber } from './posHelpers';
import {
  buildPaymentPayload,
  createSinglePaymentLine,
  rebalancePaymentLines,
  totalCashChangeAmount,
  type SalePaymentLineDraft,
  validatePaymentLines,
} from '@shared/lib/salePayments';
import {
  fetchScaleStatus,
  LocalAgentNotAvailableError,
  waitForStableWeight,
} from '@features/sales/lib/localAgentClient';
import { computeCartTotals } from '@shared/lib/saleCalculations';
import { useTenantIgvRate } from '@shared/hooks/useTenantIgvRate';
import { useWarehouseStock } from './useWarehouseStock';
import { offlineDb } from '@offline/db';
import { useCurrencySettings } from '@shared/hooks/useCurrencySettings';
import { submitSale } from './saleSubmitter';

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
  paymentMethods: PaymentMethodOption[];
  enforceStockCheck: boolean;
  warnOnNegativeStock?: boolean;
  visibleProducts: PosItem[];
  isOnline: boolean;
  useWarehouseStockChecks?: boolean;
  onQueuedSaleCreated: () => Promise<void>;
  printSaleInvoice: (saleId: string) => Promise<void>;
  onCashSessionRequired?: () => void;
};

export const usePosCheckout = ({
  tenantId,
  storeId,
  selectedWarehouseId,
  selectedCustomerId,
  paymentMethods,
  enforceStockCheck,
  warnOnNegativeStock = false,
  visibleProducts,
  isOnline,
  useWarehouseStockChecks = true,
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
  const [paymentLines, setPaymentLines] = useState<SalePaymentLineDraft[]>([]);
  const [discountPercent, setDiscountPercent] = useState(() => draft?.discountPercent ?? 0);
  const [note, setNote] = useState(() => draft?.note ?? '');
  const [isProcessingSale, setIsProcessingSale] = useState(false);
  const [lastCheckoutSucceeded, setLastCheckoutSucceeded] = useState(false);
  const [lastSaleCode, setLastSaleCode] = useState<string | null>(null);
  const [emitInvoice, setEmitInvoice] = useState(false);
  const { currency, exchangeRate, setCurrency, setExchangeRate } = useCurrencySettings();
  const { igvRate } = useTenantIgvRate();
  const [scaleReady, setScaleReady] = useState(false);

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
    useWarehouseStockChecks,
  );

  useEffect(() => {
    let cancelled = false;
    fetchScaleStatus()
      .then((status) => {
        if (!cancelled) {
          setScaleReady(status.enabled && status.connected);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setScaleReady(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const readScaleQuantity = async (): Promise<number | null> => {
    if (!scaleReady) {
      toast('Balanza no configurada. Ingresa el peso manualmente o configúrala en Hardware local.', 'warning');
      return null;
    }

    try {
      toast('Coloque el producto en la balanza…', 'info');
      return await waitForStableWeight({ timeoutMs: 4_000, pollMs: 300 });
    } catch (err) {
      if (err instanceof LocalAgentNotAvailableError) {
        toast('Agente de hardware no disponible. Configure el cableado en Hardware local.', 'warning');
      } else {
        toast('No se obtuvo peso estable. Use el botón de balanza o ingrese manualmente.', 'warning');
      }
      return null;
    }
  };

  const syncCartPricesFromCatalog = useCallback(() => {
    setCart((previousCart) => {
      if (previousCart.length === 0) {
        return previousCart;
      }

      let changed = false;
      const nextCart = previousCart.map((cartItem) => {
        const catalogItem = visibleProducts.find((product) => product.id === cartItem.id);
        if (!catalogItem) {
          return cartItem;
        }

        const nextPrice = toNumber(catalogItem.salePrice);
        if (toNumber(cartItem.salePrice) === nextPrice) {
          return cartItem;
        }

        changed = true;
        return {
          ...cartItem,
          salePrice: nextPrice,
        };
      });

      return changed ? nextCart : previousCart;
    });
  }, [visibleProducts]);

  const addToCart = async (item: PosItem) => {
    const tracksInventory = item.trackInventory !== false;
    const warehouseStock = useWarehouseStockChecks && selectedWarehouseId
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

    if (
      tracksInventory &&
      warnOnNegativeStock &&
      typeof effectiveStock === 'number' &&
      effectiveStock <= 0
    ) {
      toast(`"${item.name}" quedará con stock negativo`, 'warning');
    }

    const isWeighted = isWeightUnitCode(item.saleUnitCode);
    let initialQuantity = 1;
    if (isWeighted) {
      initialQuantity = 0.1;
    }

    setCart((prevCart) => {
      const existingItem = prevCart.find((cartItem) => cartItem.id === item.id);
      if (existingItem) {
        if (isWeighted) {
          return prevCart.map((cartItem) =>
            cartItem.id === item.id
              ? {
                  ...cartItem,
                  saleUnitCode: cartItem.saleUnitCode ?? item.saleUnitCode,
                  quantity: initialQuantity,
                }
              : cartItem,
          );
        }

        if (
          tracksInventory &&
          enforceStockCheck &&
          typeof effectiveStock === 'number' &&
          existingItem.quantity >= effectiveStock
        ) {
          toast(`Stock máximo alcanzado para "${item.name}"`, 'warning');
          return prevCart;
        }

        if (
          tracksInventory &&
          warnOnNegativeStock &&
          typeof effectiveStock === 'number' &&
          existingItem.quantity >= effectiveStock
        ) {
          toast(`"${item.name}" superará el stock disponible`, 'warning');
        }

        return prevCart.map((cartItem) =>
          cartItem.id === item.id
            ? {
                ...cartItem,
                saleUnitCode: cartItem.saleUnitCode ?? item.saleUnitCode,
                quantity: cartItem.quantity + 1,
              }
            : cartItem,
        );
      }

      return [...prevCart, { ...item, stock: effectiveStock, quantity: initialQuantity }];
    });

    if (isWeighted && scaleReady) {
      void readScaleQuantity().then((weight) => {
        if (weight == null) return;
        setCart((prevCart) =>
          prevCart.map((cartItem) =>
            cartItem.id === item.id
              ? {
                  ...cartItem,
                  saleUnitCode: cartItem.saleUnitCode ?? item.saleUnitCode,
                  quantity: Number(weight.toFixed(3)),
                }
              : cartItem,
          ),
        );
      });
    }
  };

  const readScaleForLine = async (id: string) => {
    const weight = await readScaleQuantity();
    if (weight == null) return;
    await updateQuantity(id, Number(weight.toFixed(3)));
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
    const normalizedNextQuantity = Number(nextQuantity.toFixed(3));

    if (normalizedNextQuantity <= 0) {
      removeFromCart(id);
      return;
    }

    const currentItem = cart.find((item) => item.id === id);
    if (!currentItem) {
      return;
    }

    const warehouseStock = useWarehouseStockChecks && selectedWarehouseId
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
          const quantity = Math.min(normalizedNextQuantity, effectiveStock);
          if (quantity < normalizedNextQuantity) {
            toast(`Stock máximo alcanzado para "${item.name}"`, 'warning');
          }
          return { ...item, stock: effectiveStock, quantity };
        }

        if (
          tracksInventory &&
          warnOnNegativeStock &&
          typeof effectiveStock === 'number' &&
          normalizedNextQuantity > effectiveStock
        ) {
          toast(`"${item.name}" superará el stock disponible`, 'warning');
        }

        return { ...item, stock: effectiveStock, quantity: normalizedNextQuantity };
      }),
    );
  };

  const updateLineDescription = (id: string, lineDescription: string) => {
    setCart((prevCart) =>
      prevCart.map((item) => (item.id === id ? { ...item, lineDescription } : item)),
    );
  };

  const updateLinePrice = (id: string, nextPrice: number) => {
    const normalizedPrice = Number(nextPrice.toFixed(2));
    if (!Number.isFinite(normalizedPrice) || normalizedPrice < 0) {
      toast('El precio debe ser mayor o igual a cero', 'warning');
      return;
    }

    setCart((prevCart) =>
      prevCart.map((item) =>
        item.id === id ? { ...item, salePrice: normalizedPrice } : item,
      ),
    );
  };

  const clearCart = () => {
    setCart([]);
    setDiscountPercent(0);
    setNote('');
    localStorage.removeItem(storageKey);
  };

  const cartItemsCount = cart.reduce((sum, item) => sum + item.quantity, 0);
  const { subtotal, discountAmount, total, includedTax, gravadoBase, exoneradoBase, inafectoBase } =
    computeCartTotals(
      cart.map((item) => ({
        price: toNumber(item.salePrice),
        quantity: item.quantity,
        taxType: item.taxType,
      })),
      discountPercent,
      igvRate,
    );
  useEffect(() => {
    if (paymentMethods.length === 0 || total <= 0) {
      return;
    }

    setPaymentLines((previousLines) => {
      if (previousLines.length === 0) {
        const defaultMethod =
          paymentMethods.find((method) => isCashPaymentMethod(method)) ?? paymentMethods[0];
        return [createSinglePaymentLine(defaultMethod.id, total)];
      }

      if (previousLines.length === 1) {
        return [createSinglePaymentLine(previousLines[0].methodId, total)];
      }

      return rebalancePaymentLines(previousLines, total);
    });
  }, [paymentMethods, total]);

  const paymentValidation = validatePaymentLines(paymentLines, paymentMethods, total);
  const resolvedPayments = buildPaymentPayload(paymentLines, paymentMethods, total);
  const primaryPaymentMethodId = paymentLines[0]?.methodId ?? null;
  const changeAmount = totalCashChangeAmount(paymentLines, paymentMethods, total);

  const checkoutDisabled =
    cart.length === 0 ||
    isProcessingSale ||
    !selectedWarehouseId ||
    total <= 0 ||
    !paymentValidation.valid;

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
      if (!paymentValidation.valid) {
        toast(paymentValidation.message, 'warning');
      }
      return;
    }

    setIsProcessingSale(true);
    setLastCheckoutSucceeded(false);

    try {
      const salePayload = {
        issuedAt: nowUtcIso(),
        issuedDate: todayBusinessDate(),
        warehouse: getIRI(`${API}warehouses`, selectedWarehouseId),
        customer: selectedCustomerId ? getIRI(`${API}customers`, String(selectedCustomerId)) : null,
        paymentMethod: primaryPaymentMethodId
          ? getIRI(`${API}payment-methods`, String(primaryPaymentMethodId))
          : null,
        payments: resolvedPayments,
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
          description: item.lineDescription?.trim() || item.name,
          discountAmount: '0.00',
          netAmount: (toNumber(item.salePrice) * item.quantity).toFixed(2),
        })),
      };

      const submitResult = await submitSale({
        tenantId,
        storeId,
        warehouseId: selectedWarehouseId,
        endpointUrl: `${API}sales`,
        requestBody: salePayload,
        items: cart.map((item) => ({
          productId: item.id,
          name: item.lineDescription?.trim() || item.name,
          quantity: item.quantity,
          unitPrice: toNumber(item.salePrice),
          lineTotal: toNumber(item.salePrice) * item.quantity,
        })),
        payments: resolvedPayments.map((payment) => {
          const method = paymentMethods.find((item) => item.id === payment.payment_method_id);
          return {
            methodId: payment.payment_method_id,
            methodName: method?.name,
            amount: Number.parseFloat(payment.amount),
            amountReceived: payment.tendered_amount
              ? Number.parseFloat(payment.tendered_amount)
              : Number.parseFloat(payment.amount),
            changeAmount: payment.change_amount
              ? Number.parseFloat(payment.change_amount)
              : 0,
          };
        }),
        totals: {
          subtotal,
          igv: includedTax,
          discount: discountAmount,
          total,
        },
        isOnline,
      });

      if (submitResult.mode === 'server') {
        toast(`Venta registrada (${submitResult.serverCode})`, 'success');
        setLastSaleCode(submitResult.serverSaleId);
        setLastCheckoutSucceeded(true);
        clearCart();
        printSaleInvoice(submitResult.serverSaleId).catch(() => {
          toast('Venta registrada, pero no se pudo imprimir el ticket.', 'warning');
        });
        return;
      }

      const { sale } = submitResult;

      if (!isOnline) {
        toast('Venta guardada localmente', 'success');
        toast('Pendiente de sincronización', 'warning');
        setLastSaleCode(sale.correlativoTemporal);
        setLastCheckoutSucceeded(true);
        clearCart();
        await onQueuedSaleCreated();
        return;
      }

      setLastSaleCode(sale.correlativoTemporal);
      await onQueuedSaleCreated();
      const syncedSale = await offlineDb.sales.get(sale.id);
      if (syncedSale?.serverSaleId) {
        toast(`Venta registrada (${syncedSale.serverCode ?? sale.correlativoTemporal})`, 'success');
        setLastCheckoutSucceeded(true);
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
        setLastCheckoutSucceeded(true);
        clearCart();
      }
    } catch {
      // HttpClient already displayed an error toast for HTTP error responses.
      // We only need to ensure isProcessingSale resets (handled by finally).
    } finally {
      setIsProcessingSale(false);
    }
  };

  return {
    cart,
    paymentLines,
    setPaymentLines,
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
    updateLinePrice,
    updateLineDescription,
    clearCart,
    cartItemsCount,
    subtotal,
    discountAmount,
    total,
    includedTax,
    gravadoBase,
    exoneradoBase,
    inafectoBase,
    /** Active IGV rate (decimal fraction) used for display. */
    igvRate,
    changeAmount,
    checkoutDisabled,
    handleCheckout,
    lastCheckoutSucceeded,
    getEffectiveStock,
    readScaleForLine,
    scaleReady,
    syncCartPricesFromCatalog,
  };
};

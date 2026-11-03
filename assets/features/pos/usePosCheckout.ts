/* eslint-disable react-hooks/set-state-in-effect -- checkout resets dependent payment and customer state */
import { useCallback, useEffect, useState } from 'react';
import toast, { getIRI } from '@shared/lib/SharedUtils';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import type { CartItem, CustomerOption, PaymentMethodOption, PosItem } from './types';
import {
  isCashPaymentMethod,
  isWeightUnitCode,
  nowUtcIso,
  todayBusinessDate,
  toMoneyString,
  toNumber,
} from './posHelpers';
import {
  buildPaymentPayload,
  buildSettlementPayload,
  creditPaymentAmount,
  createSinglePaymentLine,
  hasCreditPayment,
  rebalancePaymentLines,
  totalCashChangeAmount,
  type SalePaymentLineDraft,
  validatePaymentLines,
} from '@shared/lib/salePayments';
import {
  LocalAgentNotAvailableError,
  waitForStableWeight,
} from '@features/sales/lib/localAgentClient';
import { computeCartTotals, computeLineAmount } from '@shared/lib/saleCalculations';
import { cashRoundingAdjustment } from '@shared/lib/cashRounding';
import { useTenantIgvRate } from '@shared/hooks/useTenantIgvRate';
import { useWarehouseStock } from './useWarehouseStock';
import { offlineDb } from '@offline/db';
import { useCurrencySettings } from '@shared/hooks/useCurrencySettings';
import { generateIdempotencyKey, submitSale } from './saleSubmitter';
import { publicSaleCode } from './posPresentation';
import { useQueryClient } from '@tanstack/react-query';
import { runCriticalOperation, useAppUpdate } from '@offline/appUpdate';
import { useScaleReady } from './useScaleReady';
import { Setting, settings } from '@shared/settings';

let posCheckoutInFlight = false;
let posCheckoutAttemptKey: string | null = null;

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
  selectedCustomer: CustomerOption | null;
  paymentMethods: PaymentMethodOption[];
  enforceStockCheck: boolean;
  warnOnNegativeStock?: boolean;
  visibleProducts: PosItem[];
  isOnline: boolean;
  useWarehouseStockChecks?: boolean;
  onQueuedSaleCreated: () => Promise<void>;
  printSaleInvoice: (saleId: string) => Promise<void>;
  onCashSessionRequired?: () => void;
  autoInvoice?: boolean;
  autoPrint?: boolean;
  cashRoundingEnabled?: boolean;
  /** Clears state owned by the POS page (for example the selected customer). */
  onSaleCompleted?: () => void;
};

export const usePosCheckout = ({
  tenantId,
  storeId,
  selectedWarehouseId,
  selectedCustomerId,
  selectedCustomer,
  paymentMethods,
  enforceStockCheck,
  warnOnNegativeStock = false,
  visibleProducts,
  isOnline,
  useWarehouseStockChecks = true,
  onQueuedSaleCreated,
  printSaleInvoice,
  onCashSessionRequired,
  autoInvoice = true,
  autoPrint = true,
  cashRoundingEnabled = false,
  onSaleCompleted,
}: UsePosCheckoutParams) => {
  const queryClient = useQueryClient();
  const appUpdate = useAppUpdate();
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
  const [lastSaleDocumentType, setLastSaleDocumentType] = useState<string | null>(null);
  const [lastSaleTotal, setLastSaleTotal] = useState<number | null>(null);
  const [lastSaleId, setLastSaleId] = useState<string | null>(null);
  const [lastSaleReceiptUrl, setLastSaleReceiptUrl] = useState<string | null>(null);
  const [emitInvoice, setEmitInvoice] = useState(autoInvoice);
  const [requestedVoucherType, setRequestedVoucherType] = useState<'AUTO' | 'FACTURA' | 'BOLETA'>(
    'AUTO',
  );
  const [checkoutError, setCheckoutError] = useState<string | null>(null);

  useEffect(() => {
    setEmitInvoice(autoInvoice);
  }, [autoInvoice]);
  const { currency, exchangeRate, setCurrency, setExchangeRate } = useCurrencySettings();
  const { igvRate, taxRegime } = useTenantIgvRate();
  const scaleReady = useScaleReady();

  /** Factura is only legal when the customer has an 11-digit RUC and the tenant isn't NRUS. */
  const canRequestFactura = taxRegime !== 'NRUS' && (selectedCustomer?.doc?.length ?? 0) === 11;

  useEffect(() => {
    if (!canRequestFactura) {
      setRequestedVoucherType((current) => (current === 'FACTURA' ? 'AUTO' : current));
    }
  }, [canRequestFactura]);

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

  const readScaleQuantity = async (): Promise<number | null> => {
    if (!scaleReady) {
      toast(
        'Balanza no configurada. Ingresa el peso manualmente o configúrala en Hardware local.',
        'warning',
      );
      return null;
    }

    try {
      toast('Coloque el producto en la balanza…', 'info');
      return await waitForStableWeight({ timeoutMs: 4_000, pollMs: 300 });
    } catch (err) {
      if (err instanceof LocalAgentNotAvailableError) {
        toast(
          'Agente de hardware no disponible. Configure el cableado en Hardware local.',
          'warning',
        );
      } else {
        toast(
          'No se obtuvo peso estable. Use el botón de balanza o ingrese manualmente.',
          'warning',
        );
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

        const nextPrice = catalogItem.salePrice;
        if (cartItem.salePrice === nextPrice) {
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
    const warehouseStock =
      useWarehouseStockChecks && selectedWarehouseId
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

    const warehouseStock =
      useWarehouseStockChecks && selectedWarehouseId
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
    const normalizedPrice = toMoneyString(nextPrice, '');
    if (normalizedPrice === '' || nextPrice < 0) {
      toast('El precio debe ser mayor o igual a cero', 'warning');
      return;
    }

    setCart((prevCart) =>
      prevCart.map((item) => (item.id === id ? { ...item, salePrice: normalizedPrice } : item)),
    );
  };

  const clearCart = () => {
    setCart([]);
    setDiscountPercent(0);
    setNote('');
    localStorage.removeItem(storageKey);
  };

  /** Replaces the order in progress, e.g. when resuming a parked sale. */
  const restoreOrder = (order: { cart: CartItem[]; discountPercent: number; note: string }) => {
    setCart(order.cart);
    setDiscountPercent(order.discountPercent);
    setNote(order.note);
  };

  // A new sale should always start from the safest, most common tender type.
  // Keep this separate from clearCart so manually emptying a cart does not
  // unexpectedly change an in-progress payment choice.
  const resetPaymentToCash = () => {
    const defaultMethod =
      paymentMethods.find((method) => isCashPaymentMethod(method)) ?? paymentMethods[0];
    setPaymentLines(defaultMethod ? [createSinglePaymentLine(defaultMethod.id, 0)] : []);
  };

  const resetForNextSale = () => {
    clearCart();
    resetPaymentToCash();
    setEmitInvoice(autoInvoice);
    setRequestedVoucherType('AUTO');

    const configuredCurrency = settings.getCached(Setting.DEFAULT_CURRENCY)?.value;
    setCurrency(configuredCurrency === 'USD' ? 'USD' : 'PEN');
    const configuredRate = settings.getCached(Setting.DEFAULT_USD_EXCHANGE_RATE)?.value;
    if (
      (typeof configuredRate === 'string' || typeof configuredRate === 'number') &&
      Number(configuredRate) > 0
    ) {
      setExchangeRate(String(configuredRate));
    }

    onSaleCompleted?.();
  };

  const cartItemsCount = cart.reduce((sum, item) => sum + item.quantity, 0);
  const { subtotal, discountAmount, total, includedTax, gravadoBase, exoneradoBase, inafectoBase } =
    computeCartTotals(
      cart.map((item) => ({
        price: item.salePrice,
        quantity: item.quantity,
        taxType: item.taxType,
      })),
      discountPercent,
      igvRate,
    );
  const singlePaymentMethod = paymentMethods.find(
    (method) => method.id === paymentLines[0]?.methodId,
  );
  const creditSale = hasCreditPayment(paymentLines, paymentMethods);
  const creditAmount = creditPaymentAmount(paymentLines, paymentMethods);
  const hasIdentifiedCreditCustomer =
    selectedCustomerId != null && selectedCustomer?.doc !== '00000000';
  const cashRoundingAdjustmentAmount =
    cashRoundingEnabled &&
    !creditSale &&
    currency === 'PEN' &&
    paymentLines.length === 1 &&
    singlePaymentMethod !== undefined &&
    isCashPaymentMethod(singlePaymentMethod)
      ? cashRoundingAdjustment(total)
      : 0;
  const payableTotal = Number((total - cashRoundingAdjustmentAmount).toFixed(2));
  useEffect(() => {
    if (paymentMethods.length === 0 || total <= 0) {
      return;
    }

    setPaymentLines((previousLines) => {
      if (previousLines.length === 0) {
        const defaultMethod =
          paymentMethods.find((method) => isCashPaymentMethod(method)) ?? paymentMethods[0];
        return [createSinglePaymentLine(defaultMethod.id, payableTotal)];
      }

      if (previousLines.length === 1) {
        return [createSinglePaymentLine(previousLines[0].methodId, payableTotal)];
      }

      return rebalancePaymentLines(previousLines, payableTotal);
    });
  }, [paymentMethods, payableTotal, total]);

  const paymentValidation = validatePaymentLines(paymentLines, paymentMethods, payableTotal);
  const resolvedSettlements = buildSettlementPayload(paymentLines, paymentMethods, payableTotal);
  const resolvedPayments = buildPaymentPayload(paymentLines, paymentMethods, payableTotal);
  const primaryPaymentMethodId = paymentLines[0]?.methodId ?? null;
  const changeAmount = totalCashChangeAmount(paymentLines, paymentMethods, payableTotal);

  const checkoutDisabled =
    appUpdate.required ||
    appUpdate.updating ||
    cart.length === 0 ||
    isProcessingSale ||
    !selectedWarehouseId ||
    (creditSale && !hasIdentifiedCreditCustomer) ||
    total <= 0 ||
    !paymentValidation.valid;

  const performCheckout = async () => {
    if (posCheckoutInFlight) {
      return;
    }

    if (checkoutDisabled) {
      if (cart.length === 0) {
        toast('Agrega productos al carrito', 'warning');
        return;
      }
      if (!selectedWarehouseId) {
        toast('Selecciona un almacén', 'warning');
        return;
      }
      if (creditSale && !hasIdentifiedCreditCustomer) {
        toast('Selecciona un cliente para registrar la cuenta por cobrar.', 'warning');
        return;
      }
      if (!paymentValidation.valid) {
        toast(paymentValidation.message, 'warning');
      }
      return;
    }

    posCheckoutInFlight = true;
    setIsProcessingSale(true);
    setLastCheckoutSucceeded(false);
    setCheckoutError(null);
    if (posCheckoutAttemptKey === null) {
      posCheckoutAttemptKey = generateIdempotencyKey();
    }
    const idempotencyKey = posCheckoutAttemptKey;

    try {
      const salePayload = {
        issuedAt: nowUtcIso(),
        issuedDate: todayBusinessDate(),
        warehouse: getIRI(`${API}warehouses`, selectedWarehouseId),
        customer: selectedCustomerId ? getIRI(`${API}customers`, String(selectedCustomerId)) : null,
        paymentMethod: primaryPaymentMethodId
          ? getIRI(`${API}payment-methods`, String(primaryPaymentMethodId))
          : null,
        settlements: resolvedSettlements,
        note: note.trim() || null,
        discountPercent: discountPercent.toFixed(2),
        cashRoundingAdjustment: cashRoundingAdjustmentAmount.toFixed(2),
        emitInvoice,
        requestedVoucherType,
        currency,
        exchangeRate: currency === 'USD' ? exchangeRate : '1.000000',
        grandTotal: payableTotal.toFixed(2),
        writeItems: cart.map((item) => ({
          product: getIRI(`${API}products`, String(item.id)),
          qty: Number(item.quantity),
          unitPrice: item.salePrice,
          description: item.lineDescription?.trim() || item.name,
          discountAmount: '0.00',
          netAmount: computeLineAmount(item.quantity, item.salePrice, 0).netAmount.toFixed(2),
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
            changeAmount: payment.change_amount ? Number.parseFloat(payment.change_amount) : 0,
          };
        }),
        totals: {
          subtotal,
          igv: includedTax,
          discount: discountAmount,
          total: payableTotal,
        },
        isOnline,
        idempotencyKey,
      });

      if (submitResult.mode === 'server') {
        const displayCode = publicSaleCode(
          submitResult.invoiceFullNumber ?? submitResult.serverCode,
        );
        toast(displayCode ? `Venta registrada (${displayCode})` : 'Venta registrada', 'success');
        setLastSaleId(submitResult.serverSaleId);
        setLastSaleCode(displayCode);
        setLastSaleDocumentType(submitResult.documentType);
        setLastSaleTotal(total);
        setLastSaleReceiptUrl(submitResult.publicReceiptUrl);
        setLastCheckoutSucceeded(true);
        posCheckoutAttemptKey = null;
        resetForNextSale();
        void queryClient.invalidateQueries({ queryKey: ['dashboard-overview'] });
        if (autoPrint) {
          printSaleInvoice(submitResult.serverSaleId).catch(() => {
            toast('Venta registrada, pero no se pudo imprimir el ticket.', 'warning');
          });
        }
        return;
      }

      const { sale } = submitResult;

      if (!isOnline) {
        toast('Venta guardada localmente', 'success');
        toast('Pendiente de sincronización', 'warning');
        setLastSaleId(sale.id);
        setLastSaleCode(publicSaleCode(sale.correlativoTemporal));
        setLastSaleDocumentType(null);
        setLastSaleTotal(total);
        setLastSaleReceiptUrl(null);
        setLastCheckoutSucceeded(true);
        posCheckoutAttemptKey = null;
        resetForNextSale();
        await onQueuedSaleCreated();
        return;
      }

      setLastSaleId(sale.id);
      setLastSaleCode(publicSaleCode(sale.correlativoTemporal));
      setLastSaleTotal(total);
      await onQueuedSaleCreated();
      const syncedSale = await offlineDb.sales.get(sale.id);
      if (syncedSale?.serverSaleId) {
        const syncedCode = publicSaleCode(syncedSale.serverCode ?? sale.correlativoTemporal);
        toast(syncedCode ? `Venta registrada (${syncedCode})` : 'Venta registrada', 'success');
        setLastSaleId(syncedSale.serverSaleId);
        setLastSaleCode(syncedCode);
        setLastSaleTotal(total);
        setLastCheckoutSucceeded(true);
        posCheckoutAttemptKey = null;
        resetForNextSale();
        void queryClient.invalidateQueries({ queryKey: ['dashboard-overview'] });
        if (autoPrint) {
          printSaleInvoice(syncedSale.serverSaleId).catch(() => {
            toast('Venta sincronizada, pero no se pudo imprimir el ticket.', 'warning');
          });
        }
      } else if (syncedSale?.status === 'error') {
        if (syncedSale.errorCode === 'SALE_CASH_SESSION_REQUIRED') {
          onCashSessionRequired?.();
        }
        toast(getActionableSaleErrorMessage(syncedSale), 'error', 6000);
      } else {
        toast('Venta registrada localmente', 'success');
        setLastSaleTotal(total);
        setLastCheckoutSucceeded(true);
        posCheckoutAttemptKey = null;
        resetForNextSale();
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : 'No se pudo registrar la venta.';
      setCheckoutError(message);
    } finally {
      posCheckoutInFlight = false;
      setIsProcessingSale(false);
    }
  };

  const handleCheckout = async () => {
    try {
      await runCriticalOperation(performCheckout);
    } catch (error) {
      setCheckoutError(error instanceof Error ? error.message : 'No se pudo registrar la venta.');
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
    restoreOrder,
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
    cashRoundingAdjustment: cashRoundingAdjustmentAmount,
    payableTotal,
    checkoutDisabled,
    handleCheckout,
    lastCheckoutSucceeded,
    checkoutError,
    getEffectiveStock,
    readScaleForLine,
    scaleReady,
    syncCartPricesFromCatalog,
  };
};

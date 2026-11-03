/* eslint-disable react-hooks/refs, react-hooks/set-state-in-effect -- checkout and hardware flows synchronize imperative state */
import React, { useEffect, useRef, useState } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { EntityDropdown } from '@nubitio/crud';
import {
  AppDialog,
  Badge,
  Button,
  Chip,
  EmptyState,
  IconButton,
  TextAreaField,
  TextField,
} from '@nubitio/ui';
import type { SalePaymentLineDraft } from '@shared/lib/salePayments';
import { SplitPaymentsField } from '@shared/ui/split-payments/SplitPaymentsField';
import { formatPosCustomerLabel } from './lib/mapPosCustomer';
import type { CartItem, CustomerOption, PaymentMethodOption } from './types';
import { activeOnlyFilter } from '@shared/lib/activeEntityField';

const API = getCoreApiBaseUrl();
const ENTITY_DROPDOWN_FILTERS = activeOnlyFilter();
import { formatPosQuantity, isWeightUnitCode, toNumber } from './posHelpers';
import { formatPosReceiptLabel } from './posPresentation';
import type { LocalSale } from '@offline/types';

interface POSCheckoutPanelProps {
  cart: CartItem[];
  cartItemsCount: number;
  clearCart: () => void;
  selectedWarehouseName: string;
  cajaAbierta: boolean;
  quickMode: boolean;
  selectedCustomer: CustomerOption | null;
  selectedCustomerId: number | null;
  onCustomerChange: (value: string | null, item?: unknown) => void;
  clearSelectedCustomer: () => void;
  focusCustomerTrigger?: number;
  note: string;
  setNote: (value: string) => void;
  updateQuantity: (id: string, nextQuantity: number) => void | Promise<void>;
  updateLinePrice: (id: string, nextPrice: number) => void;
  allowLinePriceOverride?: boolean;
  allowDiscount?: boolean;
  updateLineDescription: (id: string, lineDescription: string) => void;
  removeFromCart: (id: string) => void;
  formatMoney: (value: number) => string;
  paymentMethods: PaymentMethodOption[];
  paymentLines: SalePaymentLineDraft[];
  setPaymentLines: (lines: SalePaymentLineDraft[]) => void;
  emitInvoice: boolean;
  setEmitInvoice: (checked: boolean) => void;
  requestedVoucherType: 'AUTO' | 'FACTURA' | 'BOLETA';
  setRequestedVoucherType: (value: 'AUTO' | 'FACTURA' | 'BOLETA') => void;
  canRequestFactura: boolean;
  creditSale: boolean;
  creditAmount: number;
  currency: 'PEN' | 'USD';
  setCurrency: (value: 'PEN' | 'USD') => void;
  exchangeRate: string;
  setExchangeRate: (value: string) => void;
  discountPercent: number;
  setDiscountPercent: (value: number) => void;
  subtotal: number;
  discountAmount: number;
  includedTax: number;
  igvRate?: number;
  total: number;
  payableTotal: number;
  cashRoundingAdjustment: number;
  changeAmount: number;
  checkoutDisabled: boolean;
  handleCheckout: () => void;
  isProcessingSale: boolean;
  lastCheckoutSucceeded?: boolean;
  checkoutError?: string | null;
  lastSaleCode?: string | null;
  lastSaleDocumentType?: string | null;
  lastSaleTotal?: number | null;
  lastSaleReceiptUrl?: string | null;
  onPrintLastSale?: () => void;
  syncSales?: LocalSale[];
  onRetrySale?: (saleId: string) => void;
  onReadScaleWeight?: (itemId: string) => void;
  scaleReady?: boolean;
  /** Local order reference shown in the header, e.g. "#00483". */
  ticketLabel?: string;
  /** Parks the order so another customer can be served; omitted when unsupported. */
  onHold?: () => void;
  paymentOpen: boolean;
  setPaymentOpen: (open: boolean) => void;
}

export function POSCheckoutPanel({
  cart,
  cartItemsCount: _cartItemsCount,
  clearCart,
  selectedWarehouseName: _selectedWarehouseName,
  cajaAbierta,
  quickMode,
  selectedCustomer,
  selectedCustomerId,
  onCustomerChange,
  clearSelectedCustomer,
  focusCustomerTrigger,
  note,
  setNote,
  updateQuantity,
  updateLinePrice,
  allowLinePriceOverride = true,
  allowDiscount = true,
  updateLineDescription,
  removeFromCart,
  formatMoney,
  paymentMethods,
  paymentLines,
  setPaymentLines,
  emitInvoice,
  setEmitInvoice,
  requestedVoucherType,
  setRequestedVoucherType,
  canRequestFactura,
  creditSale,
  creditAmount,
  currency,
  setCurrency: _setCurrency,
  exchangeRate,
  setExchangeRate,
  discountPercent,
  setDiscountPercent,
  discountAmount,
  includedTax,
  igvRate,
  total,
  payableTotal,
  cashRoundingAdjustment,
  changeAmount,
  checkoutDisabled,
  handleCheckout,
  isProcessingSale,
  lastCheckoutSucceeded = false,
  checkoutError = null,
  lastSaleCode,
  lastSaleDocumentType = null,
  lastSaleTotal = null,
  lastSaleReceiptUrl = null,
  onPrintLastSale,
  syncSales: _syncSales = [],
  onRetrySale: _onRetrySale,
  onReadScaleWeight,
  scaleReady = false,
  ticketLabel,
  onHold,
  paymentOpen,
  setPaymentOpen,
}: POSCheckoutPanelProps) {
  const customerDropdownRef = useRef<HTMLDivElement>(null);
  const cartBodyRef = useRef<HTMLDivElement>(null);
  // Collapsed by default to save vertical space — auto-opens when a discount is
  // already active so an in-progress sale never hides its own state.
  const [optionsOpen, setOptionsOpen] = useState(discountPercent > 0);
  // Notes are exceptional in the checkout flow. Keep their editor out of the
  // way, except when restoring a draft that already contains one.
  const [noteOpen, setNoteOpen] = useState(Boolean(note.trim()));

  // The payment footer is fixed while the cart scrolls. When the note opens,
  // bring the whole editor above that footer instead of leaving its lower half
  // hidden until the cashier scrolls manually.
  useEffect(() => {
    if (!noteOpen) return;

    // TextAreaField finishes its layout after the open-state paint. Waiting a
    // moment prevents scrolling to the former, shorter note height.
    const timer = window.setTimeout(() => {
      const cartBody = cartBodyRef.current?.closest<HTMLElement>('.pos-cart-panel');
      cartBody?.scrollTo({ top: cartBody.scrollHeight, behavior: 'smooth' });
    }, 100);

    return () => window.clearTimeout(timer);
  }, [noteOpen]);
  const voucherOptions = [
    { value: 'BOLETA', label: 'Boleta' },
    { value: 'FACTURA', label: 'Factura' },
    { value: 'NOTA', label: 'Nota de venta' },
  ] as const;
  type VoucherChoice = (typeof voucherOptions)[number]['value'];
  const voucherChoice: VoucherChoice = !emitInvoice
    ? 'NOTA'
    : requestedVoucherType === 'AUTO'
      ? canRequestFactura
        ? 'FACTURA'
        : 'BOLETA'
      : requestedVoucherType;
  const chooseVoucher = (choice: VoucherChoice) => {
    if (choice === 'NOTA') {
      setEmitInvoice(false);
      return;
    }
    setEmitInvoice(true);
    setRequestedVoucherType(choice);
  };
  // Inline quantity editing
  const [editingQtyId, setEditingQtyId] = useState<string | null>(null);
  const [editingQtyVal, setEditingQtyVal] = useState<string>('');
  const [editingPriceId, setEditingPriceId] = useState<string | null>(null);
  const [editingPriceVal, setEditingPriceVal] = useState<string>('');
  const [editingNameId, setEditingNameId] = useState<string | null>(null);
  const [editingNameVal, setEditingNameVal] = useState<string>('');
  // ── Micro-interaction state ───────────────────────────────────────────────
  const [showSuccess, setShowSuccess] = useState(false);
  const [newItemIds, setNewItemIds] = useState<Set<string>>(new Set());
  const [totalUpdated, setTotalUpdated] = useState(false);
  const prevProcessing = useRef(false);
  const prevCartIds = useRef<Set<string>>(new Set());
  const prevTotal = useRef(total);
  // Snapshot of change info captured at the moment of successful sale
  const successChangeRef = useRef(0);
  const successWasCashRef = useRef(false);
  const successCodeRef = useRef<string | null>(null);
  const successDocumentTypeRef = useRef<string | null>(null);
  const successTotalRef = useRef<number | null>(null);
  const successEmitInvoiceRef = useRef(false);
  const successReceiptUrlRef = useRef<string | null>(null);
  const successDismissTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Success overlay: fires when isProcessingSale goes true → false AND sale succeeded
  useEffect(() => {
    if (prevProcessing.current && !isProcessingSale) {
      if (lastCheckoutSucceeded) {
        successChangeRef.current = changeAmount;
        successWasCashRef.current = changeAmount > 0;
        successCodeRef.current = lastSaleCode ?? null;
        successDocumentTypeRef.current = lastSaleDocumentType;
        successTotalRef.current = lastSaleTotal;
        successEmitInvoiceRef.current = emitInvoice;
        successReceiptUrlRef.current = lastSaleReceiptUrl;
        setShowSuccess(true);
        setPaymentOpen(false);
        if (successDismissTimer.current) clearTimeout(successDismissTimer.current);
      }
    }
    prevProcessing.current = isProcessingSale;
  }, [
    isProcessingSale,
    lastCheckoutSucceeded,
    changeAmount,
    lastSaleCode,
    lastSaleDocumentType,
    lastSaleTotal,
    emitInvoice,
    lastSaleReceiptUrl,
  ]);

  // New line entrance animation
  useEffect(() => {
    const incoming = cart.map((i) => i.id).filter((id) => !prevCartIds.current.has(id));
    if (incoming.length > 0) {
      const next = new Set(incoming);
      setNewItemIds(next);
      setTimeout(() => setNewItemIds(new Set()), 300);
    }
    prevCartIds.current = new Set(cart.map((i) => i.id));
  }, [cart]);

  // Total flash when value changes
  useEffect(() => {
    if (prevTotal.current !== total && total > 0) {
      setTotalUpdated(true);
      setTimeout(() => setTotalUpdated(false), 420);
    }
    prevTotal.current = total;
  }, [total]);

  // Keep the options section open if a discount becomes active while collapsed
  // (e.g. restored from a pending sale) — never hide state the cashier set.
  useEffect(() => {
    if (discountPercent > 0) setOptionsOpen(true);
  }, [discountPercent]);

  // A completed checkout is a hard boundary between sales. Besides clearing
  // the data in the checkout hook, close transient editors so the next order
  // starts from the same compact, predictable layout.
  useEffect(() => {
    if (!lastCheckoutSucceeded || cart.length > 0) return;
    setOptionsOpen(false);
    setNoteOpen(false);
    setEditingQtyId(null);
    setEditingPriceId(null);
    setEditingNameId(null);
  }, [cart.length, lastCheckoutSucceeded]);

  useEffect(() => {
    if (!focusCustomerTrigger) return;
    clearSelectedCustomer();
    queueMicrotask(() => {
      const input = customerDropdownRef.current?.querySelector('input');
      input?.focus();
    });
  }, [focusCustomerTrigger, clearSelectedCustomer]);

  return (
    <aside className="pos-cart-panel">
      {/* ── Panel header ── */}
      <div className="pos-panel__header">
        <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
          <h2>{ticketLabel ? `Venta ${ticketLabel}` : 'Orden'}</h2>
          {cart.length > 0 && (
            <Badge
              variant="primary"
              size="sm"
              pill
              aria-label={`${cart.length} productos en carrito`}
            >
              {cart.length}
            </Badge>
          )}
        </div>
        {onHold && (
          <Button
            variant="ghost"
            size="sm"
            onClick={onHold}
            disabled={cart.length === 0}
            data-testid="pos-hold-btn"
          >
            <i className="ph ph-pause" aria-hidden="true" /> En espera
          </Button>
        )}
        <IconButton
          icon="ph ph-trash"
          label="Vaciar carrito"
          onClick={clearCart}
          disabled={cart.length === 0}
          variant="danger"
        />
      </div>

      {/* ── Customer — sticky, outside scroll ── */}
      {!quickMode && (
        <div className="pos-customer-section" ref={customerDropdownRef} data-testid="pos-customer">
          <div className="pos-customer-section__field">
            <span className="pos-customer-section__label">Cliente</span>
            <EntityDropdown
              url={`${API}customers`}
              valueField="id"
              textField="businessName"
              value={selectedCustomerId != null ? String(selectedCustomerId) : null}
              onChange={onCustomerChange}
              placeholder="Buscar por nombre o RUC..."
              searchExpr={['businessName', 'ruc']}
              itemFormatter={formatPosCustomerLabel}
              filters={ENTITY_DROPDOWN_FILTERS}
            />
          </div>
        </div>
      )}

      {/* ── Comprobante: one choice instead of a toggle plus a hidden chip group ── */}
      {!quickMode && (
        <div className="pos-voucher-section" data-testid="pos-voucher">
          <span className="pos-customer-section__label">Comprobante</span>
          <div className="pos-segmented" role="radiogroup" aria-label="Comprobante">
            {voucherOptions.map((option) => (
              <button
                key={option.value}
                type="button"
                role="radio"
                aria-checked={voucherChoice === option.value}
                className={`pos-segmented__option${voucherChoice === option.value ? ' is-active' : ''}`}
                disabled={option.value === 'FACTURA' && !canRequestFactura}
                onClick={() => chooseVoucher(option.value)}
              >
                {option.label}
              </button>
            ))}
          </div>
          {!canRequestFactura && (
            <span className="pos-voucher-section__hint">
              Factura se activa al elegir un cliente con RUC.
            </span>
          )}
        </div>
      )}

      {/* ── Caja cerrada banner ── */}
      {!cajaAbierta && (
        <div className="pos-caja-closed-banner" role="alert">
          Caja cerrada — no se puede cobrar
        </div>
      )}
      {checkoutError && (
        <div className="pos-checkout-error-banner" role="alert">
          {checkoutError}
        </div>
      )}

      {/* ── Scrollable body: cart items + note only ── */}
      <div ref={cartBodyRef} className="pos-cart-body">
        <div className="pos-cart-lines">
          {cart.length === 0 ? (
            <EmptyState
              icon="shopping-cart"
              title="Orden vacía"
              description="Presiona F2 para buscar productos"
              size="sm"
            />
          ) : (
            cart.map((item) => {
              const isWeighted = isWeightUnitCode(item.saleUnitCode);
              const quantityStep = isWeighted ? 0.1 : 1;
              const quantityFloor = isWeighted ? 0.001 : 1;
              const lineDescription = item.lineDescription ?? '';
              const displayName = lineDescription || item.name;
              const hasCustomName =
                lineDescription.trim() !== '' && lineDescription.trim() !== item.name;
              const displayUnit = item.saleUnitCode
                ? `/${item.saleUnitCode.toLowerCase()}`
                : ' c/u';

              const commitPriceEdit = () => {
                const nextPrice = Number.parseFloat(editingPriceVal);
                if (Number.isFinite(nextPrice)) {
                  updateLinePrice(item.id, nextPrice);
                }
                setEditingPriceId(null);
              };

              const commitNameEdit = () => {
                const nextName = editingNameVal.trim();
                updateLineDescription(
                  item.id,
                  nextName === '' || nextName === item.name ? '' : nextName,
                );
                setEditingNameId(null);
              };

              return (
                <div
                  key={item.id}
                  className={`pos-line${newItemIds.has(item.id) ? ' is-new' : ''}${isWeighted ? ' is-weighted' : ''}`}
                >
                  <div className="pos-line__body">
                    {editingNameId === item.id ? (
                      <input
                        type="text"
                        className="pos-line__name-input"
                        value={editingNameVal}
                        maxLength={255}
                        autoFocus
                        onChange={(e) => setEditingNameVal(e.target.value)}
                        onBlur={commitNameEdit}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') (e.target as HTMLInputElement).blur();
                          if (e.key === 'Escape') setEditingNameId(null);
                        }}
                        aria-label={`Descripcion de ${item.name}`}
                      />
                    ) : (
                      <button
                        type="button"
                        className="pos-line__name pos-line__name--editable"
                        title="Editar descripcion para comprobante"
                        onClick={() => {
                          setEditingNameId(item.id);
                          setEditingNameVal(displayName);
                        }}
                      >
                        {displayName}
                      </button>
                    )}
                    {allowLinePriceOverride && editingPriceId === item.id ? (
                      <div className="pos-line__unit pos-line__unit--editing">
                        <input
                          type="number"
                          className="pos-line__price-input"
                          value={editingPriceVal}
                          min={0}
                          step="0.10"
                          autoFocus
                          onChange={(e) => setEditingPriceVal(e.target.value)}
                          onFocus={(e) => e.currentTarget.select()}
                          onBlur={commitPriceEdit}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter') (e.target as HTMLInputElement).blur();
                            if (e.key === 'Escape') setEditingPriceId(null);
                          }}
                          aria-label={`Precio de ${item.name}`}
                        />
                        <span>{displayUnit}</span>
                      </div>
                    ) : allowLinePriceOverride ? (
                      <button
                        type="button"
                        className="pos-line__unit pos-line__unit--editable"
                        title="Editar precio unitario"
                        onClick={() => {
                          setEditingPriceId(item.id);
                          setEditingPriceVal(toNumber(item.salePrice).toFixed(2));
                        }}
                      >
                        <span>
                          {formatMoney(toNumber(item.salePrice))}
                          {displayUnit}
                        </span>
                        <i className="ph ph-pencil-simple" aria-hidden="true" />
                      </button>
                    ) : (
                      <div className="pos-line__unit">
                        {formatMoney(toNumber(item.salePrice))}
                        {displayUnit}
                      </div>
                    )}
                    {hasCustomName && (
                      <div className="pos-line__base-name">Producto: {item.name}</div>
                    )}
                  </div>
                  <div className="pos-line__actions">
                    <div className="pos-line__qty">
                      <button
                        type="button"
                        className={item.quantity <= quantityFloor ? 'is-remove' : ''}
                        onClick={() =>
                          item.quantity <= quantityFloor
                            ? removeFromCart(item.id)
                            : updateQuantity(item.id, item.quantity - quantityStep)
                        }
                        aria-label={
                          item.quantity <= quantityFloor ? `Quitar ${item.name}` : 'Restar cantidad'
                        }
                      >
                        {item.quantity <= quantityFloor ? (
                          <i className="ph ph-trash" aria-hidden="true" />
                        ) : (
                          <i className="ph ph-minus" aria-hidden="true" />
                        )}
                      </button>
                      {editingQtyId === item.id ? (
                        <input
                          type="number"
                          className="pos-line__qty-input"
                          value={editingQtyVal}
                          min={0}
                          step={quantityStep}
                          autoFocus
                          onFocus={(e) => e.currentTarget.select()}
                          onChange={(e) => setEditingQtyVal(e.target.value)}
                          onBlur={() => {
                            const n = Number.parseFloat(editingQtyVal);
                            if (!isNaN(n) && n > 0) void updateQuantity(item.id, n);
                            else if (!isNaN(n) && n === 0) removeFromCart(item.id);
                            setEditingQtyId(null);
                          }}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter') (e.target as HTMLInputElement).blur();
                            if (e.key === 'Escape') setEditingQtyId(null);
                          }}
                          aria-label="Cantidad"
                        />
                      ) : (
                        <span
                          className="pos-line__qty-display"
                          role="button"
                          tabIndex={0}
                          title="Editar cantidad"
                          onClick={() => {
                            setEditingQtyId(item.id);
                            setEditingQtyVal(String(item.quantity));
                          }}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                              setEditingQtyId(item.id);
                              setEditingQtyVal(String(item.quantity));
                            }
                          }}
                        >
                          {formatPosQuantity(item.quantity, item.saleUnitCode)}
                        </span>
                      )}
                      <button
                        type="button"
                        onClick={() => updateQuantity(item.id, item.quantity + quantityStep)}
                        aria-label="Sumar cantidad"
                      >
                        <i className="ph ph-plus" aria-hidden="true" />
                      </button>
                    </div>
                    {isWeighted && onReadScaleWeight && (
                      <button
                        type="button"
                        className="pos-line__scale-btn"
                        disabled={!scaleReady}
                        onClick={() => onReadScaleWeight(item.id)}
                        aria-label={`Leer peso de ${item.name} desde la balanza`}
                        title={scaleReady ? 'Leer balanza' : 'Balanza no configurada'}
                      >
                        <i className="ph ph-scales" aria-hidden="true" />
                      </button>
                    )}
                  </div>
                  <strong className="pos-line__total">
                    {formatMoney(toNumber(item.salePrice) * item.quantity)}
                  </strong>
                </div>
              );
            })
          )}
        </div>
        {!quickMode && (
          <div className="pos-note-section">
            <button
              type="button"
              className="pos-note-section__toggle"
              onClick={() => setNoteOpen((open) => !open)}
              aria-expanded={noteOpen}
              aria-controls="pos-internal-note"
            >
              <i className={`ph ph-caret-${noteOpen ? 'down' : 'right'}`} aria-hidden="true" />
              <span>Nota interna</span>
              {!noteOpen && note.trim() && (
                <span className="pos-note-section__badge">Con contenido</span>
              )}
            </button>
            {noteOpen && (
              <div id="pos-internal-note" className="pos-note-section__content">
                <label className="pos-note-section__field">
                  <TextAreaField
                    rows={2}
                    maxLength={2000}
                    placeholder="Referencia, instrucciones o comentario de la venta..."
                    value={note}
                    onChange={(event) => setNote(event.target.value)}
                    aria-label="Nota interna de la venta"
                  />
                </label>
                <span className="pos-note-section__help">
                  No modifica la descripción que aparece en las líneas del comprobante.
                </span>
              </div>
            )}
          </div>
        )}
      </div>

      {/* ── Fixed footer: everything non-scrolling ── */}
      <div className="pos-checkout-footer">
        {/* Opciones — collapsed by default to save space; discount/invoice/FX are
            per-shift or occasional settings, not per-sale taps like payment method. */}
        <div className="pos-footer-options">
          <button
            type="button"
            className="pos-footer-options__toggle"
            onClick={() => setOptionsOpen((open) => !open)}
            aria-expanded={optionsOpen}
          >
            <span>Descuento y opciones</span>
            {!optionsOpen && discountPercent > 0 && (
              <span className="pos-footer-options__badge">-{discountPercent}%</span>
            )}
            <i className={`ph ph-caret-${optionsOpen ? 'up' : 'down'}`} aria-hidden="true" />
          </button>

          {optionsOpen && (
            <div className="pos-footer-options__body">
              {allowDiscount && (
                <div className="pos-footer-option-row">
                  <span className="pos-discount-label">Descuento</span>
                  <div className="pos-discount-presets" role="group" aria-label="Descuento">
                    <Chip
                      label="Ninguno"
                      active={discountPercent === 0}
                      size="sm"
                      onClick={() => setDiscountPercent(0)}
                    />
                    {[5, 10, 15, 20].map((pct) => (
                      <Chip
                        key={pct}
                        label={`${pct}%`}
                        active={discountPercent === pct}
                        size="sm"
                        onClick={() => setDiscountPercent(discountPercent === pct ? 0 : pct)}
                      />
                    ))}
                  </div>
                </div>
              )}

              {currency === 'USD' && (
                <div className="pos-footer-option-row">
                  <span className="pos-discount-label">T.C. USD/PEN</span>
                  <TextField
                    id="pos-exchange-rate"
                    type="number"
                    min={0.01}
                    step="0.01"
                    value={exchangeRate}
                    onChange={(e) => setExchangeRate(e.target.value)}
                    style={{ width: '72px' }}
                    aria-label="Tipo de cambio"
                  />
                </div>
              )}
            </div>
          )}
        </div>

        {/* Summary rows */}
        <div className="pos-summary">
          <div className="pos-summary__row">
            <span>Op. gravada</span>
            <span>{formatMoney(total - includedTax)}</span>
          </div>
          {discountAmount > 0 && (
            <div className="pos-summary__row">
              <span>Descuento</span>
              <span className="pos-summary__discount">− {formatMoney(discountAmount)}</span>
            </div>
          )}
          <div className="pos-summary__row">
            <span>
              {typeof igvRate === 'number'
                ? `IGV (${(igvRate * 100).toFixed(1).replace(/\.0$/, '')}%)`
                : 'IGV'}
            </span>
            <span>{formatMoney(includedTax)}</span>
          </div>
        </div>

        {/* Total hero */}
        <div className={`pos-total-hero${totalUpdated ? ' is-updated' : ''}`}>
          <span>Total</span>
          <strong>{formatMoney(total)}</strong>
        </div>

        {/* Checkout CTA — opens the payment dialog */}
        <button
          type="button"
          className="pos-checkout"
          onClick={() => setPaymentOpen(true)}
          disabled={cart.length === 0 || total <= 0 || isProcessingSale || !cajaAbierta}
          data-testid="pos-open-payment-btn"
        >
          {isProcessingSale ? (
            <span className="pos-checkout__processing">Procesando...</span>
          ) : !cajaAbierta ? (
            <span className="pos-checkout__processing">Caja cerrada</span>
          ) : (
            <>
              <span>
                Cobrar <kbd className="pos-checkout__key">F9</kbd>
              </span>
              <strong>{formatMoney(payableTotal)}</strong>
            </>
          )}
        </button>
      </div>

      {/* ── Payment dialog ── */}
      <AppDialog
        open={paymentOpen}
        title="Cobrar venta"
        onClose={() => setPaymentOpen(false)}
        className="pos-payment-dialog"
        width={640}
        footer={
          <div className="pos-payment-dialog__footer">
            <Button variant="secondary" onClick={() => setPaymentOpen(false)}>
              Volver
            </Button>
            <Button
              variant="primary"
              onClick={handleCheckout}
              disabled={checkoutDisabled || !cajaAbierta}
              data-testid="pos-checkout-btn"
            >
              {isProcessingSale
                ? 'Procesando...'
                : `${
                    creditSale
                      ? 'Registrar crédito'
                      : emitInvoice
                        ? `Cobrar y emitir ${voucherChoice === 'FACTURA' ? 'factura' : 'boleta'}`
                        : 'Cobrar'
                  } · ${formatMoney(payableTotal)}`}
            </Button>
          </div>
        }
      >
        <div className="pos-payment-dialog__body">
          {checkoutError && (
            <div className="pos-checkout-error-banner" role="alert">
              {checkoutError}
            </div>
          )}
          {cashRoundingAdjustment > 0 && (
            <div className="pos-change-row pos-cash-rounding" data-testid="pos-cash-rounding">
              <span>Redondeo en efectivo</span>
              <strong>− {formatMoney(cashRoundingAdjustment)}</strong>
            </div>
          )}

          <div className="pos-payment-dialog__total">
            <span>Total a cobrar</span>
            <strong>{formatMoney(payableTotal)}</strong>
          </div>
          <SplitPaymentsField
            paymentMethods={paymentMethods}
            total={payableTotal}
            currency={currency}
            value={paymentLines}
            onChange={setPaymentLines}
            variant="pos"
            layout="inline"
            quickMethodsLimit={4}
          />

          {creditSale && (
            <div className="pos-credit-notice" data-testid="pos-credit-notice" role="status">
              <i className="ph ph-hand-coins" aria-hidden="true" />
              <span>
                <strong>Venta a crédito · {formatMoney(creditAmount)}</strong>
                <small>
                  {selectedCustomerId == null || selectedCustomer?.doc === '00000000'
                    ? 'Selecciona un cliente para registrar la cuenta por cobrar.'
                    : 'Este saldo se registrará automáticamente en Cuentas por cobrar.'}
                </small>
              </span>
            </div>
          )}

          {changeAmount > 0 && (
            <div className="pos-change-row is-change">
              <span>Vuelto</span>
              <strong>{formatMoney(changeAmount)}</strong>
            </div>
          )}
        </div>
      </AppDialog>

      {/* ── Post-sale overlay ── */}
      {showSuccess && (
        <div
          className="pos-postsale"
          aria-live="assertive"
          role="status"
          data-testid="pos-postsale"
        >
          <div className="pos-postsale__card">
            <div className="pos-postsale__check">✓</div>
            <h2 className="pos-postsale__title" data-testid="pos-postsale-title">
              Venta registrada
            </h2>
            {/* eslint-disable-next-line react-hooks/refs -- refs hold post-sale snapshot values captured at sale completion; read after showSuccess is set */}
            {successTotalRef.current != null && successTotalRef.current > 0 && (
              // eslint-disable-next-line react-hooks/refs -- refs hold post-sale snapshot values captured at sale completion; read after showSuccess is set
              <p className="pos-postsale__total">{formatMoney(successTotalRef.current)}</p>
            )}
            {/* eslint-disable-next-line react-hooks/refs -- refs hold post-sale snapshot values captured at sale completion; read after showSuccess is set */}
            {formatPosReceiptLabel(successCodeRef.current, successDocumentTypeRef.current) ? (
              // eslint-disable-next-line react-hooks/refs -- refs hold post-sale snapshot values captured at sale completion; read after showSuccess is set
              <p className="pos-postsale__code" data-testid="pos-postsale-code">
                {formatPosReceiptLabel(successCodeRef.current, successDocumentTypeRef.current)}
              </p>
            ) : successEmitInvoiceRef.current ? (
              <p className="pos-postsale__hint">Comprobante en proceso de envío a SUNAT</p>
            ) : null}
            {/* eslint-disable-next-line react-hooks/refs -- refs hold post-sale snapshot values captured at sale completion; read after showSuccess is set */}
            {successWasCashRef.current && successChangeRef.current > 0 && (
              <div className="pos-postsale__change">
                <span>Vuelto</span>
                {/* eslint-disable-next-line react-hooks/refs -- refs hold post-sale snapshot values captured at sale completion; read after showSuccess is set */}
                <strong>{formatMoney(successChangeRef.current)}</strong>
              </div>
            )}
            <div className="pos-postsale__actions">
              {onPrintLastSale && (
                <button
                  type="button"
                  className="pos-postsale__btn pos-postsale__btn--secondary"
                  onClick={onPrintLastSale}
                >
                  Imprimir ticket
                </button>
              )}
              {/* eslint-disable-next-line react-hooks/refs -- refs hold post-sale snapshot values captured at sale completion; read after showSuccess is set */}
              {successReceiptUrlRef.current && (
                <button
                  type="button"
                  className="pos-postsale__btn pos-postsale__btn--secondary"
                  onClick={() => {
                    const receiptUrl = successReceiptUrlRef.current;
                    const code = successCodeRef.current;
                    if (!receiptUrl) {
                      return;
                    }
                    const text = encodeURIComponent(
                      formatPosReceiptLabel(code, successDocumentTypeRef.current)
                        ? `Tu ${formatPosReceiptLabel(code, successDocumentTypeRef.current)}: ${receiptUrl}`
                        : `Tu comprobante: ${receiptUrl}`,
                    );
                    window.open(`https://wa.me/?text=${text}`, '_blank', 'noopener,noreferrer');
                  }}
                >
                  Enviar por WhatsApp
                </button>
              )}
              <button
                type="button"
                className="pos-postsale__btn pos-postsale__btn--primary"
                onClick={() => {
                  if (successDismissTimer.current) clearTimeout(successDismissTimer.current);
                  setShowSuccess(false);
                }}
                autoFocus
                data-testid="pos-postsale-new-sale"
              >
                Nueva venta
              </button>
            </div>
          </div>
        </div>
      )}
    </aside>
  );
}

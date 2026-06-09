import React, { useEffect, useRef, useState } from 'react';
import { Badge, Chip, EmptyState, IconButton, TextField } from '@nubit/ui';
import type { CartItem, CustomerOption, PaymentMethodOption } from './types';
import { isCashPaymentMethod, normalizeText, toNumber } from './posHelpers';
import type { LocalSale } from '@offline/types';

interface POSCheckoutPanelProps {
  cart: CartItem[];
  cartItemsCount: number;
  clearCart: () => void;
  selectedWarehouseName: string;
  cajaAbierta: boolean;
  quickMode: boolean;
  customerSearch: string;
  setCustomerSearch: (value: string) => void;
  clearSelectedCustomer: () => void;
  focusCustomerTrigger?: number;
  selectedCustomerId: number | null;
  setSelectedCustomerId: (value: number | null) => void;
  customerOptions: CustomerOption[];
  customersLoading: boolean;
  customersHasMore: boolean;
  customersPage: number;
  deferredCustomerSearch: string;
  loadCustomers: (query: string, page: number, append: boolean) => void;
  note: string;
  setNote: (value: string) => void;
  updateQuantity: (id: string, nextQuantity: number) => void | Promise<void>;
  removeFromCart: (id: string) => void;
  formatMoney: (value: number) => string;
  paymentMethods: PaymentMethodOption[];
  selectedPaymentMethodId: number | null;
  setSelectedPaymentMethodId: (id: number) => void;
  emitInvoice: boolean;
  setEmitInvoice: (checked: boolean) => void;
  currency: 'PEN' | 'USD';
  setCurrency: (value: 'PEN' | 'USD') => void;
  exchangeRate: string;
  setExchangeRate: (value: string) => void;
  discountPercent: number;
  setDiscountPercent: (value: number) => void;
  isCashPaymentSelected: boolean;
  amountReceived: string;
  setAmountReceived: (value: string) => void;
  quickCashActions: Array<{ label: string; value: number }>;
  subtotal: number;
  discountAmount: number;
  includedTax: number;
  igvRate?: number;
  total: number;
  amountReceivedNumber: number;
  amountMissing: number;
  changeAmount: number;
  checkoutDisabled: boolean;
  handleCheckout: () => void;
  isProcessingSale: boolean;
  lastSaleCode?: string | null;
  onPrintLastSale?: () => void;
  syncSales?: LocalSale[];
  onRetrySale?: (saleId: string) => void;
}

const MAX_VISIBLE_PAYMENT_METHODS = 3;

function getPaymentIconClass(methodName: string): string {
  const n = normalizeText(methodName);
  if (isCashPaymentMethod({ id: 0, name: methodName })) return 'ph ph-receipt';
  if (n.includes('debito')) return 'ph ph-credit-card';
  if (n.includes('credito') || n.includes('tarjet')) return 'ph ph-credit-card';
  if (n.includes('transferencia')) return 'ph ph-building';
  if (n.includes('yape') || n.includes('plin') || n.includes('wallet')) return 'ph ph-wallet';
  if (n.includes('link')) return 'ph ph-link';
  if (n.includes('deposito')) return 'ph ph-money';
  return 'ph ph-device-mobile';
}

export function POSCheckoutPanel({
  cart,
  cartItemsCount: _cartItemsCount,
  clearCart,
  selectedWarehouseName: _selectedWarehouseName,
  cajaAbierta,
  quickMode,
  customerSearch,
  setCustomerSearch,
  clearSelectedCustomer,
  focusCustomerTrigger,
  selectedCustomerId,
  setSelectedCustomerId,
  customerOptions,
  customersLoading,
  customersHasMore,
  customersPage,
  deferredCustomerSearch,
  loadCustomers,
  note: _note,
  setNote: _setNote,
  updateQuantity,
  removeFromCart,
  formatMoney,
  paymentMethods,
  selectedPaymentMethodId,
  setSelectedPaymentMethodId,
  emitInvoice: _emitInvoice,
  setEmitInvoice: _setEmitInvoice,
  currency,
  setCurrency,
  exchangeRate,
  setExchangeRate,
  discountPercent,
  setDiscountPercent,
  isCashPaymentSelected,
  amountReceived,
  setAmountReceived,
  quickCashActions,
  subtotal,
  discountAmount,
  includedTax,
  total,
  amountReceivedNumber,
  amountMissing,
  changeAmount,
  checkoutDisabled,
  handleCheckout,
  isProcessingSale,
  lastSaleCode,
  onPrintLastSale,
  syncSales: _syncSales = [],
  onRetrySale: _onRetrySale,
}: POSCheckoutPanelProps) {
  const [showCustomerSuggestions, setShowCustomerSuggestions] = useState(false);
  const [customerOpen, setCustomerOpen] = useState(false);
  const [showMorePayments, setShowMorePayments] = useState(false);
  const [cashExpanded, setCashExpanded] = useState(false);
  // Inline quantity editing
  const [editingQtyId, setEditingQtyId] = useState<string | null>(null);
  const [editingQtyVal, setEditingQtyVal] = useState<string>('');
  // Customer field ref (for F3 shortcut focus)
  const customerInputRef = useRef<HTMLInputElement>(null);
  // Set to true when F3 fires; consumed once the input actually mounts
  const pendingCustomerFocusRef = useRef(false);

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
  const successDismissTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Success overlay: fires when isProcessingSale goes true → false
  useEffect(() => {
    if (prevProcessing.current && !isProcessingSale) {
      successChangeRef.current = changeAmount;
      successWasCashRef.current = isCashPaymentSelected && amountReceivedNumber > 0;
      successCodeRef.current = lastSaleCode ?? null;
      setShowSuccess(true);
      // Auto-dismiss after 8s if the cashier doesn't interact
      if (successDismissTimer.current) clearTimeout(successDismissTimer.current);
      successDismissTimer.current = setTimeout(() => setShowSuccess(false), 8000);
    }
    prevProcessing.current = isProcessingSale;
  }, [isProcessingSale, changeAmount, isCashPaymentSelected, amountReceivedNumber, lastSaleCode]);

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

  // F3 → open customer field; mark pending focus (input may not be mounted yet)
  useEffect(() => {
    if (!focusCustomerTrigger) return;
    pendingCustomerFocusRef.current = true;
    // Always clear so the search <input> mounts (clearSelectedCustomer is idempotent)
    clearSelectedCustomer();
    setCustomerOpen(true);
    setShowCustomerSuggestions(true);
  }, [focusCustomerTrigger]); // eslint-disable-line react-hooks/exhaustive-deps

  // Collapse cash panel whenever payment method changes
  useEffect(() => { setCashExpanded(false); }, [selectedPaymentMethodId]);

  const selectedCustomer =
    selectedCustomerId !== null ? customerOptions.find((c) => c.id === selectedCustomerId) : null;

  const hasMorePayments = paymentMethods.length > MAX_VISIBLE_PAYMENT_METHODS;
  const visiblePaymentMethods =
    hasMorePayments && !showMorePayments
      ? paymentMethods.slice(0, MAX_VISIBLE_PAYMENT_METHODS)
      : paymentMethods;

  return (
    <aside className="pos-cart-panel">
      {/* ── Panel header ── */}
      <div className="pos-panel__header">
        <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
          <h2>Orden</h2>
          {cart.length > 0 && (
            <Badge variant="primary" size="sm" pill aria-label={`${cart.length} productos en carrito`}>
              {cart.length}
            </Badge>
          )}
        </div>
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
        <div className="pos-customer-section">
          <div className="pos-customer-row" data-testid="pos-customer-toggle">
            <i className="ph ph-user-circle pos-customer-row__icon" aria-hidden="true" />
            <div className="pos-customer-row__info">
              <span className="pos-customer-row__name">
                {selectedCustomer ? selectedCustomer.businessName : 'Cliente general'}
              </span>
              <span className="pos-customer-row__sub">
                {selectedCustomer ? (selectedCustomer.doc || 'Sin documento') : 'Sin RUC'}
              </span>
            </div>
            <button
              type="button"
              className="pos-customer-row__change"
              onClick={() => {
                if (selectedCustomer) {
                  clearSelectedCustomer();
                  setCustomerOpen(true);
                  setShowCustomerSuggestions(true);
                } else {
                  setCustomerOpen((v) => !v);
                  setShowCustomerSuggestions(true);
                }
              }}
              data-testid="pos-customer-clear"
            >
              {customerOpen && !selectedCustomer ? 'Cancelar' : 'Cambiar'}
              {' '}<i className="ph ph-caret-right" aria-hidden="true" />
            </button>
          </div>

          {customerOpen && !selectedCustomer && (
            <div className="pos-customer-wrap">
              <TextField
                type="text"
                placeholder="Buscar por nombre o RUC..."
                value={customerSearch}
                onChange={(e) => { setCustomerSearch(e.target.value); setShowCustomerSuggestions(true); }}
                onFocus={() => setShowCustomerSuggestions(true)}
                onBlur={() => setTimeout(() => setShowCustomerSuggestions(false), 120)}
                aria-label="Cliente"
                autoComplete="off"
                className="pos-config-input"
                autoFocus
                data-testid="pos-customer-search"
                ref={(el) => {
                  (customerInputRef as React.MutableRefObject<HTMLInputElement | null>).current = el;
                  if (el && pendingCustomerFocusRef.current) {
                    pendingCustomerFocusRef.current = false;
                    el.focus();
                  }
                }}
              />
              {customerSearch.length > 0 && !customersLoading && customerOptions.length === 0 && (
                <p className="pos-customer-no-results">Sin resultados</p>
              )}
              {showCustomerSuggestions && customerOptions.length > 0 && (
                <div className="pos-customer-suggestions" role="listbox" aria-label="Sugerencias" data-testid="pos-customer-suggestions">
                  {customerOptions.slice(0, 8).map((customer) => (
                    <button
                      key={customer.id}
                      type="button"
                      role="option"
                      aria-selected={selectedCustomerId === customer.id}
                      className={`pos-customer-option ${selectedCustomerId === customer.id ? 'is-active' : ''}`}
                      onMouseDown={(e) => e.preventDefault()}
                      data-testid="pos-customer-option"
                      onClick={() => { setSelectedCustomerId(customer.id); setShowCustomerSuggestions(false); setCustomerOpen(false); }}
                    >
                      <span>{customer.businessName}</span>
                      {customer.doc && <small>{customer.doc}</small>}
                    </button>
                  ))}
                  {(customersLoading || customersHasMore) && (
                    <button type="button" className="pos-load-more-customers"
                      onClick={() => loadCustomers(deferredCustomerSearch, customersPage + 1, true)}
                      disabled={customersLoading || !customersHasMore}
                    >
                      {customersLoading ? 'Buscando...' : 'Ver más'}
                    </button>
                  )}
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* ── Caja cerrada banner ── */}
      {!cajaAbierta && (
        <div className="pos-caja-closed-banner" role="alert">
          Caja cerrada — no se puede cobrar
        </div>
      )}

      {/* ── Scrollable body: cart items + note only ── */}
      <div className="pos-cart-body">
        <div className="pos-cart-lines">
          {cart.length === 0 ? (
            <EmptyState
              icon="shopping-cart"
              title="Orden vacía"
              description="Presiona F2 para buscar productos"
              size="sm"
            />
          ) : (
            cart.map((item) => (
              <div key={item.id} className={`pos-line${newItemIds.has(item.id) ? ' is-new' : ''}`}>
                <div className="pos-line__body">
                  <div className="pos-line__name">{item.name}</div>
                  <div className="pos-line__unit">{formatMoney(toNumber(item.salePrice))} c/u</div>
                </div>
                <div className="pos-line__qty">
                  <button
                    type="button"
                    className={item.quantity === 1 ? 'is-remove' : ''}
                    onClick={() => item.quantity === 1 ? removeFromCart(item.id) : updateQuantity(item.id, item.quantity - 1)}
                    aria-label={item.quantity === 1 ? `Quitar ${item.name}` : 'Restar uno'}
                  >
                    {item.quantity === 1
                      ? <i className="ph ph-trash" aria-hidden="true" />
                      : <i className="ph ph-minus" aria-hidden="true" />}
                  </button>
                  {editingQtyId === item.id ? (
                    <input
                      type="number"
                      className="pos-line__qty-input"
                      value={editingQtyVal}
                      min={0}
                      autoFocus
                      onChange={(e) => setEditingQtyVal(e.target.value)}
                      onBlur={() => {
                        const n = parseInt(editingQtyVal, 10);
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
                      onClick={() => { setEditingQtyId(item.id); setEditingQtyVal(String(item.quantity)); }}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') { setEditingQtyId(item.id); setEditingQtyVal(String(item.quantity)); }
                      }}
                    >
                      {item.quantity}
                    </span>
                  )}
                  <button type="button" onClick={() => updateQuantity(item.id, item.quantity + 1)} aria-label="Sumar uno">
                    <i className="ph ph-plus" aria-hidden="true" />
                  </button>
                </div>
                <strong className="pos-line__total">
                  {formatMoney(toNumber(item.salePrice) * item.quantity)}
                </strong>
              </div>
            ))
          )}
        </div>

      </div>

      {/* ── Fixed footer: everything non-scrolling ── */}
      <div className="pos-checkout-footer">

        {/* Discount — single compact inline row */}
        <div className="pos-footer-option-row">
          <span className="pos-discount-label">Descuento</span>
          <div className="pos-discount-presets" role="group" aria-label="Descuento">
            <Chip label="Ninguno" active={discountPercent === 0} size="sm" onClick={() => setDiscountPercent(0)} />
            {[5, 10, 15, 20].map((pct) => (
              <Chip key={pct} label={`${pct}%`} active={discountPercent === pct} size="sm"
                onClick={() => setDiscountPercent(discountPercent === pct ? 0 : pct)} />
            ))}
          </div>
        </div>
        {currency === 'USD' && (
          <div className="pos-footer-option-row">
            <span className="pos-discount-label">T.C. USD/PEN</span>
            <TextField id="pos-exchange-rate" type="number" min={0.01} step="0.01"
              value={exchangeRate} onChange={(e) => setExchangeRate(e.target.value)}
              style={{ width: '72px' }} aria-label="Tipo de cambio" />
          </div>
        )}

        {/* Summary rows */}
        <div className="pos-summary">
          <div className="pos-summary__row">
            <span>Base imponible</span>
            <span>{formatMoney(subtotal)}</span>
          </div>
          {discountAmount > 0 && (
            <div className="pos-summary__row">
              <span>Descuento</span>
              <span className="pos-summary__discount">− {formatMoney(discountAmount)}</span>
            </div>
          )}
          <div className="pos-summary__row">
            <span>IGV (18%)</span>
            <span>{formatMoney(includedTax)}</span>
          </div>
        </div>

        {/* Total hero */}
        <div className={`pos-total-hero${totalUpdated ? ' is-updated' : ''}`}>
          <span>TOTAL</span>
          <strong>{formatMoney(total)}</strong>
        </div>

        {/* Payment methods — text only, single row */}
        <div className="pos-payment__methods" role="radiogroup" aria-label="Método de pago">
          {visiblePaymentMethods.map((method) => (
            <label
              key={method.id}
              className={`pos-payment-btn ${selectedPaymentMethodId === method.id ? 'is-active' : ''}`}
              role="radio"
              aria-checked={selectedPaymentMethodId === method.id}
            >
              <input type="radio" name="pos-payment-method"
                checked={selectedPaymentMethodId === method.id}
                onChange={() => setSelectedPaymentMethodId(method.id)} />
              <span>{method.name}</span>
            </label>
          ))}
          {hasMorePayments && (
            <button type="button"
              className={`pos-payment-btn pos-payment-btn--more ${showMorePayments ? 'is-expanded' : ''}`}
              onClick={() => setShowMorePayments((v) => !v)}
            >
              <span>{showMorePayments ? 'Menos' : 'Más'}</span>
            </button>
          )}
        </div>

        {/* Cash received — collapsed by default, toggle to expand */}
        {isCashPaymentSelected && (
          <>
            <button
              type="button"
              className={`pos-cash-toggle${cashExpanded ? ' is-open' : ''}`}
              onClick={() => setCashExpanded((v) => !v)}
            >
              <i className="ph ph-coins" aria-hidden="true" />
              <span>Monto recibido</span>
              <i className={`ph ${cashExpanded ? 'ph-caret-up' : 'ph-caret-down'}`} aria-hidden="true" />
              {amountReceivedNumber > 0 && !cashExpanded && (
                <strong className="pos-cash-toggle__value">{formatMoney(amountReceivedNumber)}</strong>
              )}
            </button>
            {cashExpanded && (
              <div className="pos-payment__cash">
                <TextField
                  id="pos-amount-received"
                  type="number"
                  min={0}
                  step="0.01"
                  placeholder="0.00"
                  value={amountReceived}
                  onChange={(e) => setAmountReceived(e.target.value)}
                  autoFocus
                  aria-label="Monto recibido"
                />
                <div className="pos-discount-presets" role="group" aria-label="Montos rápidos">
                  {quickCashActions.map((action) => (
                    <Chip key={action.label} label={action.label}
                      active={amountReceived === action.value.toFixed(2)} size="sm" disabled={total <= 0}
                      onClick={() => setAmountReceived(amountReceived === action.value.toFixed(2) ? '' : action.value.toFixed(2))} />
                  ))}
                </div>
              </div>
            )}
            {amountReceivedNumber > 0 && (
              <div className={`pos-change-row ${amountMissing > 0 ? 'is-missing' : 'is-change'}`}>
                <span>{amountMissing > 0 ? 'Falta' : 'Vuelto'}</span>
                <strong>{formatMoney(amountMissing > 0 ? amountMissing : changeAmount)}</strong>
              </div>
            )}
          </>
        )}

        {/* Checkout CTA */}
        <button
          type="button"
          className="pos-checkout"
          onClick={handleCheckout}
          disabled={checkoutDisabled || !cajaAbierta}
          data-testid="pos-checkout-btn"
        >
          {isProcessingSale ? (
            <span className="pos-checkout__processing">Procesando...</span>
          ) : !cajaAbierta ? (
            <span className="pos-checkout__processing">Caja cerrada</span>
          ) : (
            <>
              <span>Cobrar</span>
              <strong>{formatMoney(total)}</strong>
            </>
          )}
        </button>
      </div>

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
              ¡Venta completada!
            </h2>
            {/* eslint-disable-next-line react-hooks/refs -- refs hold post-sale snapshot values captured at sale completion; read after showSuccess is set */}
            {successCodeRef.current && (
              // eslint-disable-next-line react-hooks/refs -- refs hold post-sale snapshot values captured at sale completion; read after showSuccess is set
              <p className="pos-postsale__code">{successCodeRef.current}</p>
            )}
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

import React, { useEffect, useRef, useState } from 'react';
import {
  BadgePercent,
  Banknote,
  Building2,
  ChevronDown,
  CreditCard,
  Eraser,
  Link,
  MessageSquare,
  Minus,
  Plus,
  Receipt,
  ShoppingCart,
  Smartphone,
  Trash2,
  User2,
  Wallet,
} from 'lucide-react';
import type { CartItem, CustomerOption, PaymentMethodOption } from './types';
import { isCashPaymentMethod, normalizeText, toNumber } from './posHelpers';
import type { LocalSale } from '@offline/types';
import { SaleSyncStatus } from './SaleSyncStatus';

interface POSCheckoutPanelProps {
  cart: CartItem[];
  cartItemsCount: number;
  clearCart: () => void;
  selectedWarehouseName: string;
  cajaAbierta: boolean;
  quickMode: boolean;
  customerSearch: string;
  setCustomerSearch: (value: string) => void;
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
  discountPercent: number;
  setDiscountPercent: (value: number) => void;
  isCashPaymentSelected: boolean;
  amountReceived: string;
  setAmountReceived: (value: string) => void;
  quickCashActions: Array<{ label: string; value: number }>;
  subtotal: number;
  discountAmount: number;
  includedTax: number;
  total: number;
  amountReceivedNumber: number;
  amountMissing: number;
  changeAmount: number;
  checkoutDisabled: boolean;
  handleCheckout: () => void;
  isProcessingSale: boolean;
  syncSales: LocalSale[];
  onRetrySale: (saleId: string) => void;
}

const MAX_VISIBLE_PAYMENT_METHODS = 3;

function getPaymentIcon(methodName: string) {
  const n = normalizeText(methodName);
  if (isCashPaymentMethod({ id: 0, name: methodName })) return Receipt;
  if (n.includes('debito')) return CreditCard;
  if (n.includes('credito') || n.includes('tarjet')) return CreditCard;
  if (n.includes('transferencia')) return Building2;
  if (n.includes('yape') || n.includes('plin') || n.includes('wallet')) return Wallet;
  if (n.includes('link')) return Link;
  if (n.includes('deposito')) return Banknote;
  return Smartphone;
}

export function POSCheckoutPanel({
  cart,
  cartItemsCount,
  clearCart,
  selectedWarehouseName,
  cajaAbierta,
  quickMode,
  customerSearch,
  setCustomerSearch,
  selectedCustomerId,
  setSelectedCustomerId,
  customerOptions,
  customersLoading,
  customersHasMore,
  customersPage,
  deferredCustomerSearch,
  loadCustomers,
  note,
  setNote,
  updateQuantity,
  removeFromCart,
  formatMoney,
  paymentMethods,
  selectedPaymentMethodId,
  setSelectedPaymentMethodId,
  emitInvoice,
  setEmitInvoice,
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
  syncSales,
  onRetrySale,
}: POSCheckoutPanelProps) {
  const [showCustomerSuggestions, setShowCustomerSuggestions] = useState(false);
  const [noteOpen, setNoteOpen] = useState(false);
  const [customerOpen, setCustomerOpen] = useState(false);
  const [showMorePayments, setShowMorePayments] = useState(false);

  // ── Micro-interaction state ───────────────────────────────────────────────
  const [showSuccess, setShowSuccess] = useState(false);
  const [newItemIds, setNewItemIds] = useState<Set<string>>(new Set());
  const [totalUpdated, setTotalUpdated] = useState(false);
  const prevProcessing = useRef(false);
  const prevCartIds = useRef<Set<string>>(new Set());
  const prevTotal = useRef(total);

  // Success flash: fires when isProcessingSale goes true → false
  useEffect(() => {
    if (prevProcessing.current && !isProcessingSale) {
      setShowSuccess(true);
      setTimeout(() => setShowSuccess(false), 1700);
    }
    prevProcessing.current = isProcessingSale;
  }, [isProcessingSale]);

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

  const selectedCustomer =
    selectedCustomerId !== null ? customerOptions.find((c) => c.id === selectedCustomerId) : null;

  const hasMorePayments = paymentMethods.length > MAX_VISIBLE_PAYMENT_METHODS;
  const visiblePaymentMethods = hasMorePayments && !showMorePayments
    ? paymentMethods.slice(0, MAX_VISIBLE_PAYMENT_METHODS)
    : paymentMethods;

  return (
    <aside className="pos-cart-panel">

      {/* ── Panel header ── */}
      <div className="pos-panel__header">
        <div>
          <h2>Orden</h2>
          {selectedWarehouseName && (
            <p className="pos-panel__warehouse">
              Almacén: {selectedWarehouseName}
            </p>
          )}
        </div>
        <button
          type="button"
          className="pos-icon-btn"
          onClick={clearCart}
          disabled={cart.length === 0}
          title="Vaciar carrito"
          aria-label="Vaciar carrito"
        >
          <Trash2 size={14} />
        </button>
      </div>

      {/* ── Caja cerrada overlay ── */}
      {!cajaAbierta && (
        <div className="pos-caja-closed-banner" role="alert">
          <span>🔴 Caja cerrada — no se puede cobrar</span>
        </div>
      )}

      {/* ── Single scrollable body ── */}
      <div className="pos-cart-body">

        {/* [1] Cart line items */}
        <div className="pos-cart-lines">
          {cart.length === 0 ? (
            <div className="pos-empty-cart">
              <ShoppingCart size={30} strokeWidth={1.5} />
              <p>Orden vacía</p>
              <span>Presiona <kbd>F2</kbd> para buscar productos</span>
            </div>
          ) : (
            cart.map((item) => (
              <div key={item.id} className={`pos-line${newItemIds.has(item.id) ? ' is-new' : ''}`}>
                <div className="pos-line__body">
                  <div className="pos-line__name">{item.name}</div>
                  <div className="pos-line__unit">{formatMoney(toNumber(item.salePrice))} c/u</div>
                </div>
                <div className="pos-line__controls">
                  <div className="pos-line__qty">
                    <button
                      type="button"
                      className={item.quantity === 1 ? 'is-remove' : ''}
                      onClick={() =>
                        item.quantity === 1
                          ? removeFromCart(item.id)
                          : updateQuantity(item.id, item.quantity - 1)
                      }
                      aria-label={item.quantity === 1 ? `Quitar ${item.name}` : 'Restar uno'}
                    >
                      {item.quantity === 1 ? <Trash2 size={11} /> : <Minus size={11} />}
                    </button>
                    <span>{item.quantity}</span>
                    <button
                      type="button"
                      onClick={() => updateQuantity(item.id, item.quantity + 1)}
                      aria-label="Sumar uno"
                    >
                      <Plus size={11} />
                    </button>
                  </div>
                  <strong className="pos-line__total">
                    {formatMoney(toNumber(item.salePrice) * item.quantity)}
                  </strong>
                </div>
              </div>
            ))
          )}
        </div>

        {/* [2] Note — collapsible, hidden in quickMode */}
        {!quickMode && (
          <div className="pos-note-section">
            <button
              type="button"
              className={`pos-note-toggle ${noteOpen || note ? 'is-open' : ''}`}
              onClick={() => setNoteOpen((v) => !v)}
              aria-expanded={noteOpen}
            >
              <MessageSquare size={13} />
              <span>{note ? `Nota: ${note}` : 'Agregar nota interna'}</span>
              <ChevronDown size={12} className="pos-note-toggle__chevron" />
            </button>
            {(noteOpen || note) && (
              <textarea
                className="pos-note-input"
                rows={2}
                placeholder="Nota o referencia interna..."
                value={note}
                onChange={(e) => setNote(e.target.value)}
                autoFocus={noteOpen && !note}
              />
            )}
          </div>
        )}

        {/* [3] Customer — collapsible, hidden in quickMode */}
        {!quickMode && (
          <div className="pos-customer-section">
            <div className={`pos-section-toggle ${customerOpen || selectedCustomer ? 'is-open' : ''}`}>
              <button
                type="button"
                className="pos-section-toggle__btn"
                onClick={() => setCustomerOpen((v) => !v)}
                aria-expanded={customerOpen}
              >
                <User2 size={13} />
                <span>
                  {selectedCustomer
                    ? selectedCustomer.businessName
                    : 'Cliente (opcional)'}
                </span>
                {!selectedCustomer && <ChevronDown size={12} className="pos-note-toggle__chevron" />}
              </button>
              {selectedCustomer && (
                <button
                  type="button"
                  className="pos-section-toggle__clear"
                  onClick={() => {
                    setSelectedCustomerId(null);
                    setCustomerSearch('');
                    setCustomerOpen(false);
                  }}
                  aria-label="Quitar cliente"
                >
                  ×
                </button>
              )}
            </div>

            {(customerOpen || selectedCustomer) && (
              <div className="pos-customer-wrap">
                {!selectedCustomer && (
                  <>
                    <input
                      type="text"
                      placeholder="Buscar cliente por nombre o RUC..."
                      value={customerSearch}
                      onChange={(e) => {
                        setCustomerSearch(e.target.value);
                        setShowCustomerSuggestions(true);
                      }}
                      onFocus={() => setShowCustomerSuggestions(true)}
                      onBlur={() =>
                        setTimeout(() => setShowCustomerSuggestions(false), 120)
                      }
                      aria-label="Cliente"
                      autoComplete="off"
                      className="pos-config-input"
                      autoFocus
                    />
                    {showCustomerSuggestions && customerOptions.length > 0 && (
                      <div
                        className="pos-customer-suggestions"
                        role="listbox"
                        aria-label="Sugerencias"
                      >
                        {customerOptions.slice(0, 8).map((customer) => (
                          <button
                            key={customer.id}
                            type="button"
                            role="option"
                            aria-selected={selectedCustomerId === customer.id}
                            className={`pos-customer-option ${selectedCustomerId === customer.id ? 'is-active' : ''}`}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => {
                              setSelectedCustomerId(customer.id);
                              setShowCustomerSuggestions(false);
                              setCustomerOpen(false);
                            }}
                          >
                            <span>{customer.businessName}</span>
                            {customer.doc && <small>{customer.doc}</small>}
                          </button>
                        ))}
                        {(customersLoading || customersHasMore) && (
                          <button
                            type="button"
                            className="pos-load-more-customers"
                            onClick={() =>
                              loadCustomers(
                                deferredCustomerSearch,
                                customersPage + 1,
                                true,
                              )
                            }
                            disabled={customersLoading || !customersHasMore}
                          >
                            {customersLoading ? 'Buscando...' : 'Ver más'}
                          </button>
                        )}
                      </div>
                    )}
                  </>
                )}
              </div>
            )}
          </div>
        )}

        {/* ── Payment block ── */}
        <div className="pos-payment">

          {/* [4] Payment methods */}
          <div className="pos-payment__section-label">Método de pago</div>
          <div className="pos-payment__methods" role="radiogroup" aria-label="Método de pago">
            {visiblePaymentMethods.map((method) => {
              const Icon = getPaymentIcon(method.name);
              return (
                <label
                  key={method.id}
                  className={`pos-payment-btn ${
                    selectedPaymentMethodId === method.id ? 'is-active' : ''
                  }`}
                  role="radio"
                  aria-checked={selectedPaymentMethodId === method.id}
                >
                  <input
                    type="radio"
                    name="pos-payment-method"
                    checked={selectedPaymentMethodId === method.id}
                    onChange={() => setSelectedPaymentMethodId(method.id)}
                  />
                  <Icon size={14} />
                  <span>{method.name}</span>
                </label>
              );
            })}
            {hasMorePayments && (
              <button
                type="button"
                className={`pos-payment-btn pos-payment-btn--more ${
                  showMorePayments ? 'is-expanded' : ''
                }`}
                onClick={() => setShowMorePayments((v) => !v)}
              >
                <ChevronDown size={14} className={showMorePayments ? 'is-flipped' : ''} />
                <span>{showMorePayments ? 'Menos' : 'Más'}</span>
              </button>
            )}
          </div>

          {/* [5] Invoice toggle + discount */}
          <div className="pos-payment__options">
            <label className="pos-toggle-row">
              <span>Emitir comprobante</span>
              <span className="pos-toggle">
                <input
                  type="checkbox"
                  checked={emitInvoice}
                  onChange={(e) => setEmitInvoice(e.target.checked)}
                />
                <span className="pos-toggle__track" />
              </span>
            </label>

            <div className="pos-discount-row">
              <label className="pos-discount-label" htmlFor="pos-discount">
                <BadgePercent size={13} />
                Descuento (%)
              </label>
              <div className="pos-payment__discount-controls">
                <input
                  id="pos-discount"
                  type="number"
                  min={0}
                  max={100}
                  step={1}
                  value={discountPercent}
                  onChange={(e) =>
                    setDiscountPercent(
                      Math.max(
                        0,
                        Math.min(
                          100,
                          Number.parseFloat(e.target.value || '0') || 0,
                        ),
                      ),
                    )
                  }
                />
                <button
                  type="button"
                  onClick={() => setDiscountPercent(0)}
                  title="Limpiar descuento"
                  aria-label="Limpiar descuento"
                >
                  <Eraser size={13} />
                </button>
              </div>
            </div>
          </div>

          {/* [6] Monto recibido — only for efectivo */}
          {isCashPaymentSelected && (
            <div className="pos-payment__cash">
              <label htmlFor="pos-amount-received">Monto recibido</label>
              <input
                id="pos-amount-received"
                type="number"
                min={0}
                step="0.01"
                placeholder="0.00"
                value={amountReceived}
                onChange={(e) => setAmountReceived(e.target.value)}
                autoFocus
              />
              <div className="pos-quick-amounts">
                {quickCashActions.map((action) => (
                  <button
                    key={action.label}
                    type="button"
                    onClick={() => setAmountReceived(action.value.toFixed(2))}
                    disabled={total <= 0}
                  >
                    {action.label}
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* [7] Summary */}
          <div className="pos-summary">
            <div className="pos-summary__row">
              <span>Subtotal</span>
              <strong>{formatMoney(subtotal)}</strong>
            </div>
            {discountAmount > 0 && (
              <div className="pos-summary__row">
                <span>Descuento</span>
                <strong className="pos-summary__discount">
                  − {formatMoney(discountAmount)}
                </strong>
              </div>
            )}
            <div className="pos-summary__row">
              <span>IGV incluido (18%)</span>
              <strong>{formatMoney(includedTax)}</strong>
            </div>
          </div>

          <div className="pos-sync-section">
            <div className="pos-payment__section-label">Estado de sincronización</div>
            {syncSales.length === 0 ? (
              <div className="pos-sync-section__empty">Sin ventas locales recientes.</div>
            ) : (
              syncSales.slice(0, 6).map((sale) => (
                <SaleSyncStatus key={sale.id} sale={sale} onRetry={onRetrySale} />
              ))
            )}
          </div>
        </div>
      </div>

      {/* ── Always-visible footer: change + total + cobrar ── */}
      <div className="pos-checkout-footer">
        {isCashPaymentSelected && amountReceivedNumber > 0 && (
          <div
            className={`pos-change-row ${
              amountMissing > 0 ? 'is-missing' : 'is-change'
            }`}
          >
            <span>{amountMissing > 0 ? 'Falta' : 'Vuelto'}</span>
            <strong>
              {formatMoney(amountMissing > 0 ? amountMissing : changeAmount)}
            </strong>
          </div>
        )}

          <div className={`pos-total-hero${totalUpdated ? ' is-updated' : ''}`}>
          <span>TOTAL</span>
          <strong>{formatMoney(total)}</strong>
        </div>

        <button
          type="button"
          className="pos-checkout"
          onClick={handleCheckout}
          disabled={checkoutDisabled || !cajaAbierta}
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

      {/* ── Checkout success flash overlay ── */}
      {showSuccess && (
        <div className="pos-checkout-success-flash" aria-live="assertive" role="status">
          <div className="pos-checkout-success-flash__inner">
            <div className="pos-checkout-success-flash__check">✓</div>
            <h2>¡Venta completada!</h2>
          </div>
        </div>
      )}

    </aside>
  );
}

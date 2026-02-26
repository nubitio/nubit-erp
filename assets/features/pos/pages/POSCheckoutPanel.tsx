import React, { useState } from 'react';
import {
  BadgePercent,
  CreditCard,
  Eraser,
  Receipt,
  ShoppingCart,
  Smartphone,
  Trash2,
} from 'lucide-react';
import type { CartItem, CustomerOption, PaymentMethodOption, WarehouseOption } from './types';
import { isCashPaymentMethod, normalizeText, toNumber } from './posHelpers';

interface POSCheckoutPanelProps {
  cart: CartItem[];
  cartItemsCount: number;
  clearCart: () => void;
  warehouses: WarehouseOption[];
  selectedWarehouseId: string;
  setSelectedWarehouseId: (value: string) => void;
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
}

export function POSCheckoutPanel({
  cart,
  cartItemsCount,
  clearCart,
  warehouses,
  selectedWarehouseId,
  setSelectedWarehouseId,
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
}: POSCheckoutPanelProps) {
  const [showCustomerSuggestions, setShowCustomerSuggestions] = useState(false);

  return (
    <aside className="pos-cart-panel">
      <div className="pos-panel">
        <div className="pos-panel__header">
          <div>
            <h2>Carrito</h2>
            <p>{cartItemsCount} productos agregados</p>
          </div>
          <button
            type="button"
            className="pos-icon-btn"
            onClick={clearCart}
            disabled={cart.length === 0}
            title="Vaciar carrito"
          >
            <Trash2 size={16} />
          </button>
        </div>

        <div className="pos-customer-fields">
          <select
            value={selectedWarehouseId}
            onChange={(e) => setSelectedWarehouseId(e.target.value)}
            aria-label="Almacen"
          >
            <option value="">Selecciona almacen</option>
            {warehouses.map((warehouse) => (
              <option key={warehouse.id} value={warehouse.id}>
                {warehouse.name}
              </option>
            ))}
          </select>
          <input
            type="text"
            placeholder="Buscar y seleccionar cliente..."
            value={customerSearch}
            onChange={(e) => {
              setCustomerSearch(e.target.value);
              setShowCustomerSuggestions(true);
            }}
            onFocus={() => setShowCustomerSuggestions(true)}
            onBlur={() => setTimeout(() => setShowCustomerSuggestions(false), 120)}
            aria-label="Cliente"
          />
          {showCustomerSuggestions && customerOptions.length > 0 && (
            <div className="pos-customer-suggestions" role="listbox" aria-label="Sugerencias de clientes">
              {customerOptions.slice(0, 8).map((customer) => (
                <button
                  key={customer.id}
                  type="button"
                  className={`pos-customer-option ${selectedCustomerId === customer.id ? 'is-active' : ''}`}
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => {
                    setSelectedCustomerId(customer.id);
                    setShowCustomerSuggestions(false);
                  }}
                >
                  <span>{customer.businessName}</span>
                  {customer.doc && <small>{customer.doc}</small>}
                </button>
              ))}
            </div>
          )}
          {(customersLoading || customersHasMore) && (
            <button
              type="button"
              onClick={() => loadCustomers(deferredCustomerSearch, customersPage + 1, true)}
              disabled={customersLoading || !customersHasMore}
            >
              {customersLoading ? 'Buscando clientes...' : 'Cargar más clientes'}
            </button>
          )}
          <input
            type="text"
            placeholder="Nota / referencia"
            value={note}
            onChange={(e) => setNote(e.target.value)}
          />
        </div>

        <div className="pos-cart-lines">
          {cart.length === 0 ? (
            <div className="pos-empty-cart">
              <ShoppingCart size={26} />
              <p>El carrito está vacío</p>
              <span>Selecciona productos para iniciar la venta.</span>
            </div>
          ) : (
            cart.map((item) => (
              <div key={item.id} className="pos-line">
                <img
                  src={item.photo?.path || `https://ui-avatars.com/api/?name=${encodeURIComponent(item.name)}`}
                  alt={item.name}
                  className="pos-line__image"
                />
                <div className="pos-line__content">
                  <div className="pos-line__title">{item.name}</div>
                  <div className="pos-line__price">{formatMoney(toNumber(item.salePrice))}</div>
                  <div className="pos-line__qty">
                    <button type="button" onClick={() => updateQuantity(item.id, item.quantity - 1)}>
                      -
                    </button>
                    <span>{item.quantity}</span>
                    <button type="button" onClick={() => updateQuantity(item.id, item.quantity + 1)}>
                      +
                    </button>
                  </div>
                </div>
                <div className="pos-line__side">
                  <strong>{formatMoney(toNumber(item.salePrice) * item.quantity)}</strong>
                  <button type="button" onClick={() => removeFromCart(item.id)}>
                    Quitar
                  </button>
                </div>
              </div>
            ))
          )}
        </div>
      </div>

      <div className="pos-panel">
        <div className="pos-payment">
          <div className="pos-payment__methods">
            {paymentMethods.map((method) => {
              const name = normalizeText(method.name);
              const Icon =
                isCashPaymentMethod(method) ? Receipt : name.includes('tarjet') ? CreditCard : Smartphone;

              return (
                <button
                  key={method.id}
                  type="button"
                  className={`pos-payment-btn ${selectedPaymentMethodId === method.id ? 'is-active' : ''}`}
                  onClick={() => setSelectedPaymentMethodId(method.id)}
                >
                  <Icon size={16} />
                  {method.name}
                </button>
              );
            })}
          </div>

          <div className="pos-payment__discount">
            <label className="pos-inline-check">
              <input type="checkbox" checked={emitInvoice} onChange={(e) => setEmitInvoice(e.target.checked)} />
              <span>Emitir comprobante</span>
            </label>
            <label htmlFor="pos-discount">
              <BadgePercent size={15} /> Descuento (%)
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
                  setDiscountPercent(Math.max(0, Math.min(100, Number.parseFloat(e.target.value || '0') || 0)))
                }
              />
              <button type="button" onClick={() => setDiscountPercent(0)} title="Limpiar descuento">
                <Eraser size={14} />
              </button>
            </div>
          </div>

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

          <div className="pos-summary">
            <div className="pos-summary__row">
              <span>Subtotal</span>
              <strong>{formatMoney(subtotal)}</strong>
            </div>
            <div className="pos-summary__row">
              <span>Descuento</span>
              <strong>- {formatMoney(discountAmount)}</strong>
            </div>
            <div className="pos-summary__row">
              <span>IGV incluido (18%)</span>
              <strong>{formatMoney(includedTax)}</strong>
            </div>
            <div className="pos-summary__row is-total">
              <span>Total a cobrar</span>
              <strong>{formatMoney(total)}</strong>
            </div>
            {isCashPaymentSelected && (
              <>
                <div className="pos-summary__row">
                  <span>Recibido</span>
                  <strong>{formatMoney(amountReceivedNumber)}</strong>
                </div>
                <div className={`pos-summary__row ${amountMissing > 0 ? 'is-warning' : 'is-change'}`}>
                  <span>{amountMissing > 0 ? 'Falta' : 'Vuelto'}</span>
                  <strong>{formatMoney(amountMissing > 0 ? amountMissing : changeAmount)}</strong>
                </div>
              </>
            )}
          </div>

          <button type="button" className="pos-checkout" onClick={handleCheckout} disabled={checkoutDisabled}>
            {isProcessingSale ? 'Procesando...' : `Cobrar ${formatMoney(total)}`}
          </button>
        </div>
      </div>
    </aside>
  );
}

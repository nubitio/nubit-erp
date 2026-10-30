import React, { useId, useMemo, useState } from 'react';
import { Button, Drawer } from '@nubitio/ui';
import {
  createSinglePaymentLine,
  paymentsMatchTotal,
  paymentsWithinTotal,
  remainingPaymentAmount,
  summarizePaymentLines,
  updatePaymentLineMethod,
  type SalePaymentLineDraft,
} from '@shared/lib/salePayments';
import type { PaymentMethodOption } from '@shared/pos/types';
import { SplitPaymentsEditor } from './SplitPaymentsEditor';
import {
  compactPaymentMethodName,
  paymentMethodIcon,
  primaryPaymentMethods,
} from './paymentMethodPresentation';
import './SplitPaymentsField.scss';
import './SplitPaymentsEditor.scss';

export interface SplitPaymentsFieldProps {
  paymentMethods: PaymentMethodOption[];
  total: number;
  currency?: string;
  value: SalePaymentLineDraft[];
  onChange: (lines: SalePaymentLineDraft[]) => void;
  variant?: 'admin' | 'pos';
  /** Inline form (admin) or compact trigger + drawer (POS). */
  layout?: 'inline' | 'drawer';
  /** "Venta a crédito": accepts a sum below total instead of requiring an exact match. */
  allowPartial?: boolean;
  /** Limit the first row; the remaining API methods stay available via an explicit expansion. */
  quickMethodsLimit?: number;
}

export function SplitPaymentsField({
  paymentMethods,
  total,
  currency = 'PEN',
  value,
  onChange,
  variant = 'admin',
  layout = 'inline',
  allowPartial = false,
  quickMethodsLimit,
}: SplitPaymentsFieldProps) {
  const quickGroupName = useId();
  const [moreMethods, setMoreMethods] = useState(false);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const summary = useMemo(
    () => summarizePaymentLines(value, paymentMethods),
    [paymentMethods, value],
  );
  const primaryLine = value[0];
  const isBalanced = allowPartial
    ? paymentsWithinTotal(value, total)
    : paymentsMatchTotal(value, total);
  const remaining = remainingPaymentAmount(value, total);

  const compactMethods = quickMethodsLimit
    ? primaryPaymentMethods(paymentMethods, quickMethodsLimit)
    : paymentMethods;
  const selectedMethod = paymentMethods.find((method) => method.id === primaryLine?.methodId);
  const quickMethods =
    moreMethods || !quickMethodsLimit
      ? paymentMethods
      : selectedMethod && !compactMethods.some((method) => method.id === selectedMethod.id)
        ? [...compactMethods.slice(0, Math.max(0, quickMethodsLimit - 1)), selectedMethod]
        : compactMethods;

  const handleQuickMethod = (methodId: number) => {
    if (value.length <= 1) {
      onChange([createSinglePaymentLine(methodId, total)]);
      return;
    }

    onChange(updatePaymentLineMethod(value, 0, methodId));
  };

  if (layout === 'inline') {
    return (
      <SplitPaymentsEditor
        paymentMethods={paymentMethods}
        total={total}
        currency={currency}
        value={value}
        onChange={onChange}
        variant={variant}
        allowPartial={allowPartial}
      />
    );
  }

  return (
    <>
      <div className={`split-payments-field split-payments-field--${variant}`}>
        {variant === 'pos' && (
          <div className="split-payments-field__heading">
            <h3>Método de pago</h3>
            {quickMethodsLimit && paymentMethods.length > quickMethodsLimit && (
              <button
                type="button"
                onClick={() => setMoreMethods((open) => !open)}
                aria-expanded={moreMethods}
              >
                {moreMethods ? 'Menos métodos' : 'Otros métodos'}
              </button>
            )}
          </div>
        )}
        <div
          className="split-payments-field__methods"
          role="radiogroup"
          aria-label="Método de pago rápido"
        >
          {quickMethods.map((method) => (
            <label
              key={method.id}
              className={`split-payments-field__method ${
                primaryLine?.methodId === method.id ? 'is-active' : ''
              }`}
            >
              <input
                type="radio"
                aria-label={method.name}
                name={quickGroupName}
                checked={primaryLine?.methodId === method.id}
                onChange={() => handleQuickMethod(method.id)}
              />
              {variant === 'pos' && (
                <i className={`ph ${paymentMethodIcon(method)}`} aria-hidden="true" />
              )}
              <span>
                {quickMethodsLimit && !moreMethods ? compactPaymentMethodName(method) : method.name}
              </span>
            </label>
          ))}
        </div>

        <button
          type="button"
          className={`split-payments-field__trigger${
            !isBalanced ? ' split-payments-field__trigger--warning' : ''
          }`}
          onClick={() => setDrawerOpen(true)}
          aria-label="Configurar pagos"
          data-testid="pos-payments-trigger"
        >
          <span className="split-payments-field__trigger-leading" aria-hidden="true">
            <i className="ph ph-wallet" />
          </span>
          <span className="split-payments-field__trigger-body">
            <span className="split-payments-field__trigger-label">Detalle de pagos</span>
            <span className="split-payments-field__trigger-value">{summary.label}</span>
          </span>
          <span className="split-payments-field__trigger-badges">
            {summary.isSplit && (
              <span className="split-payments-field__trigger-badge">Dividido</span>
            )}
            {!isBalanced && (
              <span className="split-payments-field__trigger-badge is-warning">
                Falta S/ {remaining.toFixed(2)}
              </span>
            )}
          </span>
          <i className="ph ph-caret-right split-payments-field__trigger-icon" aria-hidden="true" />
        </button>
      </div>

      <Drawer
        isOpen={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title="Configurar pagos"
        width={440}
        scrim="overlay"
        className="split-payments-drawer-panel"
      >
        <div className="split-payments-drawer">
          <div className={`split-payments-drawer__hero${!isBalanced ? ' is-warning' : ''}`}>
            <div className="split-payments-drawer__hero-copy">
              <span className="split-payments-drawer__hero-label">Total a cobrar</span>
              <strong className="split-payments-drawer__hero-amount">
                {total.toLocaleString('es-PE', { style: 'currency', currency })}
              </strong>
            </div>
            <span
              className={`split-payments-drawer__status${
                isBalanced ? ' is-balanced' : ' is-pending'
              }`}
            >
              {isBalanced ? 'Cuadrado' : `Falta S/ ${remaining.toFixed(2)}`}
            </span>
          </div>

          <SplitPaymentsEditor
            paymentMethods={paymentMethods}
            total={total}
            currency={currency}
            value={value}
            onChange={onChange}
            variant={variant}
            showMethodPicker={false}
            allowPartial={allowPartial}
          />

          <div className="split-payments-drawer__actions">
            <Button
              variant="primary"
              type="button"
              onClick={() => setDrawerOpen(false)}
              disabled={!isBalanced}
            >
              Listo
            </Button>
          </div>
        </div>
      </Drawer>
    </>
  );
}

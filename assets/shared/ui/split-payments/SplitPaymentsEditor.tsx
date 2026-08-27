import React, { useEffect, useMemo, useState } from 'react';
import NumberUtils from '@shared/lib/NumberUtils';
import {
  addPaymentLine,
  createSinglePaymentLine,
  enableSplitPaymentLines,
  parseMoneyInput,
  paymentsMatchTotal,
  paymentsWithinTotal,
  remainingPaymentAmount,
  removePaymentLine,
  roundMoney,
  sumPaymentAmounts,
  updatePaymentLineAmount,
  updatePaymentLineMethod,
  type SalePaymentLineDraft,
} from '@shared/lib/salePayments';
import { isCashPaymentMethod } from '@shared/pos/helpers';
import type { PaymentMethodOption } from '@shared/pos/types';
import './SplitPaymentsEditor.scss';

export interface SplitPaymentsEditorProps {
  paymentMethods: PaymentMethodOption[];
  total: number;
  currency?: string;
  value: SalePaymentLineDraft[];
  onChange: (lines: SalePaymentLineDraft[]) => void;
  /** Compact layout for POS footer. */
  variant?: 'admin' | 'pos';
  /** Hide quick method chips (e.g. when shown outside a drawer). */
  showMethodPicker?: boolean;
  /** "Venta a crédito": accepts a sum below total instead of requiring an exact match. */
  allowPartial?: boolean;
}

const formatMoney = (value: number, currency: string) =>
  NumberUtils.formatCurrency(value, currency);

const TENDER_BILLS = [10, 20, 50, 100, 200];

export const buildTenderSuggestions = (amount: number): number[] => {
  const options = new Set<number>([roundMoney(amount)]);
  const nextTen = Math.ceil(amount / 10) * 10;
  if (nextTen > amount) {
    options.add(nextTen);
  }
  TENDER_BILLS.forEach((bill) => {
    if (bill >= amount) {
      options.add(bill);
    }
  });
  return [...options].sort((a, b) => a - b);
};

export function SplitPaymentsEditor({
  paymentMethods,
  total,
  currency = 'PEN',
  value,
  onChange,
  variant = 'admin',
  showMethodPicker = true,
  allowPartial = false,
}: SplitPaymentsEditorProps) {
  const [splitEnabled, setSplitEnabled] = useState(value.length > 1);

  useEffect(() => {
    if (value.length > 1) {
      setSplitEnabled(true);
    }
  }, [value.length]);

  const lines = splitEnabled
    ? value
    : [value[0] ?? createSinglePaymentLine(null, total)];
  const primaryLine = lines[0] ?? createSinglePaymentLine(null, total);
  const assigned = sumPaymentAmounts(lines);
  const remaining = remainingPaymentAmount(value, total);
  const overAssigned = Math.max(0, roundMoney(assigned - roundMoney(total)));
  const isBalanced = allowPartial ? paymentsWithinTotal(value, total) : paymentsMatchTotal(value, total);
  const canAddLine = splitEnabled && value.length < paymentMethods.length;
  const showActions = splitEnabled && value.length > 1;

  const cashEntries = lines
    .map((line, index) => ({
      line,
      index,
      method: paymentMethods.find((item) => item.id === line.methodId) ?? null,
    }))
    .filter(
      ({ line, method }) =>
        isCashPaymentMethod(method) && parseMoneyInput(line.amount) > 0,
    );

  const methodOptionsForLine = useMemo(
    () => (lineIndex: number) => {
      const usedElsewhere = new Set(
        value
          .map((line, index) => (index === lineIndex ? null : line.methodId))
          .filter((id): id is number => id != null),
      );

      return paymentMethods.filter((method) => !usedElsewhere.has(method.id));
    },
    [paymentMethods, value],
  );

  const setSinglePayment = (methodId: number | null) => {
    onChange([createSinglePaymentLine(methodId, total)]);
  };

  const enableSplitPayment = () => {
    onChange(enableSplitPaymentLines(primaryLine.methodId, paymentMethods, total));
    setSplitEnabled(true);
  };

  const disableSplitPayment = () => {
    onChange([createSinglePaymentLine(primaryLine.methodId, total)]);
    setSplitEnabled(false);
  };

  const updatePrimaryMethod = (methodId: number) => {
    if (!splitEnabled) {
      setSinglePayment(methodId);
      return;
    }

    onChange(updatePaymentLineMethod(value, 0, methodId));
  };

  const updateLineTendered = (index: number, nextTendered: string) => {
    onChange(
      value.map((line, lineIndex) =>
        lineIndex === index ? { ...line, tenderedAmount: nextTendered } : line,
      ),
    );
  };

  if (paymentMethods.length === 0) {
    return <p className="split-payments__empty">No hay métodos de pago configurados.</p>;
  }

  const toggleSplit = () => (splitEnabled ? disableSplitPayment() : enableSplitPayment());

  return (
    <div className={`split-payments split-payments--${variant}`}>
      {showMethodPicker && (
        <div className="split-payments__methods" role="radiogroup" aria-label="Método de pago principal">
          {paymentMethods.map((method) => (
            <label
              key={method.id}
              className={`split-payments__method ${primaryLine.methodId === method.id ? 'is-active' : ''}`}
            >
              <input
                type="radio"
                name="split-payments-primary"
                checked={primaryLine.methodId === method.id}
                onChange={() => updatePrimaryMethod(method.id)}
              />
              <span>{method.name}</span>
            </label>
          ))}
        </div>
      )}

      <button
        type="button"
        className={`split-payments__split-card${splitEnabled ? ' is-active' : ''}`}
        onClick={toggleSplit}
        role="switch"
        aria-checked={splitEnabled}
        aria-label="Pago dividido"
      >
        <span className="split-payments__split-card-icon" aria-hidden="true">
          <i className="ph ph-arrows-split" />
        </span>
        <span className="split-payments__split-card-copy">
          <strong>Pago dividido</strong>
          <span>{splitEnabled ? 'Varios métodos en una venta' : 'Un solo método de pago'}</span>
        </span>
        <span className={`split-payments__split-switch${splitEnabled ? ' is-on' : ''}`} aria-hidden="true">
          <span className="split-payments__split-switch-thumb" />
        </span>
      </button>

      <div className="split-payments__sheet-header">
        <span className="split-payments__sheet-title">Detalle</span>
        {splitEnabled && (
          <span className="split-payments__sheet-meta">
            {lines.length} {lines.length === 1 ? 'línea' : 'líneas'}
          </span>
        )}
      </div>

      <div className="split-payments__sheet" role="region" aria-label="Líneas de pago">
        <table className="split-payments__table">
          <thead>
            <tr>
              <th scope="col">Método</th>
              <th scope="col" className="split-payments__col-money">Monto</th>
              {showActions && <th scope="col" className="split-payments__col-action" />}
            </tr>
          </thead>
          <tbody>
            {lines.map((line, index) => {
              const method = paymentMethods.find((item) => item.id === line.methodId) ?? null;
              const isLastSplitLine = splitEnabled && index === lines.length - 1 && lines.length > 1;
              const options = methodOptionsForLine(index);
              const amountValue = splitEnabled || allowPartial
                ? line.amount
                : roundMoney(total).toFixed(2);

              return (
                <tr key={`payment-line-${index}`}>
                  <td>
                    {splitEnabled ? (
                      <select
                        className="split-payments__cell-input split-payments__cell-select"
                        value={line.methodId ?? ''}
                        onChange={(event) =>
                          onChange(updatePaymentLineMethod(value, index, Number(event.target.value)))
                        }
                        aria-label={`Método de pago ${index + 1}`}
                      >
                        <option value="" disabled>
                          Seleccionar
                        </option>
                        {options.map((option) => (
                          <option key={option.id} value={option.id}>
                            {option.name}
                          </option>
                        ))}
                      </select>
                    ) : (
                      <span className="split-payments__cell-text">
                        {method?.name ?? '—'}
                      </span>
                    )}
                  </td>
                  <td className="split-payments__col-money">
                    {isLastSplitLine ? (
                      <span
                        className="split-payments__cell-amount is-computed"
                        title="Calculado automáticamente"
                      >
                        {formatMoney(parseMoneyInput(line.amount), currency)}
                      </span>
                    ) : (
                      <input
                        type="number"
                        className="split-payments__cell-input split-payments__cell-amount"
                        min={0.01}
                        max={total}
                        step="0.01"
                        value={amountValue}
                        readOnly={!splitEnabled && !allowPartial}
                        onChange={(event) =>
                          onChange(updatePaymentLineAmount(value, index, event.target.value, total))
                        }
                        aria-label={`Monto ${method?.name ?? `pago ${index + 1}`}`}
                      />
                    )}
                  </td>
                  {showActions && (
                    <td className="split-payments__col-action">
                      {lines.length > 2 && (
                        <button
                          type="button"
                          className="split-payments__row-remove"
                          onClick={() => onChange(removePaymentLine(value, index, total))}
                          aria-label={`Quitar pago ${index + 1}`}
                        >
                          <i className="ph ph-trash" aria-hidden="true" />
                        </button>
                      )}
                    </td>
                  )}
                </tr>
              );
            })}
          </tbody>
          <tfoot>
            {splitEnabled && !isBalanced && (
              <tr>
                <td className="split-payments__footer-label">Asignado</td>
                <td className="split-payments__col-money split-payments__footer-value">
                  {formatMoney(assigned, currency)}
                </td>
                {showActions && <td />}
              </tr>
            )}
            <tr>
              <td className="split-payments__footer-label is-strong">
                Total venta
              </td>
              <td className="split-payments__col-money split-payments__footer-value is-strong">
                {formatMoney(total, currency)}
              </td>
              {showActions && <td />}
            </tr>
            {splitEnabled && !isBalanced && (
              <tr className="is-warning">
                <td className="split-payments__footer-label">
                  {remaining > 0 ? 'Falta asignar' : 'Sobra'}
                </td>
                <td className="split-payments__col-money split-payments__footer-value">
                  {formatMoney(remaining > 0 ? remaining : overAssigned, currency)}
                </td>
                {showActions && <td />}
              </tr>
            )}
          </tfoot>
        </table>
      </div>

      {canAddLine && (
        <button
          type="button"
          className="split-payments__add-btn"
          onClick={() => onChange(addPaymentLine(value, paymentMethods, total))}
        >
          <i className="ph ph-plus" aria-hidden="true" />
          Agregar fila
        </button>
      )}

      {cashEntries.map(({ line, index }) => {
        const cashAmount = splitEnabled
          ? parseMoneyInput(line.amount)
          : roundMoney(total);
        const tendered = parseMoneyInput(line.tenderedAmount ?? line.amount);
        const change = roundMoney(tendered - cashAmount);
        const insufficient = change < -0.009;
        const inputId = `split-payments-cash-${index}`;

        return (
          <div
            key={inputId}
            className={`split-payments__cash${insufficient ? ' is-invalid' : ''}`}
          >
            <div className="split-payments__cash-row">
              <span className="split-payments__cash-icon" aria-hidden="true">
                <i className="ph ph-money" />
              </span>
              <div className="split-payments__cash-copy">
                <label htmlFor={inputId}>Efectivo recibido</label>
                <span
                  className={`split-payments__cash-hint${
                    insufficient ? ' is-warning' : change > 0 ? ' is-change' : ''
                  }`}
                >
                  {insufficient
                    ? 'Menor al monto a pagar'
                    : `Vuelto ${formatMoney(Math.max(0, change), currency)}`}
                </span>
              </div>
              <input
                id={inputId}
                type="number"
                inputMode="decimal"
                className="split-payments__cash-input"
                min={cashAmount}
                step="0.01"
                value={line.tenderedAmount ?? line.amount}
                onChange={(event) => updateLineTendered(index, event.target.value)}
              />
            </div>
            <div className="split-payments__cash-chips" role="group" aria-label="Montos rápidos">
              {buildTenderSuggestions(cashAmount).map((suggestion) => (
                <button
                  key={suggestion}
                  type="button"
                  className={`split-payments__cash-chip${
                    Math.abs(tendered - suggestion) < 0.005 ? ' is-active' : ''
                  }`}
                  onClick={() => updateLineTendered(index, suggestion.toFixed(2))}
                >
                  {suggestion === roundMoney(cashAmount)
                    ? 'Exacto'
                    : formatMoney(suggestion, currency)}
                </button>
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}

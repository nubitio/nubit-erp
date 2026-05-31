import React from 'react';
import type { SaleTotals } from '@shared/domain/sale.types';
import './SaleSummary.scss';

export interface SaleSummaryProps {
  /** Partial or full SaleTotals. Only the fields needed for display are used. */
  totals: Pick<SaleTotals, 'subtotal' | 'discount' | 'gravadoBase' | 'exoneradoBase' | 'inafectoBase' | 'igv' | 'total'>;
  /** Currency code for formatting (default: PEN). */
  currency?: string;
  /** Current IGV rate as decimal (e.g. 0.18 or 0.105). Used for the label only. */
  igvRate?: number;
  /** Show the three tax-base rows when there are non-gravado lines. */
  showTaxBreakdown?: boolean;
  /** Use the compact layout (used by POS). */
  compact?: boolean;
  /** Money formatter (e.g. from NumberUtils.formatCurrency). */
  formatMoney: (value: number) => string;
  /** Optional extra class. */
  className?: string;
}

export const SaleSummary: React.FC<SaleSummaryProps> = ({
  totals,
  currency = 'PEN',
  igvRate,
  showTaxBreakdown = false,
  compact = false,
  formatMoney,
  className = '',
}) => {
  const hasNonGravado =
    (totals.exoneradoBase ?? 0) > 0 || (totals.inafectoBase ?? 0) > 0;

  const igvLabel =
    typeof igvRate === 'number'
      ? `IGV (${(igvRate * 100).toFixed(1).replace(/\.0$/, '')}%)`
      : 'IGV';

  if (compact) {
    return (
      <div className={`sale-summary sale-summary--compact ${className}`}>
        <div className="sale-summary__row">
          <span>Subtotal</span>
          <strong>{formatMoney(totals.subtotal)}</strong>
        </div>
        {totals.discount.amount > 0 && (
          <div className="sale-summary__row sale-summary__discount">
            <span>Descuento</span>
            <strong>− {formatMoney(totals.discount.amount)}</strong>
          </div>
        )}
        <div className="sale-summary__row">
          <span>{igvLabel}</span>
          <strong>{formatMoney(totals.igv)}</strong>
        </div>
        <div className="sale-summary__row sale-summary__total">
          <span>TOTAL</span>
          <strong>{formatMoney(totals.total)}</strong>
        </div>
      </div>
    );
  }

  // Full layout (for Admin editor, reports, etc.)
  return (
    <div className={`sale-summary ${className}`}>
      <div className="sale-summary__row">
        <span>Subtotal</span>
        <strong>{formatMoney(totals.subtotal)}</strong>
      </div>

      {totals.discount.amount > 0 && (
        <div className="sale-summary__row sale-summary__discount">
          <span>Descuento ({totals.discount.percent}%)</span>
          <strong>− {formatMoney(totals.discount.amount)}</strong>
        </div>
      )}

      {/* Tax breakdown (only shown when there are mixed tax types) */}
      {showTaxBreakdown && hasNonGravado ? (
        <>
          <div className="sale-summary__row">
            <span>Base gravado</span>
            <strong>{formatMoney(totals.gravadoBase ?? 0)}</strong>
          </div>
          {(totals.exoneradoBase ?? 0) > 0 && (
            <div className="sale-summary__row">
              <span>Base exonerado</span>
              <strong>{formatMoney(totals.exoneradoBase)}</strong>
            </div>
          )}
          {(totals.inafectoBase ?? 0) > 0 && (
            <div className="sale-summary__row">
              <span>Base inafecto / export</span>
              <strong>{formatMoney(totals.inafectoBase)}</strong>
            </div>
          )}
        </>
      ) : (
        <div className="sale-summary__row">
          <span>{igvLabel}</span>
          <strong>{formatMoney(totals.igv)}</strong>
        </div>
      )}

      <div className="sale-summary__row sale-summary__total">
        <span>TOTAL</span>
        <strong>{formatMoney(totals.total)}</strong>
      </div>
    </div>
  );
};

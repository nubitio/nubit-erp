import { AppDialog, Button, FormField, TextField } from '@nubitio/ui';
import { useCallback, useEffect, useRef, useState } from 'react';
import NumberUtils from '@shared/lib/NumberUtils';
import { useEvents } from '@nubitio/core';

import './DiscountDialog.scss';

export enum DiscountDialogEvent {
  OPEN = 'discount:dialog:open',
  APPLY = 'discount:apply',
}

/** Payload sent when opening the dialog. */
export interface DiscountDialogOpenPayload {
  /** Base amount (e.g. grandTotal for global, qty × unitPrice for item). */
  total: number;
  /**
   * Discount percent in [0, 100].
   * E.g. pass `10` to pre-fill "10%". Never pass fractions.
   */
  discountPercent: number;
  /** Optional title override. Defaults to "Descuento". */
  title?: string;
  /**
   * Opaque context forwarded unchanged in the APPLY event.
   * Use this to identify which item row triggered the dialog.
   */
  context?: unknown;
}

/** Payload emitted when the user confirms. */
export interface DiscountDialogApplyPayload {
  /**
   * Discount percent in [0, 100].
   * E.g. `10` means 10%. Use directly as a form field value — no conversion needed.
   */
  discountPercent: number;
  /** Absolute discount amount (baseValue × discountPercent / 100). */
  discountAmount: number;
  /**
   * The same context object that was passed in the OPEN payload.
   * `undefined` for global (non-item) discounts.
   */
  context?: unknown;
}

type LastChanged = 'percent' | 'amount' | 'none';

export const DiscountDialog = () => {
  const [on, emit] = useEvents();
  const [visible, setVisible] = useState(false);
  const [title, setTitle] = useState('Descuento');
  const [total, setTotal] = useState(0.0);
  // discountPercent is stored as 0–100 (percent), never as a fraction.
  const [discountPercent, setDiscountPercent] = useState(0.0);
  const [discountAmount, setDiscountAmount] = useState(0.0);
  const contextRef = useRef<unknown>(undefined);

  /**
   * Tracks which field the user last edited so the bidirectional sync
   * effects don't chase each other in a loop.
   */
  const lastChanged = useRef<LastChanged>('none');

  const emitRef = useRef(emit);
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix: keep ref current with latest closure
  emitRef.current = emit;
  const onRef = useRef(on);
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix: keep ref current with latest closure
  onRef.current = on;

  const onApply = useCallback((pct: number, amt: number) => {
    setVisible(false);
    const payload: DiscountDialogApplyPayload = {
      discountPercent: pct,
      discountAmount: amt,
      context: contextRef.current,
    };
    emitRef.current<DiscountDialogApplyPayload>(DiscountDialogEvent.APPLY, payload);
  }, []);

  // When percent changes (user edited percent field), recompute amount.
  useEffect(() => {
    if (lastChanged.current !== 'percent') return;
    const clamped = Math.min(100, Math.max(0, discountPercent));
    queueMicrotask(() => setDiscountAmount(Number(((clamped / 100) * total).toFixed(2))));
  }, [discountPercent, total]);

  // When amount changes (user edited amount field), recompute percent.
  useEffect(() => {
    if (lastChanged.current !== 'amount') return;
    if (total > 0) {
      const clamped = Math.min(total, Math.max(0, discountAmount));
      queueMicrotask(() => setDiscountPercent(Number(((clamped / total) * 100).toFixed(4))));
    }
  }, [discountAmount, total]);

  useEffect(() => {
    onRef.current<DiscountDialogOpenPayload>(DiscountDialogEvent.OPEN, (data) => {
      lastChanged.current = 'none';
      contextRef.current = data.context;
      setTitle(data.title ?? 'Descuento');
      setTotal(data.total);
      setDiscountPercent(data.discountPercent);
      setDiscountAmount(Number(((data.discountPercent / 100) * data.total).toFixed(2)));
      setVisible(true);
    });
  }, []);

  const handlePercentInput = (e: React.ChangeEvent<HTMLInputElement>) => {
    lastChanged.current = 'percent';
    const raw = parseFloat(e.target.value);
    // Input is already in 0–100 scale — store as-is.
    setDiscountPercent(isNaN(raw) ? 0 : raw);
  };

  const handleAmountInput = (e: React.ChangeEvent<HTMLInputElement>) => {
    lastChanged.current = 'amount';
    const raw = parseFloat(e.target.value);
    setDiscountAmount(isNaN(raw) ? 0 : raw);
  };

  return (
    <AppDialog
      open={visible}
      title={title}
      onClose={() => setVisible(false)}
      maxWidth={420}
      rootClassName="discount-dialog-root"
      showCloseButton={true}
      restoreFocus={false}
      footer={
        <Button
          variant="primary"
          onClick={() => onApply(discountPercent, discountAmount)}
        >
          Aceptar
        </Button>
      }
    >
      <h3 className="discount-dialog__total">
        {NumberUtils.formatCurrency(total - discountAmount)}
      </h3>
      {discountAmount > 0 && (
        <p className="discount-dialog__previous-total">
          {NumberUtils.formatCurrency(total)}
        </p>
      )}
      <div className="discount-dialog__form">
        <FormField label="Descuento %" htmlFor="discount-percent">
          <TextField
            id="discount-percent"
            type="number"
            min={0}
            max={100}
            step={1}
            value={Number(discountPercent.toFixed(2))}
            onChange={handlePercentInput}
          />
        </FormField>
        <FormField label="Descuento S/" htmlFor="discount-amount">
          <TextField
            id="discount-amount"
            type="number"
            min={0}
            max={total}
            step={0.01}
            value={Number(discountAmount.toFixed(2))}
            onChange={handleAmountInput}
          />
        </FormField>
      </div>
    </AppDialog>
  );
};

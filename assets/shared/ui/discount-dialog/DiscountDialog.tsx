import { NumberBox, Popup } from 'devextreme-react';
import { ToolbarItem } from 'devextreme-react/popup';
import { useCallback, useEffect, useRef, useState } from 'react';
import NumberUtils from '@shared/lib/NumberUtils';
import { useEvents } from '@core/event/EventHook';

export enum DiscountDialogEvent {
  OPEN = 'discount:dialog:open',
  APPLY = 'discount:apply',
}

/** Payload sent when opening the dialog. */
export interface DiscountDialogOpenPayload {
  /** Base amount (e.g. grandTotal for global, qty × unitPrice for item). */
  total: number;
  /**
   * Discount percent expressed as a fraction in [0, 1].
   * For item-level discounts pass `discountPercent / 100`.
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
   * Discount percent as a fraction in [0, 1].
   * Multiply by 100 to get the percentage value.
   */
  discountPercent: number;
  /** Absolute discount amount (baseValue × discountPercent). */
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
    const clamped = Math.min(1, Math.max(0, discountPercent));
    setDiscountAmount(Number((clamped * total).toFixed(2)));
  }, [discountPercent, total]);

  // When amount changes (user edited amount field), recompute percent.
  useEffect(() => {
    if (lastChanged.current !== 'amount') return;
    if (total > 0) {
      const clamped = Math.min(total, Math.max(0, discountAmount));
      setDiscountPercent(Number((clamped / total).toFixed(6)));
    }
  }, [discountAmount, total]);

  useEffect(() => {
    onRef.current<DiscountDialogOpenPayload>(DiscountDialogEvent.OPEN, (data) => {
      lastChanged.current = 'none';
      contextRef.current = data.context;
      setTitle(data.title ?? 'Descuento');
      setTotal(data.total);
      setDiscountPercent(data.discountPercent);
      setDiscountAmount(Number((data.discountPercent * data.total).toFixed(2)));
      setVisible(true);
    });
  }, []);

  const handlePercentChange = (value: number) => {
    lastChanged.current = 'percent';
    setDiscountPercent(value);
  };

  const handleAmountChange = (value: number) => {
    lastChanged.current = 'amount';
    setDiscountAmount(value);
  };

  return (
    <div>
      <Popup
        visible={visible}
        onHiding={() => setVisible(false)}
        dragEnabled={false}
        hideOnOutsideClick={true}
        showTitle={true}
        title={title}
        width={420}
        height={'auto'}
      >
        <ToolbarItem
          key="apply-button"
          location="after"
          widget="dxButton"
          toolbar="bottom"
          options={{
            text: 'Aceptar',
            onClick: () => onApply(discountPercent, discountAmount),
          }}
        />
        <h3 className="text-center">{NumberUtils.formatCurrency(total - discountAmount)}</h3>
        {discountAmount > 0 && (
          <h6 className="text-center text-decoration-line-through">
            {NumberUtils.formatCurrency(total)}
          </h6>
        )}
        <div className="p-2">
          <div className="mb-4">
            <NumberBox
              label={'Descuento %'}
              format="#0.##%"
              step={0.01}
              value={discountPercent}
              min={0}
              max={1}
              showClearButton={true}
              stylingMode={'outlined'}
              onValueChanged={(event) => handlePercentChange(event.value)}
            />
          </div>
          <div className="mb-4">
            <NumberBox
              label={'Descuento S/'}
              format="#,##0.00"
              value={discountAmount}
              min={0}
              max={total}
              showClearButton={true}
              stylingMode={'outlined'}
              onValueChanged={(event) => handleAmountChange(event.value)}
            />
          </div>
        </div>
      </Popup>
    </div>
  );
};

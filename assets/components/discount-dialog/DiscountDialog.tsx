import { NumberBox, Popup } from 'devextreme-react';
import { ToolbarItem } from 'devextreme-react/popup';
import { useCallback, useEffect, useRef, useState } from 'react';
import NumberUtils from '../../shared/lib/NumberUtils';
import { useEvents } from '@core/event/EventHook';

export enum DiscountDialogEvent {
  OPEN = 'discount:dialog:open',
  APPLY = 'discount:apply',
}

export const DiscountDialog = () => {
  const [on, emit] = useEvents();
  const [visible, setVisible] = useState(false);
  const [total, setTotal] = useState(0.0);
  const [discountPercent, setDiscountPercent] = useState(0.0);
  const [discountAmount, setDiscountAmount] = useState(0.0);

  const emitRef = useRef(emit);
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix: keep ref current with latest closure
  emitRef.current = emit;
  const onRef = useRef(on);
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix: keep ref current with latest closure
  onRef.current = on;

  const onApply = useCallback((discountPercent: number) => {
    setVisible(false);
    emitRef.current<number>(DiscountDialogEvent.APPLY, discountPercent);
  }, []);

  useEffect(() => {
    setDiscountAmount(discountPercent * total);
  }, [discountPercent, total]);

  useEffect(() => {
    if (total > 0) {
      setDiscountPercent(discountAmount / total);
    }
  }, [discountAmount, total]);

  useEffect(() => {
    onRef.current<{ discountPercent: number; total: number }>(DiscountDialogEvent.OPEN, (data) => {
      setTotal(data.total);
      setDiscountPercent(data.discountPercent);
      setDiscountAmount(data.discountPercent * data.total);
      setVisible(true);
    });
  }, []);

  return (
    <div>
      <Popup
        visible={visible}
        onHiding={() => setVisible(false)}
        dragEnabled={false}
        hideOnOutsideClick={true}
        showTitle={true}
        title="Descuento"
        width={420}
        height={320}
      >
        <ToolbarItem
          key="apply-button"
          location="after"
          widget="dxButton"
          toolbar="bottom"
          options={{
            text: 'Aceptar',
            onClick: () => onApply(discountPercent),
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
              label={'Discount Percent'}
              format="#0%"
              step={0.01}
              value={discountPercent}
              min={0}
              max={1}
              showClearButton={true}
              stylingMode={'outlined'}
              onValueChanged={(event) => setDiscountPercent(event.value)}
            />
          </div>
          <div className="mb-4">
            <NumberBox
              label={'Discount Amount'}
              format="currency"
              value={discountAmount}
              min={0}
              max={total}
              showClearButton={true}
              stylingMode={'outlined'}
              onValueChanged={(event) => setDiscountAmount(event.value)}
            />
          </div>
        </div>
      </Popup>
    </div>
  );
};

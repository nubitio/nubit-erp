import { useEffect, useRef } from 'react';
import { DATA_GRID_EVENTS, FORM_EVENTS } from '@core/ui/CrudViews';
import { HTTP_ERROR_EVENT } from '@shared/api/HttpClient';
import { DiscountDialogApplyPayload } from '@shared/ui/discount-dialog/DiscountDialog';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UseSalePageObserversParams = {
  on: OnEventFn;
  discountApplyEvent: string;
  saleEvents: {
    ADD: string;
  };
  customerSuccessEvent: string;
  setPrintEnabled: (value: boolean) => void;
  setCloneEnabled: (value: boolean) => void;
  setGridError?: (value: string | null) => void;
  onDiscountApply: (payload: DiscountDialogApplyPayload) => void;
  onDetailRowsChanged: () => void;
  onCustomerCreated: (payload: any) => void;
  onSaleAdd: () => void | Promise<void>;
};

export const useSalePageObservers = ({
  on,
  discountApplyEvent,
  saleEvents,
  customerSuccessEvent,
  setPrintEnabled,
  setCloneEnabled,
  setGridError,
  onDiscountApply,
  onDetailRowsChanged,
  onCustomerCreated,
  onSaleAdd,
}: UseSalePageObserversParams) => {
  const callbacksRef = useRef({
    saleEvents,
    customerSuccessEvent,
    setPrintEnabled,
    setCloneEnabled,
    setGridError,
    onDiscountApply,
    onDetailRowsChanged,
    onCustomerCreated,
    onSaleAdd,
    discountApplyEvent,
  });

  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix: keep ref current with latest props
  callbacksRef.current = {
    saleEvents,
    customerSuccessEvent,
    setPrintEnabled,
    setCloneEnabled,
    setGridError,
    onDiscountApply,
    onDetailRowsChanged,
    onCustomerCreated,
    onSaleAdd,
    discountApplyEvent,
  };

  useEffect(() => {
    on<DiscountDialogApplyPayload>(callbacksRef.current.discountApplyEvent, (payload) => {
      callbacksRef.current.onDiscountApply(payload);
    });

    on(FORM_EVENTS.ROW_ADDED, () => callbacksRef.current.onDetailRowsChanged());
    on(FORM_EVENTS.ROW_UPDATED, () => callbacksRef.current.onDetailRowsChanged());
    on(FORM_EVENTS.ROW_REMOVED, () => callbacksRef.current.onDetailRowsChanged());

    on<any>(DATA_GRID_EVENTS.SELECTION_CHANGED, (e) => {
      const hasSingleSelection = e.length === 1;
      callbacksRef.current.setPrintEnabled(hasSingleSelection);
      callbacksRef.current.setCloneEnabled(hasSingleSelection);
    });

    on(callbacksRef.current.customerSuccessEvent, (payload) => {
      callbacksRef.current.onCustomerCreated(payload);
    });
    on(callbacksRef.current.saleEvents.ADD, () => {
      callbacksRef.current.onSaleAdd();
    });

    on<string>(HTTP_ERROR_EVENT, (message) => {
      callbacksRef.current.setGridError?.(message || 'No se pudo completar la operación');
    });
  }, [on]);
};

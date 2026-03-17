import { useEffect, useRef, type RefObject } from 'react';
import { FORM_EVENTS, type DxFormEvents } from '@core/form/DxForm';
import { loadMovementItemsOnEdit } from '../lib/loadMovementItemsOnEdit';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UseMovementFormObserversParams = {
  on: OnEventFn;
  apiUrl: string;
  editEventName: string;
  formRef: RefObject<DxFormEvents | null>;
  onDetailRowsChanged?: () => void;
};

export const useMovementFormObservers = ({
  on,
  apiUrl,
  editEventName,
  formRef,
  onDetailRowsChanged,
}: UseMovementFormObserversParams) => {
  const callbacksRef = useRef({
    apiUrl,
    editEventName,
    formRef,
    onDetailRowsChanged,
  });

  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix: keep ref current with latest props
  callbacksRef.current = {
    apiUrl,
    editEventName,
    formRef,
    onDetailRowsChanged,
  };

  useEffect(() => {
    on<any>(callbacksRef.current.editEventName, (payload) => {
      const movementId = payload?.row?.id;
      if (!movementId) {
        return;
      }

      loadMovementItemsOnEdit({
        apiUrl: callbacksRef.current.apiUrl,
        movementId,
        setDetailData: (data) => callbacksRef.current.formRef.current?.setDetailData(data),
      });
    });

    if (!callbacksRef.current.onDetailRowsChanged) {
      return;
    }

    on(FORM_EVENTS.ROW_ADDED, () => callbacksRef.current.onDetailRowsChanged?.());
    on(FORM_EVENTS.ROW_UPDATED, () => callbacksRef.current.onDetailRowsChanged?.());
    on(FORM_EVENTS.ROW_REMOVED, () => callbacksRef.current.onDetailRowsChanged?.());
  }, [on]);
};

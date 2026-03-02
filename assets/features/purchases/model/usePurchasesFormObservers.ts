import type { RefObject } from 'react';
import type { DxFormEvents } from '@platform/form/DxForm';
import { useMovementFormObservers } from '@features/movement/model/useMovementFormObservers';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UsePurchasesFormObserversParams = {
  on: OnEventFn;
  apiUrl: string;
  editEventName: string;
  formRef: RefObject<DxFormEvents>;
  onDetailRowsChanged: () => void;
};

export const usePurchasesFormObservers = ({
  on,
  apiUrl,
  editEventName,
  formRef,
  onDetailRowsChanged,
}: UsePurchasesFormObserversParams) => {
  useMovementFormObservers({
    on,
    apiUrl,
    editEventName,
    formRef,
    onDetailRowsChanged,
  });
};

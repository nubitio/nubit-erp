import type { RefObject } from 'react';
import type { DxFormEvents } from '@platform/form/DxForm';
import { useMovementFormObservers } from '@features/movement/model/useMovementFormObservers';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UseInboundFormObserversParams = {
  on: OnEventFn;
  apiUrl: string;
  editEventName: string;
  formRef: RefObject<DxFormEvents | null>;
  onDetailRowsChanged: () => void;
};

export const useInboundFormObservers = ({
  on,
  apiUrl,
  editEventName,
  formRef,
  onDetailRowsChanged,
}: UseInboundFormObserversParams) => {
  useMovementFormObservers({
    on,
    apiUrl,
    editEventName,
    formRef,
    onDetailRowsChanged,
  });
};

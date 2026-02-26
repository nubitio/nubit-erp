import type { RefObject } from 'react';
import type { DxFormEvents } from '@platform/form/DxForm';
import { useMovementFormObservers } from '@features/movement';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UseOutboundFormObserversParams = {
  on: OnEventFn;
  apiUrl: string;
  editEventName: string;
  formRef: RefObject<DxFormEvents>;
};

export const useOutboundFormObservers = ({
  on,
  apiUrl,
  editEventName,
  formRef,
}: UseOutboundFormObserversParams) => {
  useMovementFormObservers({
    on,
    apiUrl,
    editEventName,
    formRef,
  });
};

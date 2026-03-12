import type { RefObject } from 'react';
import type { DxFormEvents } from '@platform/form/DxForm';
import { useMovementFormObservers } from '@features/movement/model/useMovementFormObservers';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UseOutboundFormObserversParams = {
  on: OnEventFn;
  apiUrl: string;
  editEventName: string;
  formRef: RefObject<DxFormEvents | null>;
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

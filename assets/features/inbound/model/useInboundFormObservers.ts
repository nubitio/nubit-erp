import type { RefObject } from 'react';
import type { DxFormEvents } from '@platform/form/DxForm';
import { useMovementFormObservers } from '@features/movement';

type UseInboundFormObserversParams = {
  on: OnEventFn;
  apiUrl: string;
  editEventName: string;
  formRef: RefObject<DxFormEvents>;
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

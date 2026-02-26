import { useEffect, useRef, type RefObject } from 'react';
import { FORM_EVENTS, type DxFormEvents } from '@platform/form/DxForm';
import { httpClient } from '@shared/api/HttpClient';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UseCreditNoteRelatedDocumentObserverParams = {
  on: OnEventFn;
  apiUrl: string;
  formRef: RefObject<DxFormEvents>;
};

export const useCreditNoteRelatedDocumentObserver = ({
  on,
  apiUrl,
  formRef,
}: UseCreditNoteRelatedDocumentObserverParams) => {
  const callbacksRef = useRef({ apiUrl, formRef });
  callbacksRef.current = { apiUrl, formRef };

  useEffect(() => {
    on<{ field: string; value: any }>(FORM_EVENTS.FIELD_CHANGED, ({ field, value }) => {
      if (field !== 'relatedTaxDocument') {
        return;
      }

      if (!value) {
        callbacksRef.current.formRef.current?.setDetailData([]);
        return;
      }

      httpClient.get<any>(`${callbacksRef.current.apiUrl}/${value}/lines`).subscribe((response) => {
        callbacksRef.current.formRef.current?.setDetailData(response.data ?? []);
      });
    });
  }, [on]);
};

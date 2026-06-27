import { useEffect, useRef, type RefObject } from 'react';
import { FORM_EVENTS } from '@nubitio/crud';
import type { FormHandle } from '@nubitio/crud';
import { httpClient } from '@shared/api/HttpClient';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UseCreditNoteRelatedDocumentObserverParams = {
  on: OnEventFn;
  apiUrl: string;
  formRef: RefObject<FormHandle | null>;
};

type FieldChangedEvent = {
  field: string;
  value?: string | number;
};

type TaxDocumentLine = Record<string, unknown>;

export const useCreditNoteRelatedDocumentObserver = ({
  on,
  apiUrl,
  formRef,
}: UseCreditNoteRelatedDocumentObserverParams) => {
  const callbacksRef = useRef({ apiUrl, formRef });
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix: keep ref current with latest props
  callbacksRef.current = { apiUrl, formRef };

  useEffect(() => {
    on<FieldChangedEvent>(FORM_EVENTS.FIELD_CHANGED, ({ field, value }) => {
      if (field !== 'relatedTaxDocument') {
        return;
      }

      if (!value) {
        callbacksRef.current.formRef.current?.setDetailData([]);
        return;
      }

      httpClient
        .get<TaxDocumentLine[]>(`${callbacksRef.current.apiUrl}/${value}/lines`)
        .then((linesResponse) => {
          callbacksRef.current.formRef.current?.setDetailData(linesResponse.data ?? []);
        });
    });
  }, [on]);
};

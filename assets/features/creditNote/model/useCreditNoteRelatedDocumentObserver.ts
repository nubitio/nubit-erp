import { useEffect, useRef, type RefObject } from 'react';
import { FORM_EVENTS } from '@nubit/crud';
import type { FormHandle } from '@nubit/crud';
import { httpClient } from '@shared/api/HttpClient';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UseCreditNoteRelatedDocumentObserverParams = {
  on: OnEventFn;
  apiUrl: string;
  formRef: RefObject<FormHandle | null>;
  onCurrencyChange?: (currency: string) => void;
};

export const useCreditNoteRelatedDocumentObserver = ({
  on,
  apiUrl,
  formRef,
  onCurrencyChange,
}: UseCreditNoteRelatedDocumentObserverParams) => {
  const callbacksRef = useRef({ apiUrl, formRef, onCurrencyChange });
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix: keep ref current with latest props
  callbacksRef.current = { apiUrl, formRef, onCurrencyChange };

  useEffect(() => {
    on<{ field: string; value: any }>(FORM_EVENTS.FIELD_CHANGED, ({ field, value }) => {
      if (field !== 'relatedTaxDocument') {
        return;
      }

      if (!value) {
        callbacksRef.current.formRef.current?.setDetailData([]);
        callbacksRef.current.onCurrencyChange?.('PEN');
        return;
      }

      // Fetch the header document first to get the currency, then fetch lines.
      // Sequencing is critical: the columns (detailFields) must be updated via
      // onCurrencyChange before setDetailData updates the detail rows, otherwise
      // the rows render with stale currency formatters.
      //
      // After onCurrencyChange fires, we defer setDetailData via setTimeout(0) so
      // React can flush the state update (new Field[] with correct currency formatters)
      // before rows render.
      httpClient
        .get<any>(`${callbacksRef.current.apiUrl}/${value}`)
        .then((headerResponse) => {
          const currency = (headerResponse.data?.currency as string) ?? 'PEN';
          callbacksRef.current.onCurrencyChange?.(currency);
          return httpClient.get<any>(`${callbacksRef.current.apiUrl}/${value}/lines`);
        })
        .then((linesResponse) => {
          const rows = linesResponse.data ?? [];
          // Defer by one tick so React commits the new detailFields (currency columns)
          // before rendering the new row data.
          setTimeout(() => {
            callbacksRef.current.formRef.current?.setDetailData(rows);
          }, 0);
        });
    });
  }, [on]);
};

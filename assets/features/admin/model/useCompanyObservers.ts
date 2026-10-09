import { useEffect } from 'react';
import { FORM_EVENTS, type FormHandle } from '@nubitio/crud';
import { useEvents } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { companyEndpoints } from '../api/companyEndpoints';

type UseCompanyObserversParams = {
  loadingEvent: string;
  formRef: React.RefObject<FormHandle | null>;
};

type FieldChangedEvent = {
  field?: string;
  value?: string;
};

type UbigeoResponse = {
  code?: string;
  department?: string;
  province?: string;
  district?: string;
};

type EventSubscription = {
  unsubscribe?: () => void;
};

export const useCompanyObservers = ({ loadingEvent, formRef }: UseCompanyObserversParams) => {
  const [on, emit] = useEvents();

  useEffect(() => {
    const sub = on<FieldChangedEvent>(FORM_EVENTS.FIELD_CHANGED, (data) => {
      if (data.field !== 'ubigeo') return;

      if (!data.value) {
        formRef.current?.setFieldValue('ubigeoCode', '');
        formRef.current?.setFieldValue('department', '');
        formRef.current?.setFieldValue('province', '');
        formRef.current?.setFieldValue('district', '');
        return;
      }

      emit(loadingEvent, true);
      httpClient
        .get<UbigeoResponse>(companyEndpoints.ubigeo(data.value))
        .then((response) => {
          const ubigeo = response.data;
          formRef.current?.setFieldValue('ubigeoCode', ubigeo.code ?? '');
          formRef.current?.setFieldValue('department', ubigeo.department ?? '');
          formRef.current?.setFieldValue('province', ubigeo.province ?? '');
          formRef.current?.setFieldValue('district', ubigeo.district ?? '');
        })
        .catch(() => {
          formRef.current?.setFieldValue('ubigeoCode', '');
          formRef.current?.setFieldValue('department', '');
          formRef.current?.setFieldValue('province', '');
          formRef.current?.setFieldValue('district', '');
        })
        .finally(() => emit(loadingEvent, false));
    });

    return () => (sub as EventSubscription)?.unsubscribe?.();
  }, [on, emit, formRef, loadingEvent]);
};

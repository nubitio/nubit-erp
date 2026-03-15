import { useEffect } from 'react';
import { DxFormEvents, FORM_EVENTS } from '@platform/form/DxForm';
import { useEvents } from '@platform/event/EventHook';
import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@platform/common/Constants';

type UseCompanyObserversParams = {
  loadingEvent: string;
  formRef: React.RefObject<DxFormEvents | null>;
};

export const useCompanyObservers = ({ loadingEvent, formRef }: UseCompanyObserversParams) => {
  const [on, emit] = useEvents();

  useEffect(() => {
    const sub = on<any>(FORM_EVENTS.FIELD_CHANGED, (data) => {
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
        .get<any>(`${Constants.API_BASE_URL}ubigeos/${data.value}`)
        .subscribe({
          next: (response) => {
            const ubigeo = response.data;
            formRef.current?.setFieldValue('ubigeoCode', ubigeo.code ?? '');
            formRef.current?.setFieldValue('department', ubigeo.department ?? '');
            formRef.current?.setFieldValue('province', ubigeo.province ?? '');
            formRef.current?.setFieldValue('district', ubigeo.district ?? '');
          },
          error: () => {
            formRef.current?.setFieldValue('ubigeoCode', '');
            formRef.current?.setFieldValue('department', '');
            formRef.current?.setFieldValue('province', '');
            formRef.current?.setFieldValue('district', '');
          },
        })
        .add(() => emit(loadingEvent, false));
    });

    return () => (sub as any)?.unsubscribe();
  }, [on, emit, loadingEvent]);
};

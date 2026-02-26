import './CompanyPage.scss';
import { Constants } from '@platform/common/Constants';
import { useEvents } from '@platform/event/EventHook';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm, FORM_EVENTS } from '@platform/form/DxForm';
import { companyEvents } from '@features/company';
import { httpClient } from '@shared/api/HttpClient';
import { CompanyFields } from './CompanyFields';
import { useEffect, useRef } from 'react';

export const CompanyPage = () => {
  const container = 'wrapper';
  const formRef = useRef<any>(null);
  const fields = CompanyFields(formRef.current);
  const [on, emit] = useEvents();

  const API_URL = Constants.API_BASE_URL + 'companies';

  useEffect(() => {
    const sub = on<any>(FORM_EVENTS.FIELD_CHANGED, (data) => {
      if (data.field !== 'ubigeo') {
        return;
      }

      if (!data.value) {
        formRef.current?.setFieldValue('ubigeoCode', '');
        formRef.current?.setFieldValue('department', '');
        formRef.current?.setFieldValue('province', '');
        formRef.current?.setFieldValue('district', '');
        return;
      }

      emit(companyEvents.LOADING, true);
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
        .add(() => emit(companyEvents.LOADING, false));
    });

    return () => sub?.unsubscribe();
  }, [on, emit]);

  return (
    <div id={container}>
      <DxDataGrid
        id={'company'}
        title={'Empresas'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={companyEvents}
      />
      <DxDialog events={companyEvents}>
        <DxForm
          url={API_URL}
          fields={fields}
          events={companyEvents}
          useIRI={true}
          ref={formRef}
        />
      </DxDialog>
    </div>
  );
};

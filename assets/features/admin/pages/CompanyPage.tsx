import './CompanyPage.scss';
import { companyEndpoints } from '../api/companyEndpoints';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm, DxFormEvents } from '@core/form/DxForm';
import { companyEvents } from '@features/company';
import { CompanyFields } from './CompanyFields';
import { useMemo, useRef } from 'react';
import { useCompanyObservers } from '../model/useCompanyObservers';

export const CompanyPage = () => {
  const container = 'wrapper';
  const formRef = useRef<DxFormEvents | null>(null);
  const fields = useMemo(() => CompanyFields(formRef), [formRef]);

  const API_URL = companyEndpoints.list();

  useCompanyObservers({ loadingEvent: companyEvents.LOADING, formRef });

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
        <DxForm url={API_URL} fields={fields} events={companyEvents} useIRI={true} ref={formRef} />
      </DxDialog>
    </div>
  );
};

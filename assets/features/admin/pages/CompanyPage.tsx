import { useRef } from 'react';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm, type DxFormEvents } from '@core/form/DxForm';
import { companyEvents } from '@features/company';
import { CompanyFields } from './CompanyFields';
import { companyEndpoints } from '../api/companyEndpoints';
import { useCompanyObservers } from '../model/useCompanyObservers';

const API_URL = companyEndpoints.list();

export const CompanyPage = () => {
  const formRef = useRef<DxFormEvents | null>(null);
  useCompanyObservers({ loadingEvent: companyEvents.LOADING, formRef });
  const fields = CompanyFields(formRef);
  return (
    <div id="wrapper">
      <DxDataGrid
        id="company"
        title="Empresas"
        url={API_URL}
        allowAdd
        allowEdit
        allowDelete
        fields={fields}
        events={companyEvents}
      />
      <DxDialog events={companyEvents}>
        <DxForm ref={formRef} url={API_URL} fields={fields} events={companyEvents} />
      </DxDialog>
    </div>
  );
};

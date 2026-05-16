import { useRef } from 'react';
import { DataGridView, DialogView, FormView, type FormHandle } from '@core/ui/CrudViews';
import { companyEvents } from '@core/event/domainEvents';
import { CompanyFields } from './CompanyFields';
import { companyEndpoints } from '../api/companyEndpoints';
import { useCompanyObservers } from '../model/useCompanyObservers';

const API_URL = companyEndpoints.list();

export const CompanyPage = () => {
  const formRef = useRef<FormHandle | null>(null);
  useCompanyObservers({ loadingEvent: companyEvents.LOADING, formRef });
  const fields = CompanyFields(formRef);
  return (
    <div id="wrapper">
      <DataGridView
        id="company"
        title="Empresas"
        url={API_URL}
        allowAdd
        allowEdit
        allowDelete
        fields={fields}
        events={companyEvents}
      />
      <DialogView events={companyEvents}>
        <FormView ref={formRef} url={API_URL} fields={fields} events={companyEvents} />
      </DialogView>
    </div>
  );
};

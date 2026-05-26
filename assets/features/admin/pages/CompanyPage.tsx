import { useRef } from 'react';
import { SmartCrudPage, defineResource, type FormHandle } from '@nubit/crud';
import { companyEvents } from '@app/domainEvents';
import { CompanyFields } from './CompanyFields';
import { companyEndpoints } from '../api/companyEndpoints';
import { useCompanyObservers } from '../model/useCompanyObservers';

const resource = defineResource({
  id: 'company',
  title: 'Empresas',
  apiUrl: companyEndpoints.list(),
  fields: CompanyFields,
  events: companyEvents,
  permissions: { canAdd: true, canEdit: true, canDelete: true },
});

export const CompanyPage = () => {
  const formRef = useRef<FormHandle | null>(null);
  useCompanyObservers({ loadingEvent: companyEvents.LOADING, formRef });
  return <SmartCrudPage resource={resource} formRef={formRef} />;
};

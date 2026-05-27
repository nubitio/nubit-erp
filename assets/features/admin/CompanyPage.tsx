import { useRef, useMemo } from 'react';
import { SmartCrudPage, defineResource, type FormHandle } from '@nubit/crud';
import { companyEvents } from '@app/domainEvents';
import { CompanyFields } from './CompanyFields';
import { companyEndpoints } from './api/companyEndpoints';
import { useCompanyObservers } from './model/useCompanyObservers';

export const CompanyPage = () => {
  const formRef = useRef<FormHandle | null>(null);
  // eslint-disable-next-line react-hooks/preserve-manual-memoization
  const resource = useMemo(
    () =>
      defineResource(companyEndpoints.list(), {
        id: 'company',
        title: 'Empresas',
        fields: CompanyFields(formRef),
        permissions: { canAdd: true, canEdit: true, canDelete: true },
      }),
    [],
  );
  useCompanyObservers({ loadingEvent: companyEvents.LOADING, formRef });
  return <SmartCrudPage resource={resource} formRef={formRef} />;
};

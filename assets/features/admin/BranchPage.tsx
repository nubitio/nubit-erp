import { SmartCrudPage, defineResource } from '@nubit/crud';
import { branchEndpoints } from './api/adminEndpoints';

const resource = defineResource(branchEndpoints.list(), {
  id: 'branch',
  title: 'Locales',
});
export const BranchPage = () => <SmartCrudPage resource={resource} />;

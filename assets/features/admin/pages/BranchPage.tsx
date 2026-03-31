import { SmartCrudPage, defineResource } from '@core/crud';
import { branchEvents } from '@features/branch';
import { branchEndpoints } from '../api/adminEndpoints';

const resource = defineResource({
  id: 'branch',
  title: 'Locales',
  apiUrl: branchEndpoints.list(),
  fields: [],
  events: branchEvents,
});
export const BranchPage = () => <SmartCrudPage resource={resource} />;

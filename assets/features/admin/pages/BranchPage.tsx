import { SmartCrudPage, defineResource } from '@nubit/core';
import { branchEvents } from '@nubit/core';
import { branchEndpoints } from '../api/adminEndpoints';

const resource = defineResource({
  id: 'branch',
  title: 'Locales',
  apiUrl: branchEndpoints.list(),
  fields: [],
  events: branchEvents,
});
export const BranchPage = () => <SmartCrudPage resource={resource} />;

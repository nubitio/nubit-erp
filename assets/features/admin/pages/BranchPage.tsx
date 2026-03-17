import './BranchPage.scss';
import { CrudPage, defineResource } from '@core/crud';
import { branchEvents } from '@features/branch';
import { BranchFields } from './BranchFields';
import { branchEndpoints } from '../api/adminEndpoints';

const resource = defineResource({
  id: 'branch',
  title: 'Locales',
  apiUrl: branchEndpoints.list(),
  fields: BranchFields(),
  events: branchEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const BranchPage = () => <CrudPage resource={resource} />;

import './UserPage.scss';
import { CrudPage, defineResource } from '@core/crud';
import { userEvents } from '@features/user';
import { UserFields } from './UserFields';
import { userEndpoints } from '../api/adminEndpoints';

const resource = defineResource({
  id: 'user',
  title: 'Usuarios',
  apiUrl: userEndpoints.list(),
  fields: UserFields(),
  events: userEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const UserPage = () => <CrudPage resource={resource} />;

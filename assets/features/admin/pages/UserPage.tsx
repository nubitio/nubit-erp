import './UserPage.scss';
import { SmartCrudPage, defineResource } from '@core/crud';
import { userEvents } from '@features/user';
import { userEndpoints } from '../api/adminEndpoints';

const resource = defineResource({
  id: 'user',
  title: 'Usuarios',
  apiUrl: userEndpoints.list(),
  fields: [],
  events: userEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const UserPage = () => <SmartCrudPage resource={resource} />;

import './ConfigPage.scss';
import { CrudPage, defineResource } from '@core/crud';
import { configEvents } from '@features/config';
import { ConfigFields } from './ConfigFields';
import { configEndpoints } from '../api/adminEndpoints';

const resource = defineResource({
  id: 'config',
  title: 'Configuraciones',
  apiUrl: configEndpoints.list(),
  fields: ConfigFields(),
  events: configEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const ConfigPage = () => <CrudPage resource={resource} />;

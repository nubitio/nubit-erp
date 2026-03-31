import { SmartCrudPage, defineResource } from '@core/crud';
import { operationEvents } from '@features/operation';
import { operationEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'operation',
  title: 'Operaciones',
  apiUrl: operationEndpoints.list(),
  fields: [],
  events: operationEvents,
});
export const OperationPage = () => <SmartCrudPage resource={resource} />;

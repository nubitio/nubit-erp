import './OperationPage.scss';
import { CrudPage, defineResource } from '@core/crud';
import { operationEvents } from '@features/operation';
import { OperationFields } from './OperationFields';
import { operationEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'operation',
  title: 'Operaciones',
  apiUrl: operationEndpoints.list(),
  fields: OperationFields(),
  events: operationEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const OperationPage = () => <CrudPage resource={resource} />;

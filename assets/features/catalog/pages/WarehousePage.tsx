import './WarehousePage.scss';
import { SmartCrudPage, defineResource } from '@core/crud';
import { warehouseEvents } from '@features/warehouse';
import { warehouseEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'warehouse',
  title: 'Almacen',
  apiUrl: warehouseEndpoints.list(),
  fields: [],
  events: warehouseEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const WarehousePage = () => <SmartCrudPage resource={resource} />;

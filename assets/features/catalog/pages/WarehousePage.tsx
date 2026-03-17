import './WarehousePage.scss';
import { CrudPage, defineResource } from '@core/crud';
import { warehouseEvents } from '@features/warehouse';
import { WarehouseFields } from './WarehouseFields';
import { warehouseEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'warehouse',
  title: 'Almacen',
  apiUrl: warehouseEndpoints.list(),
  fields: WarehouseFields(),
  events: warehouseEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const WarehousePage = () => <CrudPage resource={resource} />;

import { SmartCrudPage, defineResource } from '@nubit/core';
import { warehouseEvents } from '@core/event/domainEvents';
import { warehouseEndpoints } from './api/catalogEndpoints';

const resource = defineResource({
  id: 'warehouse',
  title: 'Almacen',
  apiUrl: warehouseEndpoints.list(),
  fields: [],
  events: warehouseEvents,
});
export const WarehousePage = () => (
  <SmartCrudPage
    resource={resource}
    fieldOverrides={[{ key: 'productStocks', hidden: true, visible: false, visibleOnForm: false }]}
  />
);

import { SmartCrudPage, defineResource, defineFieldContract } from '@nubit/crud';
import { warehouseEndpoints } from './api/catalogEndpoints';

const resource = defineResource(warehouseEndpoints.list(), {
  id: 'warehouse',
  title: 'Almacen',
  fieldContract: defineFieldContract({
    source: 'hydra',
    strategy: 'augment',
    directives: [{ kind: 'remove', key: 'productStocks' }],
  }),
});
export const WarehousePage = () => <SmartCrudPage resource={resource} />;

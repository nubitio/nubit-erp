import { SmartCrudPage, defineResource, defineFieldContract } from '@nubit/crud';
import { warehouseEndpoints } from './api/catalogEndpoints';

const resource = defineResource(warehouseEndpoints.list(), {
  id: 'warehouse',
  title: 'Almacenes',
  viewMode: { mode: 'drawer', drawerWidth: 560 },
  fieldContract: defineFieldContract({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'remove', key: 'productStocks' },
      { kind: 'override', key: 'name', patch: { order: 0, width: 260, label: 'Nombre' } },
      { kind: 'override', key: 'code', patch: { order: 1, width: 120, label: 'Código' } },
    ],
  }),
});
export const WarehousePage = () => <SmartCrudPage resource={resource} />;

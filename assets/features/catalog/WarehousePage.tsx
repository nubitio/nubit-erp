import { SmartCrudPage, defineResource, defineFieldContract } from '@nubitio/crud';
import { warehouseEndpoints } from './api/catalogEndpoints';

const resource = defineResource(warehouseEndpoints.list(), {
  id: 'warehouse',
  title: 'Almacenes',
  viewMode: 'dialog',
  emptyState: {
    title: 'Sin almacenes',
    description: 'Crea un almacén para gestionar existencias por local.',
    icon: 'warehouse',
  },
  fieldContract: defineFieldContract({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'remove', key: 'productStocks' },
      { kind: 'override', key: 'name', patch: { order: 0, width: 260, label: 'Nombre' } },
      { kind: 'override', key: 'branch', patch: { order: 1, width: 220, label: 'Local' } },
    ],
  }),
});
export const WarehousePage = () => <SmartCrudPage resource={resource} />;

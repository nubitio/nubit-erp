import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  entityField,
  numberField,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { restaurantTableEndpoints } from './api/restaurantTableEndpoints';

const API = getCoreApiBaseUrl();

interface RestaurantAreaRef {
  id: number;
  name: string;
}

interface RestaurantTableRecord extends DataRecord {
  id: number;
  code: string;
  area: RestaurantAreaRef;
  seats: number;
  status: string;
}

function toPatch(field: Field): SmartCrudFieldPatch<RestaurantTableRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<RestaurantTableRecord>;
}

const seatsPatch = toPatch(
  numberField<RestaurantTableRecord>()
    .name('seats')
    .label('Aforo')
    .helpText('Número de personas en la mesa')
    .required(true)
    .build(),
);

const areaPatch = toPatch(
  entityField<RestaurantTableRecord>(`${API}restaurant/areas`, 'id', 'name')
    .name('area')
    .label('Ambiente')
    .required(true)
    .formatter((cell) => (cell.value as RestaurantAreaRef | undefined)?.name ?? '')
    .build(),
);

const resource = defineResource<RestaurantTableRecord>(restaurantTableEndpoints.list(), {
  id: 'restaurantTable',
  title: 'Mesas',
  viewMode: { mode: 'drawer', drawerSize: 'sm' },
  emptyState: {
    title: 'Sin mesas',
    description: 'Crea primero un ambiente en "Ambientes y plantas", luego registra las mesas aquí.',
    icon: 'squares-four',
  },
  fieldContract: defineFields<RestaurantTableRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'code', patch: { order: 0, label: 'Código', required: true, width: 120 } },
      { kind: 'override', key: 'area', patch: areaPatch },
      { kind: 'override', key: 'seats', patch: seatsPatch },
      { kind: 'override', key: 'status', patch: { label: 'Estado', readonly: true } },
      { kind: 'remove', key: 'positionX' },
      { kind: 'remove', key: 'positionY' },
    ],
  }),
});

export const TablesAdminPage = () => <SchemaCrudPage resource={resource} />;

export default TablesAdminPage;

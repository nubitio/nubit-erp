import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  numberField,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { restaurantAreaEndpoints } from './api/restaurantAreaEndpoints';

interface RestaurantAreaRecord extends DataRecord {
  id: number;
  name: string;
  floor: number;
  orderIndex: number;
}

function toPatch(field: Field): SmartCrudFieldPatch<RestaurantAreaRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<RestaurantAreaRecord>;
}

const floorPatch = toPatch(
  numberField<RestaurantAreaRecord>()
    .name('floor')
    .label('Planta')
    .helpText('1 = planta baja. Solo importa si el local tiene más de un piso.')
    .defaultValue(1)
    .required(true)
    .build(),
);

const orderIndexPatch = toPatch(
  numberField<RestaurantAreaRecord>()
    .name('orderIndex')
    .label('Orden')
    .helpText('Orden de la pestaña dentro de la misma planta')
    .defaultValue(0)
    .required(true)
    .build(),
);

const resource = defineResource<RestaurantAreaRecord>(restaurantAreaEndpoints.list(), {
  id: 'restaurantArea',
  title: 'Ambientes y plantas',
  viewMode: { mode: 'drawer', drawerSize: 'sm' },
  emptyState: {
    title: 'Sin ambientes',
    description: 'Crea el primer ambiente (ej. "Salón") antes de registrar mesas.',
    icon: 'map-trifold',
  },
  fieldContract: defineFields<RestaurantAreaRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'name', patch: { order: 0, label: 'Nombre', required: true } },
      { kind: 'override', key: 'floor', patch: floorPatch },
      { kind: 'override', key: 'orderIndex', patch: orderIndexPatch },
    ],
  }),
});

export const RestaurantAreasAdminPage = () => <SchemaCrudPage resource={resource} />;

export default RestaurantAreasAdminPage;

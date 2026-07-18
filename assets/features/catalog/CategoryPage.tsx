import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  FieldType,
  type DataRecord,
  type GridCellContext,
} from '@nubitio/crud';
import { categoryEndpoints } from './api/catalogEndpoints';
import { SharedUtils } from '@shared/lib/SharedUtils';

interface CategoryRecord extends DataRecord {
  id: string;
  name: string;
  isActive: boolean;
}

const resource = defineResource<CategoryRecord>(categoryEndpoints.list(), {
  id: 'category',
  title: 'Categorías',
  viewMode: 'dialog',
  emptyState: {
    title: 'Sin categorías',
    description: 'Organiza el catálogo agrupando productos por categoría.',
    icon: 'folder',
  },
  fieldContract: defineFields<CategoryRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'name', patch: { order: 0, width: 320, label: 'Nombre' } },
      { kind: 'override', key: 'parent', patch: { order: 0.5, label: 'Categoría padre' } },
      {
        kind: 'override',
        key: 'isActive',
        patch: {
          order: 1,
          width: 100,
          label: 'Activo',
          type: FieldType.SWITCH,
          formatter: (cell: GridCellContext) => SharedUtils.booleanFormatter(Boolean(cell.value)),
        },
      },
    ],
  }),
});

export const CategoryPage = () => <SchemaCrudPage resource={resource} />;
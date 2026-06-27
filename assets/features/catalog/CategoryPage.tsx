import { SchemaCrudPage, defineFields, defineResource, type DataRecord } from '@nubitio/crud';
import { categoryEndpoints } from './api/catalogEndpoints';

interface CategoryRecord extends DataRecord {
  id: string;
  name: string;
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
    ],
  }),
});

export const CategoryPage = () => <SchemaCrudPage resource={resource} />;
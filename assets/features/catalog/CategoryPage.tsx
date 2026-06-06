import { SmartCrudPage, defineFields, defineResource, type DataRecord } from '@nubit/crud';
import { categoryEndpoints } from './api/catalogEndpoints';

interface CategoryRecord extends DataRecord {
  id: string;
  name: string;
}

const resource = defineResource<CategoryRecord>(categoryEndpoints.list(), {
  id: 'category',
  title: 'Categorías',
  viewMode: { mode: 'drawer', drawerWidth: 520 },
  fieldContract: defineFields<CategoryRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'name', patch: { order: 0, width: 320, label: 'Nombre' } },
    ],
  }),
});

export const CategoryPage = () => <SmartCrudPage resource={resource} />;
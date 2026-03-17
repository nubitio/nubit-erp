import './CategoryPage.scss';
import { CrudPage, defineResource } from '@core/crud';
import { categoryEndpoints } from '../api/catalogEndpoints';
import { CategoryFields } from './CategoryFields';

const categoryResource = defineResource({
  id: 'category',
  title: 'Categorias',
  apiUrl: categoryEndpoints.list(),
  fields: CategoryFields(),
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});

export const CategoryPage = () => <CrudPage resource={categoryResource} />;

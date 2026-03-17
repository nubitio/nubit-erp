import './CategoryPage.scss';
import {defineResource, SmartCrudPage} from '@core/crud';
import {categoryEndpoints} from '../api/catalogEndpoints';
import {CategoryFields} from './CategoryFields';

const categoryResource = defineResource({
  id: 'category',
  title: 'Categorias',
  apiUrl: categoryEndpoints.list(),
  fields: CategoryFields(),
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});

export const CategoryPage = () => <SmartCrudPage resource={defineResource('api/categories')}/>;

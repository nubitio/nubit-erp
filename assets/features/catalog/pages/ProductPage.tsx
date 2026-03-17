import './ProductPage.scss';
import { CrudPage, defineResource } from '@core/crud';
import { Constants } from '@core/common/Constants';
import { ProductFields } from './ProductFields';

const productResource = defineResource({
  id: 'product',
  title: 'Productos',
  apiUrl: Constants.API_BASE_URL + 'products',
  fields: ProductFields(),
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
  sort: [{ selector: 'name', desc: false }],
});

export const ProductPage = () => <CrudPage resource={productResource} />;

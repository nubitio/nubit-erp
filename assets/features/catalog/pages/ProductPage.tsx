import './ProductPage.scss';
import { SmartCrudPage, defineResource } from '@core/crud';
import { Constants } from '@core/common/Constants';

const productResource = defineResource({
  id: 'product',
  title: 'Productos',
  apiUrl: Constants.API_BASE_URL + 'products',
  fields: [],
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
  sort: [{ selector: 'name', desc: false }],
});

export const ProductPage = () => <SmartCrudPage resource={productResource} />;

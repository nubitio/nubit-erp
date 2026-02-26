import './ProductPage.scss';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxForm } from '@platform/form/DxForm';
import { DxDialog } from '@platform/dialog/DxDialog';
import { Constants } from '@platform/common/Constants';
import { productEvents } from '@features/product';
import { ProductFields } from './ProductFields';

export const ProductPage = () => {
  const container = 'wrapper';
  const fields = ProductFields();

  const API_URL = Constants.API_BASE_URL + 'products';

  return (
    <div id={container}>
      <DxDataGrid
        id={'product'}
        title={'Productos'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={productEvents}
        sort={[{ selector: 'name', desc: false }]}
      />
      <DxDialog events={productEvents}>
        <DxForm url={API_URL} fields={fields} events={productEvents} useIRI={true} />
      </DxDialog>
    </div>
  );
};

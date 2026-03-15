import './CategoryPage.scss';
import { CategoryFields } from './CategoryFields';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxForm } from '@platform/form/DxForm';
import { DxDialog } from '@platform/dialog/DxDialog';
import { categoryEvents } from '@features/category';
import { categoryEndpoints } from '../api/catalogEndpoints';

export const CategoryPage = () => {
  const container = 'wrapper';
  const API_URL = categoryEndpoints.list();
  const fields = CategoryFields();
  return (
    <div id={container}>
      <DxDataGrid
        id={'category'}
        title={'Categorias'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={categoryEvents}
      />
      <DxDialog events={categoryEvents}>
        <DxForm url={API_URL} fields={fields} events={categoryEvents} />
      </DxDialog>
    </div>
  );
};

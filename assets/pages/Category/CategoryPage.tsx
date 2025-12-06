import './CategoryPage.scss';
import { CategoryFields } from './CategoryFields';
import { DxDataGrid } from '../../core/datagrid/DxDataGrid';
import { DxForm } from '../../core/form/DxForm';
import { DxDialog } from '../../core/dialog/DxDialog';
import { Constants } from '../../core/common/Constants';

export const CategoryPage = () => {
  const container = 'wrapper';
  const API_URL = Constants.API_BASE_URL + 'categories';
  const fields = CategoryFields();
  const EVENTS = {
    ADD: 'category:add',
    EDIT: 'category:edit',
    DELETE: 'category:delete',
    SAVE: 'category:save',
    CANCEL: 'category:cancel',
    SUCCESS: 'category:success',
    LOADING: 'category:loading',
  };

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
        events={EVENTS}
      />
      <DxDialog events={EVENTS}>
        <DxForm url={API_URL} fields={fields} events={EVENTS} />
      </DxDialog>
    </div>
  );
};

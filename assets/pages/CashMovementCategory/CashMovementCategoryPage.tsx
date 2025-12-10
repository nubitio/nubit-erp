import './CashMovementCategoryPage.scss';
import { CashMovementCategoryFields } from './CashMovementCategoryFields';
import { DxDataGrid } from '../../core/datagrid/DxDataGrid';
import { DxDialog } from '../../core/dialog/DxDialog';
import { DxForm } from '../../core/form/DxForm';
import { Constants } from '../../core/common/Constants';

export const CashMovementCategoryPage = () => {
  const container = 'wrapper';
  const API_URL = Constants.API_BASE_URL + 'cash-movement-categories';
  const fields = CashMovementCategoryFields();
  const EVENTS = {
    ADD: 'cash-movement-categories:add',
    EDIT: 'cash-movement-categories:edit',
    DELETE: 'cash-movement-categories:delete',
    SAVE: 'cash-movement-categories:save',
    CANCEL: 'cash-movement-categories:cancel',
    SUCCESS: 'cash-movement-categories:success',
    LOADING: 'cash-movement-categories:loading',
  };

  return (
    <div id={container}>
      <DxDataGrid
        id={'cash-movement-categories'}
        title={'Categorías de caja'}
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

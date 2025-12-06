import './CashRegisterPage.scss';
import { DxDataGrid } from '../../core/datagrid/DxDataGrid';
import { DxForm } from '../../core/form/DxForm';
import { DxDialog } from '../../core/dialog/DxDialog';
import { Constants } from '../../core/common/Constants';
import { CashRegisterFields } from './CashRegisterFields';

export const CashRegisterPage = () => {
  const container = 'wrapper';
  const API_URL = Constants.API_BASE_URL + 'cash-registers';
  const fields = CashRegisterFields();
  const EVENTS = {
    ADD: 'cash-register:add',
    EDIT: 'cash-register:edit',
    DELETE: 'cash-register:delete',
    SAVE: 'cash-register:save',
    CANCEL: 'cash-register:cancel',
    SUCCESS: 'cash-register:success',
    LOADING: 'cash-register:loading',
  };

  return (
    <div id={container}>
      <DxDataGrid
        id={'cash-register'}
        title={'Cajas'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={EVENTS}
      />
      <DxDialog events={EVENTS}>
        <DxForm url={API_URL} fields={fields} events={EVENTS} useIRI={true} />
      </DxDialog>
    </div>
  );
};

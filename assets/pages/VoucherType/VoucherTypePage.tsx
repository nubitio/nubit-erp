import './VoucherTypePage.scss';
import { Constants } from '../../core/common/Constants';
import { DxDataGrid } from '../../core/datagrid/DxDataGrid';
import { DxDialog } from '../../core/dialog/DxDialog';
import { DxForm } from '../../core/form/DxForm';
import { VoucherTypeFields } from './VoucherTypeFields';

export const VoucherTypePage = () => {
  const container = 'wrapper';
  const fields = VoucherTypeFields();

  const API_URL = Constants.API_BASE_URL + 'voucher-types';

  const EVENTS = {
    ADD: 'voucherType:add',
    EDIT: 'voucherType:edit',
    DELETE: 'voucherType:delete',
    SAVE: 'voucherType:save',
    CANCEL: 'voucherType:cancel',
    SUCCESS: 'voucherType:success',
    LOADING: 'voucherType:loading',
  };

  return (
    <div id={container}>
      <DxDataGrid
        id={'voucherType'}
        title={'Tipos de comprobante'}
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

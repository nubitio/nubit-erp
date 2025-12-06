import './VoucherSeriesPage.scss';
import { Constants } from '../../core/common/Constants';
import { DxDataGrid } from '../../core/datagrid/DxDataGrid';
import { DxDialog } from '../../core/dialog/DxDialog';
import { DxForm } from '../../core/form/DxForm';
import { VoucherSeriesFields } from './VoucherSeriesFields';

export const VoucherSeriesPage = () => {
  const container = 'wrapper';
  const fields = VoucherSeriesFields();

  const API_URL = Constants.API_BASE_URL + 'voucher-series';

  const EVENTS = {
    ADD: 'voucher-series:add',
    EDIT: 'voucher-series:edit',
    DELETE: 'voucher-series:delete',
    SAVE: 'voucher-series:save',
    CANCEL: 'voucher-series:cancel',
    SUCCESS: 'voucher-series:success',
    LOADING: 'voucher-series:loading',
  };

  return (
    <div id={container}>
      <DxDataGrid
        id={'voucherSeries'}
        title={'Serie comprobantes'}
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

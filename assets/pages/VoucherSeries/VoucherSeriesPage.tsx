import './VoucherSeriesPage.scss';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { voucherSeriesEvents } from '@features/voucherSeries';
import { VoucherSeriesFields } from './VoucherSeriesFields';

export const VoucherSeriesPage = () => {
  const container = 'wrapper';
  const fields = VoucherSeriesFields();

  const API_URL = Constants.API_BASE_URL + 'voucher-series';

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
        events={voucherSeriesEvents}
      />
      <DxDialog events={voucherSeriesEvents}>
        <DxForm url={API_URL} fields={fields} events={voucherSeriesEvents} />
      </DxDialog>
    </div>
  );
};

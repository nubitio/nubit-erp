import './VoucherSeriesPage.scss';
import { useMemo } from 'react';
import { Constants } from '@core/common/Constants';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { voucherSeriesEvents } from '@features/voucherSeries';
import { VoucherSeriesFields } from './VoucherSeriesFields';

export const VoucherSeriesPage = () => {
  const container = 'wrapper';
  const fields = useMemo(() => VoucherSeriesFields(), []);

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

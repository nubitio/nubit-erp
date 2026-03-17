import './VoucherSeriesPage.scss';
import { Constants } from '@core/common/Constants';
import { CrudPage, defineResource } from '@core/crud';
import { voucherSeriesEvents } from '@features/voucherSeries';
import { VoucherSeriesFields } from './VoucherSeriesFields';

const resource = defineResource({
  id: 'voucherSeries',
  title: 'Serie comprobantes',
  apiUrl: Constants.API_BASE_URL + 'voucher-series',
  fields: VoucherSeriesFields(),
  events: voucherSeriesEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const VoucherSeriesPage = () => <CrudPage resource={resource} />;

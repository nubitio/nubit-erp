import './VoucherSeriesPage.scss';
import { Constants } from '@core/common/Constants';
import { SmartCrudPage, defineResource } from '@core/crud';
import { voucherSeriesEvents } from '@features/voucherSeries';

const resource = defineResource({
  id: 'voucherSeries',
  title: 'Serie comprobantes',
  apiUrl: Constants.API_BASE_URL + 'voucher-series',
  fields: [],
  events: voucherSeriesEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const VoucherSeriesPage = () => <SmartCrudPage resource={resource} />;

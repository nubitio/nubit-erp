import { Constants } from '@core/common/Constants';
import { SmartCrudPage, defineResource } from '@nubit/core';
import { voucherSeriesEvents } from '@core/event/domainEvents';

const resource = defineResource({
  id: 'voucherSeries',
  title: 'Serie comprobantes',
  apiUrl: Constants.API_BASE_URL + 'voucher-series',
  fields: [],
  events: voucherSeriesEvents,
});
export const VoucherSeriesPage = () => <SmartCrudPage resource={resource} />;

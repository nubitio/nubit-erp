import { API_BASE_URL } from '@app/constants';
import { SmartCrudPage, defineResource } from '@nubit/crud';
import { voucherSeriesEvents } from '@app/domainEvents';

const resource = defineResource({
  id: 'voucherSeries',
  title: 'Serie comprobantes',
  apiUrl: API_BASE_URL + 'voucher-series',
  fields: [],
  events: voucherSeriesEvents,
});
export const VoucherSeriesPage = () => <SmartCrudPage resource={resource} />;

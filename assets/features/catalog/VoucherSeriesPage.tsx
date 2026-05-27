import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import { SmartCrudPage, defineResource } from '@nubit/crud';

const resource = defineResource(API + 'voucher-series', {
  id: 'voucherSeries',
  title: 'Serie comprobantes',
});
export const VoucherSeriesPage = () => <SmartCrudPage resource={resource} />;

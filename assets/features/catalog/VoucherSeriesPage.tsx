import { SmartCrudPage, defineFields, defineResource, type DataRecord } from '@nubit/crud';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();

interface VoucherSeriesRecord extends DataRecord {
  id: string;
  series: string;
  voucherType: unknown;
}

const resource = defineResource<VoucherSeriesRecord>(API + 'voucher-series', {
  id: 'voucherSeries',
  title: 'Series de comprobante',
  viewMode: { mode: 'drawer', drawerWidth: 560 },
  fieldContract: defineFields<VoucherSeriesRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'series', patch: { order: 0, width: 140, label: 'Serie' } },
      { kind: 'override', key: 'voucherType', patch: { order: 1, width: 220, label: 'Tipo de comprobante' } },
    ],
  }),
});

export const VoucherSeriesPage = () => <SmartCrudPage resource={resource} />;
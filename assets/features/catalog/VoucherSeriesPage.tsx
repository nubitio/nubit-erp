import { SchemaCrudPage, defineFields, defineResource, type DataRecord } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

interface VoucherSeriesRecord extends DataRecord {
  id: string;
  series: string;
  voucherType: unknown;
}

const resource = defineResource<VoucherSeriesRecord>(API + 'voucher-series', {
  id: 'voucherSeries',
  title: 'Series de comprobante',
  viewMode: 'dialog',
  emptyState: {
    title: 'Sin series de comprobante',
    description: 'Configura series por tipo de documento para facturación.',
    icon: 'hash',
  },
  fieldContract: defineFields<VoucherSeriesRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'series', patch: { order: 0, width: 140, label: 'Serie' } },
      { kind: 'override', key: 'voucherType', patch: { order: 1, width: 220, label: 'Tipo de comprobante' } },
    ],
  }),
});

export const VoucherSeriesPage = () => <SchemaCrudPage resource={resource} />;
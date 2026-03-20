import { SmartCrudPage, defineResource } from '@core/crud';
import { voucherTypeEvents } from '@features/voucherType';
import { voucherTypeEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'voucherType',
  title: 'Tipos de comprobante',
  apiUrl: voucherTypeEndpoints.list(),
  fields: [],
  events: voucherTypeEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const VoucherTypePage = () => (
  <SmartCrudPage resource={resource} fieldOverrides={[{ key: 'code', isIdentity: true }]} />
);

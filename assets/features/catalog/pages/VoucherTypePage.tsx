import { SmartCrudPage, defineResource } from '@core/crud';
import { voucherTypeEvents } from '@features/voucherType';
import { voucherTypeEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'voucherType',
  title: 'Tipos de comprobante',
  apiUrl: voucherTypeEndpoints.list(),
  fields: [],
  events: voucherTypeEvents,
});
export const VoucherTypePage = () => (
  <SmartCrudPage
    resource={resource}
    fieldOverrides={[
      { key: 'id', isIdentity: false, visible: false, visibleOnForm: false },
      { key: 'code', isIdentity: true },
    ]}
  />
);

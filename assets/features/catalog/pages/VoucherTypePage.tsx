import './VoucherTypePage.scss';
import { CrudPage, defineResource } from '@core/crud';
import { voucherTypeEvents } from '@features/voucherType';
import { VoucherTypeFields } from './VoucherTypeFields';
import { voucherTypeEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'voucherType',
  title: 'Tipos de comprobante',
  apiUrl: voucherTypeEndpoints.list(),
  fields: VoucherTypeFields(),
  events: voucherTypeEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const VoucherTypePage = () => <CrudPage resource={resource} />;

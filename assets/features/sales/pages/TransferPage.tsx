import { Constants } from '@core/common/Constants';
import { SmartCrudPage, defineResource } from '@core/crud';
import { transferEvents } from '@features/transfer';

const resource = defineResource({
  id: 'transfer',
  title: 'Transferencias',
  apiUrl: Constants.API_BASE_URL + 'transfers',
  fields: [],
  events: transferEvents,
});
export const TransferPage = () => <SmartCrudPage resource={resource} />;

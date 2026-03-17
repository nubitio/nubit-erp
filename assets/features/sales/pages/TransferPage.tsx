import './TransferPage.scss';
import { Constants } from '@core/common/Constants';
import { CrudPage, defineResource } from '@core/crud';
import { transferEvents } from '@features/transfer';
import { TransferFields } from './TransferFields';

const resource = defineResource({
  id: 'transfer',
  title: 'Transferencias',
  apiUrl: Constants.API_BASE_URL + 'transfers',
  fields: TransferFields(),
  events: transferEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const TransferPage = () => <CrudPage resource={resource} />;

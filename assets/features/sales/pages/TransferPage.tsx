import './TransferPage.scss';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { transferEvents } from '@features/transfer';
import { TransferFields } from './TransferFields';

export const TransferPage = () => {
  const container = 'wrapper';
  const fields = TransferFields();

  const API_URL = Constants.API_BASE_URL + 'transfers';

  return (
    <div id={container}>
      <DxDataGrid
        id={'transfer'}
        title={'Transferencias'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={transferEvents}
      />
      <DxDialog events={transferEvents}>
        <DxForm url={API_URL} fields={fields} events={transferEvents} />
      </DxDialog>
    </div>
  );
};

import './TransferPage.scss';
import { useMemo } from 'react';
import { Constants } from '@core/common/Constants';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { transferEvents } from '@features/transfer';
import { TransferFields } from './TransferFields';

export const TransferPage = () => {
  const container = 'wrapper';
  const fields = useMemo(() => TransferFields(), []);

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

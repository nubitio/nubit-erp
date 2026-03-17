import './OperationPage.scss';
import { useMemo } from 'react';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { operationEvents } from '@features/operation';
import { OperationFields } from './OperationFields';
import { operationEndpoints } from '../api/catalogEndpoints';

export const OperationPage = () => {
  const container = 'wrapper';
  const fields = useMemo(() => OperationFields(), []);

  const API_URL = operationEndpoints.list();

  return (
    <div id={container}>
      <DxDataGrid
        id={'operation'}
        title={'Operaciones'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={operationEvents}
      />
      <DxDialog events={operationEvents}>
        <DxForm url={API_URL} fields={fields} events={operationEvents} />
      </DxDialog>
    </div>
  );
};

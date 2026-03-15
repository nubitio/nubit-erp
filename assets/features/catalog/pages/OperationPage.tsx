import './OperationPage.scss';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { operationEvents } from '@features/operation';
import { OperationFields } from './OperationFields';
import { operationEndpoints } from '../api/catalogEndpoints';

export const OperationPage = () => {
  const container = 'wrapper';
  const fields = OperationFields();

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

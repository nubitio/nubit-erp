import './BranchPage.scss';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { branchEvents } from '@features/branch';
import { BranchFields } from './BranchFields';
import { branchEndpoints } from '../api/adminEndpoints';

export const BranchPage = () => {
  const container = 'wrapper';
  const fields = BranchFields();

  const API_URL = branchEndpoints.list();

  return (
    <div id={container}>
      <DxDataGrid
        id={'branch'}
        title={'Locales'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={branchEvents}
      />
      <DxDialog events={branchEvents}>
        <DxForm url={API_URL} fields={fields} events={branchEvents} />
      </DxDialog>
    </div>
  );
};

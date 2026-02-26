import './BranchPage.scss';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { branchEvents } from '@features/branch';
import { BranchFields } from './BranchFields';

export const BranchPage = () => {
  const container = 'wrapper';
  const fields = BranchFields();

  const API_URL = Constants.API_BASE_URL + 'branches';

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

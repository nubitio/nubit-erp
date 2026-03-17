import './BranchPage.scss';
import { useMemo } from 'react';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { branchEvents } from '@features/branch';
import { BranchFields } from './BranchFields';
import { branchEndpoints } from '../api/adminEndpoints';

export const BranchPage = () => {
  const container = 'wrapper';
  const fields = useMemo(() => BranchFields(), []);

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

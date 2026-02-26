import './DepartmentPage.scss';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { DepartmentFields } from './DepartmentFields';

export const DepartmentPage = () => {
  const container = 'wrapper';
  const fields = DepartmentFields();

  const API_URL = Constants.API_BASE_URL + 'departments';

  const EVENTS = {
    ADD: 'department:add',
    EDIT: 'department:edit',
    DELETE: 'department:delete',
    SAVE: 'department:save',
    CANCEL: 'department:cancel',
    SUCCESS: 'department:success',
    LOADING: 'department:loading',
  };

  return (
    <div id={container}>
      <DxDataGrid
        id={'department'}
        title={'Departamentos laborales'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={EVENTS}
      />
      <DxDialog events={EVENTS}>
        <DxForm url={API_URL} fields={fields} events={EVENTS} />
      </DxDialog>
    </div>
  );
};

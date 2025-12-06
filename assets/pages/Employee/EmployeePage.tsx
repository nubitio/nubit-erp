import './EmployeePage.scss';
import { Constants } from '../../core/common/Constants';
import { DxDataGrid } from '../../core/datagrid/DxDataGrid';
import { DxDialog } from '../../core/dialog/DxDialog';
import { DxForm } from '../../core/form/DxForm';
import { EmployeeFields } from './EmployeeFields';

export const EmployeePage = () => {
  const container = 'wrapper';
  const fields = EmployeeFields();

  const API_URL = Constants.API_BASE_URL + 'employees';

  const EVENTS = {
    ADD: 'employee:add',
    EDIT: 'employee:edit',
    DELETE: 'employee:delete',
    SAVE: 'employee:save',
    CANCEL: 'employee:cancel',
    SUCCESS: 'employee:success',
    LOADING: 'employee:loading',
  };

  return (
    <div id={container}>
      <DxDataGrid
        id={'employee'}
        title={'Empleados'}
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

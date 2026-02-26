import './UserPage.scss';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { userEvents } from '@features/user';
import { UserFields } from './UserFields';

export const UserPage = () => {
  const container = 'wrapper';
  const fields = UserFields();

  const API_URL = Constants.API_BASE_URL + 'users';

  return (
    <div id={container}>
      <DxDataGrid
        id={'user'}
        title={'Usuarios'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={userEvents}
      />
      <DxDialog events={userEvents}>
        <DxForm url={API_URL} fields={fields} events={userEvents} useIRI={true} />
      </DxDialog>
    </div>
  );
};

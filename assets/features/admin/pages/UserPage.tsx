import './UserPage.scss';
import { useMemo } from 'react';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { userEvents } from '@features/user';
import { UserFields } from './UserFields';
import { userEndpoints } from '../api/adminEndpoints';

export const UserPage = () => {
  const container = 'wrapper';
  const fields = useMemo(() => UserFields(), []);

  const API_URL = userEndpoints.list();

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

import './ConfigPage.scss';
import { useMemo } from 'react';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { configEvents } from '@features/config';
import { ConfigFields } from './ConfigFields';
import { configEndpoints } from '../api/adminEndpoints';

export const ConfigPage = () => {
  const container = 'wrapper';
  const fields = useMemo(() => ConfigFields(), []);

  const API_URL = configEndpoints.list();

  return (
    <div id={container}>
      <DxDataGrid
        id={'config'}
        title={'Configuraciones'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={configEvents}
      />
      <DxDialog events={configEvents}>
        <DxForm url={API_URL} fields={fields} events={configEvents} />
      </DxDialog>
    </div>
  );
};

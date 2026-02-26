import './ConfigPage.scss';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { configEvents } from '@features/config';
import { ConfigFields } from './ConfigFields';

export const ConfigPage = () => {
  const container = 'wrapper';
  const fields = ConfigFields();

  const API_URL = Constants.API_BASE_URL + 'configs';

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

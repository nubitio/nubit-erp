import { DxDataGrid } from '../../core/datagrid/DxDataGrid';
import { DxForm } from '../../core/form/DxForm';
import { DxDialog } from '../../core/dialog/DxDialog';
import { Constants } from '../../core/common/Constants';
import { SunatCatalogFields } from './SunatCatalogFields';

export const SunatCatalogPage = () => {
  const container = 'wrapper';
  const API_URL = Constants.API_BASE_URL + 'sunat-catalogs';
  const fields = SunatCatalogFields();
  const EVENTS = {
    ADD: 'sunat-catalog:add',
    EDIT: 'sunat-catalog:edit',
    DELETE: 'sunat-catalog:delete',
    SAVE: 'sunat-catalog:save',
    CANCEL: 'sunat-catalog:cancel',
    SUCCESS: 'sunat-catalog:success',
    LOADING: 'sunat-catalog:loading',
  };

  return (
    <div id={container}>
      <DxDataGrid
        id={'sunat-catalog'}
        title={'Catálogos SUNAT'}
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

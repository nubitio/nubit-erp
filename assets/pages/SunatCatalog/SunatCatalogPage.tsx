import { Constants } from '@platform/common/Constants';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxForm } from '@platform/form/DxForm';
import { DxDialog } from '@platform/dialog/DxDialog';
import { sunatCatalogEvents } from '@features/sunatCatalog';
import { SunatCatalogFields } from './SunatCatalogFields';

export const SunatCatalogPage = () => {
  const container = 'wrapper';
  const API_URL = Constants.API_BASE_URL + 'sunat-catalogs';
  const fields = SunatCatalogFields();
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
        events={sunatCatalogEvents}
      />
      <DxDialog events={sunatCatalogEvents}>
        <DxForm url={API_URL} fields={fields} events={sunatCatalogEvents} />
      </DxDialog>
    </div>
  );
};

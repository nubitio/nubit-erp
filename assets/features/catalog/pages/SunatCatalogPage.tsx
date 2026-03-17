import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { useMemo } from 'react';
import { DxForm } from '@core/form/DxForm';
import { DxDialog } from '@core/dialog/DxDialog';
import { sunatCatalogEvents } from '@features/sunatCatalog';
import { SunatCatalogFields } from './SunatCatalogFields';
import { sunatCatalogEndpoints } from '../api/catalogEndpoints';

export const SunatCatalogPage = () => {
  const container = 'wrapper';
  const API_URL = sunatCatalogEndpoints.list();
  const fields = useMemo(() => SunatCatalogFields(), []);
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

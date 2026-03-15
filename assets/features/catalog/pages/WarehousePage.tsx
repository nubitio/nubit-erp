import './WarehousePage.scss';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { warehouseEvents } from '@features/warehouse';
import { WarehouseFields } from './WarehouseFields';
import { warehouseEndpoints } from '../api/catalogEndpoints';

export const WarehousePage = () => {
  const container = 'wrapper';
  const fields = WarehouseFields();

  const API_URL = warehouseEndpoints.list();

  return (
    <div id={container}>
      <DxDataGrid
        id={'warehouse'}
        title={'Almacen'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={warehouseEvents}
      />
      <DxDialog events={warehouseEvents}>
        <DxForm url={API_URL} fields={fields} events={warehouseEvents} useIRI={true} />
      </DxDialog>
    </div>
  );
};

import './PriceListPage.scss';
import { useRef } from 'react';
import { DxDataGrid, type DxDataGridEvents } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { PriceListFields } from './PriceListFields';
import { priceListEndpoints } from '../api/priceListEndpoints';
import toast from '@shared/lib/SharedUtils';
import { DataGrid } from 'devextreme-react';
import { Column, type DataGridRef, Editing, Scrolling } from 'devextreme-react/data-grid';
import type { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import NumberUtils from '@shared/lib/NumberUtils';
import { useEvents } from '@core/event/EventHook';
import DataSource from 'devextreme/data/data_source';
import ArrayStore from 'devextreme/data/array_store';
import { usePriceListObservers } from '../model/usePriceListObservers';

const EVENTS = {
  ADD: 'price-list:add',
  EDIT: 'price-list:edit',
  DELETE: 'price-list:delete',
  SAVE: 'price-list:save',
  CANCEL: 'price-list:cancel',
  SUCCESS: 'price-list:success',
  LOADING: 'price-list:loading',
};

const PRICES_EVENT = {
  ADD: 'price:add',
  EDIT: 'price:edit',
  DELETE: 'price:delete',
  SAVE: 'price:save',
  CANCEL: 'price:cancel',
  SUCCESS: 'price:success',
  LOADING: 'price:loading',
};

const API_URL = priceListEndpoints.list();
const fields = PriceListFields();

export const PriceListPage = () => {
  const [on, emit] = useEvents();
  const gridRef = useRef<DxDataGridEvents | null>(null);
  const pricesRef = useRef<DataGridRef | null>(null);
  const pricesData = useRef<DataSource>(
    new DataSource({ store: new ArrayStore({ key: 'id', data: [] }) }),
  );

  const toolbar = [
    {
      text: 'Precios',
      type: 'info',
      onClick: () => {
        const selectedRow = gridRef.current?.getSelectedRow();
        if (!selectedRow) {
          toast('Debe seleccionar una tarifa', 'warning');
          return;
        }
        emit(PRICES_EVENT.ADD, { title: 'Precios de ' + selectedRow.name });
      },
    },
  ];

  usePriceListObservers({ on, emit, pricesEvent: PRICES_EVENT, gridRef, pricesRef, pricesData });

  return (
    <div id="wrapper">
      <DxDataGrid
        id="price-list"
        ref={gridRef}
        title="Tarifas"
        toolbar={toolbar}
        url={API_URL}
        allowAdd
        allowEdit
        allowDelete
        fields={fields}
        events={EVENTS}
      />
      <DxDialog events={EVENTS}>
        <DxForm url={API_URL} fields={fields} events={EVENTS} />
      </DxDialog>
      <DxDialog events={PRICES_EVENT}>
        <DataGrid
          ref={pricesRef}
          dataSource={pricesData.current}
          height="100%"
          showBorders
          showRowLines
          onRowUpdated={(e: any) => {
            e.data.updated = true;
            e.component.saveEditData();
          }}
        >
          <Editing mode="cell" allowUpdating={true} />
          <Scrolling mode="virtual" />
          <Column dataField="id" caption="ID" visible={false} />
          <Column dataField="name" caption="Producto" minWidth={300} allowEditing={false} />
          <Column
            dataField="price"
            caption="Precio"
            alignment="right"
            dataType="number"
            cellRender={(cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value)}
          />
          <Column dataField="updated" caption="" visible={false} />
        </DataGrid>
      </DxDialog>
    </div>
  );
};

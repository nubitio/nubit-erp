import './PriceListPage.scss';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid, DxDataGridEvents } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { PriceListFields } from './PriceListFields';
import { useRef } from 'react';
import notify from 'devextreme/ui/notify';
import { DataGrid } from 'devextreme-react';
import { Column, DataGridRef, Editing, Scrolling } from 'devextreme-react/data-grid';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import NumberUtils from '@shared/lib/NumberUtils';
import { useEvents } from '@platform/event/EventHook';
import DataSource from 'devextreme/data/data_source';
import ArrayStore from 'devextreme/data/array_store';
import { usePriceListObservers } from '../model/usePriceListObservers';

export const PriceListPage = () => {
  const [on, emit] = useEvents();
  const container = 'wrapper';
  const fields = PriceListFields();
  const gridRef = useRef<DxDataGridEvents | null>(null);
  const pricesRef = useRef<DataGridRef | null>(null);
  const pricesData = useRef<DataSource>(
    new DataSource({
      store: new ArrayStore({
        key: 'id',
        data: [],
      }),
    }),
  );

  const API_URL = Constants.API_BASE_URL + 'price-lists';

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

  const toolbar = [
    {
      text: 'Precios',
      type: 'info',
      onClick: () => {
        const selectedRow = gridRef.current?.getSelectedRow();
        if (!selectedRow) {
          notify(
            {
              message: 'Debe seleccionar una tarifa',
              type: 'warning',
              displayTime: 3000,
            },
            { position: 'top right' },
          );
          return;
        }
        emit(PRICES_EVENT.ADD, { title: 'Precios de ' + selectedRow.name });
      },
    },
  ];

  usePriceListObservers({
    on,
    emit,
    pricesEvent: PRICES_EVENT,
    gridRef,
    pricesRef,
    pricesData,
  });

  return (
    <div id={container}>
      <DxDataGrid
        id={'price-list'}
        ref={gridRef}
        title={'Tarifas'}
        toolbar={toolbar}
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
      <DxDialog events={PRICES_EVENT}>
        <DataGrid
          ref={pricesRef}
          dataSource={pricesData.current}
          height={'100%'}
          showBorders={true}
          showRowLines={true}
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

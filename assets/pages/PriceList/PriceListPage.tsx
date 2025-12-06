import './PriceListPage.scss';
import { Constants } from '../../core/common/Constants';
import { DxDataGrid, DxDataGridEvents } from '../../core/datagrid/DxDataGrid';
import { DxDialog } from '../../core/dialog/DxDialog';
import { DxForm } from '../../core/form/DxForm';
import { PriceListFields } from './PriceListFields';
import { useEffect, useRef } from 'react';
import notify from 'devextreme/ui/notify';
import { DataGrid } from 'devextreme-react';
import { Column, DataGridRef, Editing, Scrolling } from 'devextreme-react/data-grid';
import { httpClient } from '../../core/http/HttpClient';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import NumberUtils from '../../core/utils/NumberUtils';
import { useEvents } from '../../core/event/EventHook';
import AppResponse from '../../core/http/AppResponse';
import toast from '../../core/utils/SharedUtils';
import DataSource from 'devextreme/data/data_source';
import ArrayStore from 'devextreme/data/array_store';

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
        let selectedRow = gridRef.current?.getSelectedRow();
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

  let alreadySetup = useRef(false);

  const setupObservers = () => {
    on(PRICES_EVENT.ADD, () => {
      let selectedRow = gridRef.current?.getSelectedRow();
      if (!selectedRow) {
        toast('Debe seleccionar una tarifa', 'warning');
      }
      httpClient
        .get<any>(Constants.API_BASE_URL + 'price-lists/' + selectedRow.id + '/prices')
        .subscribe((response) => {
          pricesRef.current?.instance().beginCustomLoading('Cargando precios...');
          pricesRef.current?.instance().option('dataSource', response.data);
          let store = pricesData.current.store() as ArrayStore;
          store.clear();
          response.data.forEach((item: any) => {
            store.insert(item);
          });
          pricesData.current.reload();
          pricesRef.current?.instance().endCustomLoading();
        });
    });

    on(PRICES_EVENT.SAVE, () => {
      let selectedRow = gridRef.current?.getSelectedRow();
      let pricesData: any[] = [];
      let updatedPrices = pricesRef.current?.instance().getDataSource().items();
      updatedPrices?.forEach((item: any) => {
        if (!item.updated) return;
        pricesData.push({
          productId: item.id,
          price: Number(item.price),
        });
      });
      if (pricesData.length === 0) {
        toast('No se han realizado cambios', 'warning');
        return;
      }
      httpClient
        .post<AppResponse>(Constants.API_BASE_URL + 'price-lists/update', {
          priceListId: selectedRow.id,
          prices: pricesData,
        })
        .subscribe((response) => {
          notify(
            {
              message: response.data.message,
              type: 'success',
              displayTime: 3000,
            },
            { position: 'top right' },
          );
          emit(PRICES_EVENT.SUCCESS);
        });
    });
  };

  useEffect(() => {
    if (!alreadySetup.current) {
      setupObservers();
      alreadySetup.current = true;
    }
  }, []);

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

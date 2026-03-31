import { useEffect, useRef } from 'react';
import { DxDataGridEvents } from '@core/datagrid/DxDataGrid';
import { DataGridRef } from 'devextreme-react/data-grid';
import { businessHttpClient, httpRequestData } from '@shared/api/network';
import { priceListEndpoints } from '../api/priceListEndpoints';
import ArrayStore from 'devextreme/data/array_store';
import DataSource from 'devextreme/data/data_source';
import AppResponse from '@shared/api/AppResponse';
import toast from '@shared/lib/SharedUtils';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UsePriceListObserversParams = {
  on: OnEventFn;
  emit: <T>(name: string, payload?: T) => void;
  pricesEvent: {
    ADD: string;
    SAVE: string;
    SUCCESS: string;
  };
  gridRef: React.RefObject<DxDataGridEvents | null>;
  pricesRef: React.RefObject<DataGridRef | null>;
  pricesData: React.RefObject<DataSource>;
};

type SelectedPriceListRow = {
  id: string | number;
};

export const usePriceListObservers = ({
  on,
  emit,
  pricesEvent,
  gridRef,
  pricesRef,
  pricesData,
}: UsePriceListObserversParams) => {
  const callbacksRef = useRef({ on, emit, pricesEvent, gridRef, pricesRef, pricesData });
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix
  callbacksRef.current = { on, emit, pricesEvent, gridRef, pricesRef, pricesData };

  useEffect(() => {
    const {
      on: onEvent,
      emit: emitEvent,
      pricesEvent: ev,
      gridRef: grid,
      pricesRef: prices,
      pricesData: data,
    } = callbacksRef.current;

    onEvent(ev.ADD, () => {
      const selectedRow = grid.current?.getSelectedRow() as SelectedPriceListRow | undefined;
      if (!selectedRow) {
        toast('Debe seleccionar una tarifa', 'warning');
        return;
      }

      prices.current?.instance().beginCustomLoading('Cargando precios...');

      void httpRequestData<any[]>(
        businessHttpClient.get<any[]>(priceListEndpoints.prices(selectedRow.id)),
      )
        .then((items) => {
          const store = data.current.store() as ArrayStore;
          store.clear();
          items.forEach((item: any) => {
            store.insert(item);
          });
          data.current.reload();
        })
        .catch(() => {
          toast('No se pudieron cargar los precios', 'error');
        })
        .finally(() => {
          prices.current?.instance().endCustomLoading();
        });
    });

    onEvent(ev.SAVE, () => {
      const selectedRow = grid.current?.getSelectedRow() as SelectedPriceListRow | undefined;
      if (!selectedRow) {
        toast('Debe seleccionar una tarifa', 'warning');
        return;
      }

      const pricesPayload: any[] = [];
      const updatedPrices = prices.current?.instance().getDataSource().items();
      updatedPrices?.forEach((item: any) => {
        if (!item.updated) return;
        pricesPayload.push({
          productId: item.id,
          price: Number(item.price),
        });
      });
      if (pricesPayload.length === 0) {
        toast('No se han realizado cambios', 'warning');
        return;
      }
      void httpRequestData<AppResponse>(
        businessHttpClient.post<AppResponse>(priceListEndpoints.updatePrices(), {
          priceListId: selectedRow.id,
          prices: pricesPayload,
        }),
      )
        .then((response) => {
          toast(response.message, 'success');
          emitEvent(ev.SUCCESS);
        })
        .catch(() => {
          toast('No se pudieron guardar los precios', 'error');
        });
    });
  }, []);
};

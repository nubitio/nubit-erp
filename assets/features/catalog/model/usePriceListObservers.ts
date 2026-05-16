import { useEffect, useRef } from 'react';
import type { GridHandle } from '@core/datagrid/GridHandle';
import { businessHttpClient, httpRequestData } from '@shared/api/network';
import { priceListEndpoints } from '../api/priceListEndpoints';
import AppResponse from '@shared/api/AppResponse';
import toast from '@shared/lib/SharedUtils';
import type { PriceEditorGridHandle, PriceEditorRow } from '../components/PriceEditorGrid';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UsePriceListObserversParams = {
  on: OnEventFn;
  emit: <T>(name: string, payload?: T) => void;
  pricesEvent: {
    ADD: string;
    SAVE: string;
    SUCCESS: string;
  };
  gridRef: React.RefObject<GridHandle | null>;
  pricesRef: React.RefObject<PriceEditorGridHandle | null>;
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
}: UsePriceListObserversParams) => {
  const callbacksRef = useRef({ on, emit, pricesEvent, gridRef, pricesRef });
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix
  callbacksRef.current = { on, emit, pricesEvent, gridRef, pricesRef };

  useEffect(() => {
    const {
      on: onEvent,
      emit: emitEvent,
      pricesEvent: ev,
      gridRef: grid,
      pricesRef: prices,
    } = callbacksRef.current;

    onEvent(ev.ADD, () => {
      const selectedRow = grid.current?.getSelectedRow() as SelectedPriceListRow | undefined;
      if (!selectedRow) {
        toast('Debe seleccionar una tarifa', 'warning');
        return;
      }

      prices.current?.showLoading('Cargando precios...');

      void httpRequestData<PriceEditorRow[]>(
        businessHttpClient.get<PriceEditorRow[]>(priceListEndpoints.prices(selectedRow.id)),
      )
        .then((items) => {
          prices.current?.setRows(items);
        })
        .catch(() => {
          toast('No se pudieron cargar los precios', 'error');
        })
        .finally(() => {
          prices.current?.hideLoading();
        });
    });

    onEvent(ev.SAVE, () => {
      const selectedRow = grid.current?.getSelectedRow() as SelectedPriceListRow | undefined;
      if (!selectedRow) {
        toast('Debe seleccionar una tarifa', 'warning');
        return;
      }

      const pricesPayload = (prices.current?.getUpdatedRows() ?? []).map((item) => ({
        productId: item.id,
        price: Number(item.price),
      }));
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

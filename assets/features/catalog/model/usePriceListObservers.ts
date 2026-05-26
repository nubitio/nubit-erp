import { useEffect, useRef } from 'react';
import type { DataRecord } from '@nubit/crud';
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
  selectedRowRef: React.RefObject<DataRecord | null>;
  pricesRef: React.RefObject<PriceEditorGridHandle | null>;
};

export const usePriceListObservers = ({
  on,
  emit,
  pricesEvent,
  selectedRowRef,
  pricesRef,
}: UsePriceListObserversParams) => {
  const callbacksRef = useRef({ on, emit, pricesEvent, selectedRowRef, pricesRef });
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix
  callbacksRef.current = { on, emit, pricesEvent, selectedRowRef, pricesRef };

  useEffect(() => {
    const {
      on: onEvent,
      emit: emitEvent,
      pricesEvent: ev,
      selectedRowRef: rowRef,
      pricesRef: prices,
    } = callbacksRef.current;

    onEvent(ev.ADD, () => {
      const row = rowRef.current;
      if (!row) {
        toast('Debe seleccionar una tarifa', 'warning');
        return;
      }

      prices.current?.showLoading('Cargando precios...');

      void httpRequestData<PriceEditorRow[]>(
        businessHttpClient.get<PriceEditorRow[]>(
          priceListEndpoints.prices(row.id as string | number),
        ),
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
      const row = rowRef.current;
      if (!row) {
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
          priceListId: row.id,
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

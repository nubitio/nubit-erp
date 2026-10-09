import { useEffect, useRef } from 'react';
import type { DataRecord } from '@nubitio/crud';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import { priceListEndpoints } from '../api/priceListEndpoints';
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
  pricesRef: React.RefObject<PriceEditorGridHandle | null>;
};

export const usePriceListObservers = ({
  on,
  emit,
  pricesEvent,
  pricesRef,
}: UsePriceListObserversParams) => {
  const currentPriceListRef = useRef<DataRecord | null>(null);
  const callbacksRef = useRef({ on, emit, pricesEvent, pricesRef });
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix
  callbacksRef.current = { on, emit, pricesEvent, pricesRef };

  useEffect(() => {
    const {
      on: onEvent,
      emit: emitEvent,
      pricesEvent: ev,
      pricesRef: prices,
    } = callbacksRef.current;

    onEvent<{ row?: DataRecord } | undefined>(ev.ADD, (payload) => {
      const row = payload?.row ?? null;
      currentPriceListRef.current = row;
      if (!row) {
        toast('Debe seleccionar una tarifa', 'warning');
        return;
      }

      prices.current?.setPriceListId(row.id as string | number);
      prices.current?.showLoading('Cargando precios...');

      void httpRequestData<PriceEditorRow[]>(
        httpClient.get<PriceEditorRow[]>(priceListEndpoints.prices(row.id as string | number)),
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
      const row = currentPriceListRef.current;
      if (!row) {
        toast('Debe seleccionar una tarifa', 'warning');
        return;
      }

      if (!prices.current?.hasChanges()) {
        toast('No se han realizado cambios', 'warning');
        return;
      }

      void prices.current
        .saveChanges()
        .then((saved) => {
          if (saved) emitEvent(ev.SUCCESS);
        })
        .catch(() => {
          toast('No se pudieron guardar los precios', 'error');
        });
    });
  }, []);
};

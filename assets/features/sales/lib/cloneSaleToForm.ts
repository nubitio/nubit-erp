import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';

type EmitFn = (eventName: string, payload?: any) => void;

type CloneSaleToFormParams = {
  saleId: string;
  emit: EmitFn;
  addEventName: string;
  loadingEventName: string;
};

export const cloneSaleToForm = ({
  saleId,
  emit,
  addEventName,
  loadingEventName,
}: CloneSaleToFormParams) => {
  emit(loadingEventName, true);

  void Promise.all([
    httpRequestData<any>(httpClient.get<any>(`${API}sales/${saleId}`)),
    httpRequestData<any>(
      httpClient.get<any>(`${API}sales/${saleId}/items`),
    ),
  ])
    .then(([sale, items]) => {
      emit(addEventName, {
        ...sale,
        writeItems: items,
      });
    })
    .catch(() => undefined)
    .finally(() => emit(loadingEventName, false));
};

import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';

type EmitFn = (eventName: string, payload?: unknown) => void;

type SaleCloneResponse = Record<string, unknown>;
type SaleCloneItem = Record<string, unknown>;

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
    httpRequestData<SaleCloneResponse>(httpClient.get<SaleCloneResponse>(`${API}sales/${saleId}`)),
    httpRequestData<SaleCloneItem[]>(
      httpClient.get<SaleCloneItem[]>(`${API}sales/${saleId}/items`),
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

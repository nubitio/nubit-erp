import { API_BASE_URL } from '@app/constants';
import { businessHttpClient, httpRequestData } from '@shared/api/network';

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
    httpRequestData<any>(businessHttpClient.get<any>(`${API_BASE_URL}sales/${saleId}`)),
    httpRequestData<any>(
      businessHttpClient.get<any>(`${API_BASE_URL}sales/${saleId}/items`),
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

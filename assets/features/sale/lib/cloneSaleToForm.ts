import { forkJoin, map, of, switchMap } from 'rxjs';
import { Constants } from '@core/common/Constants';
import { httpClient } from '@shared/api/HttpClient';

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

  httpClient
    .get<any>(`${Constants.API_BASE_URL}sales/${saleId}`)
    .pipe(
      switchMap((saleResponse) =>
        forkJoin({
          sale: of(saleResponse),
          items: httpClient.get<any>(`${Constants.API_BASE_URL}sales/${saleId}/items`),
        }),
      ),
      map(({ sale, items }) => ({
        ...sale.data,
        writeItems: items.data,
      })),
    )
    .subscribe({
      next: (data) => {
        emit(addEventName, data);
      },
      complete: () => emit(loadingEventName, false),
      error: () => emit(loadingEventName, false),
    });
};

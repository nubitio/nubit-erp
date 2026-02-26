import { httpClient } from '@shared/api/HttpClient';

type LoadMovementItemsOnEditParams = {
  apiUrl: string;
  movementId: string;
  setDetailData: (data: any[]) => void;
};

export const loadMovementItemsOnEdit = ({
  apiUrl,
  movementId,
  setDetailData,
}: LoadMovementItemsOnEditParams) => {
  httpClient.get<any>(`${apiUrl}/${movementId}/items`).subscribe((response) => {
    setDetailData(response.data);
  });
};

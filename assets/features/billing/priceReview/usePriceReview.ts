import { useQuery } from '@tanstack/react-query';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import type { PriceReviewLine } from './priceReview';

export function usePriceReview() {
  return useQuery({
    queryKey: ['sales', 'price-review'],
    retry: false,
    queryFn: async () =>
      (
        await httpClient.get<PriceReviewLine[]>(`${getCoreApiBaseUrl()}sale-price-review`, {
          errorPresentation: 'inline',
        })
      ).data,
  });
}

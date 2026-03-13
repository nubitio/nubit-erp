import { firstValueFrom } from 'rxjs';
import { useQuery } from '@tanstack/react-query';
import { kardexService } from '@features/kardex';
import { DateUtils } from '@shared/lib/DateUtils';

export interface KardexRow {
  producto_id: string | number;
  producto: string;
  saldo: number;
  ingresos: number;
  salidas: number;
  stock: number;
}

export const useKardexQuery = (dateFrom: Date, dateTo: Date) => {
  const { data, isLoading, isError, error, refetch } = useQuery<KardexRow[], Error>({
    queryKey: ['kardex', DateUtils.dateFormatter(dateFrom), DateUtils.dateFormatter(dateTo)],
    queryFn: () =>
      firstValueFrom(
        kardexService.getAll(DateUtils.dateFormatter(dateFrom), DateUtils.dateFormatter(dateTo)),
      ).then((response) => response.data ?? []),
    staleTime: 30_000,
  });

  return {
    rows: data ?? [],
    isLoading,
    isError,
    errorMessage: error?.message ?? 'No se pudo cargar el Kardex',
    refetch: () => void refetch(),
  };
};

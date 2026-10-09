import { useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { CashRegisterStatus } from '../types';

export const useCashRegisters = () => {
  const {
    data,
    isFetching: isLoading,
    error: queryError,
    refetch,
  } = useQuery<CashRegisterStatus[], Error>({
    queryKey: ['cash-registers-dashboard'],
    queryFn: () =>
      httpClient
        .get<CashRegisterStatus[]>(API + 'cash-registers/dashboard')
        .then((response) => response.data ?? []),
    staleTime: 30_000,
    refetchInterval: 60_000,
    placeholderData: [],
  });

  const error = queryError ? (queryError.message ?? 'No se pudo cargar las cajas') : null;

  return {
    cashRegisters: data ?? [],
    isLoading,
    error,
    refetch: () => void refetch(),
  };
};

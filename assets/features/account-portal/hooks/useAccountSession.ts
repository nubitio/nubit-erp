import { useQuery, useQueryClient } from '@tanstack/react-query';
import { accountPortalClient } from '../api/accountPortalClient';

export function useAccountSession() {
  return useQuery({
    queryKey: ['account-session'],
    queryFn: accountPortalClient.getSession,
    staleTime: 30_000,
  });
}

export function useInvalidateAccountSession() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ['account-session'] });
}

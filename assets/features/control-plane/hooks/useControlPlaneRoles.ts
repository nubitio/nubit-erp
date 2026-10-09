import { useQuery } from '@tanstack/react-query';
import { controlPlaneClient } from '../api/controlPlaneClient';

export const useControlPlaneRoles = () => {
  const session = useQuery({
    queryKey: ['control-plane', 'session'],
    queryFn: () => controlPlaneClient.getSession(),
  });

  const roles = session.data?.roles ?? [];

  return {
    user: session.data?.user ?? null,
    roles,
    isAdmin: roles.includes('ROLE_ADMIN') || roles.includes('ROLE_SUPER_ADMIN'),
    isSuperAdmin: roles.includes('ROLE_SUPER_ADMIN'),
    loading: session.isLoading,
  };
};

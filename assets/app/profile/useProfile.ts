import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useAuth } from '@app/contexts/auth';
import { profileService, type Profile } from './profileService';

export const PROFILE_KEY = ['profile'] as const;

export const useProfile = () =>
  useQuery({
    queryKey: PROFILE_KEY,
    queryFn: async () => (await profileService.get()).data,
    staleTime: 60_000,
  });

/**
 * Call after any change that returns the fresh profile: it updates the cache and the signed-in
 * user the shell reads (name and photo in the header and the menu).
 */
export const useAcceptProfile = () => {
  const queryClient = useQueryClient();
  const { updateUser } = useAuth();

  return (profile: Profile) => {
    queryClient.setQueryData(PROFILE_KEY, profile);
    updateUser({
      name: profile.displayName ?? '',
      email: profile.email ?? '',
      avatarUrl: profile.avatarUrl ?? '',
    });
  };
};

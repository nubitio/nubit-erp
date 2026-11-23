import { useQuery } from '@tanstack/react-query';
import type { AdminMenuItem } from '@nubitio/admin';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { userInformation } from '@shared/sessionInfo';
import { menuService } from './services';

const NO_ITEMS: AdminMenuItem[] = [];

export interface WorkspaceMenu {
  /** The menu the signed-in user may see: their role's, narrowed by plan, capabilities and permissions. */
  items: AdminMenuItem[];
  /** Still working out which destinations the user has; `items` is empty meanwhile. */
  isLoading: boolean;
}

/**
 * The authorized menu, shared by the shell and by screens that live outside it (the POS), so both
 * read the same destinations. A failed request leaves an empty menu, as the shell always did.
 */
export function useWorkspaceMenu(): WorkspaceMenu {
  const { data: runtimeConfig, isError } = useTenantRuntimeConfig();
  const roleId = userInformation().currentUserRoleId ?? 0;
  const ready = runtimeConfig !== undefined || isError;

  const query = useQuery<AdminMenuItem[]>({
    queryKey: ['workspace-menu', roleId, runtimeConfig ?? null],
    enabled: ready,
    queryFn: async () => {
      try {
        const response = runtimeConfig
          ? await menuService.getMenuByRoleIdForRuntimeConfig(roleId, runtimeConfig)
          : await menuService.getMenuByRoleId(roleId);
        return response.data;
      } catch {
        return NO_ITEMS;
      }
    },
  });

  return { items: query.data ?? NO_ITEMS, isLoading: !ready || query.data === undefined };
}

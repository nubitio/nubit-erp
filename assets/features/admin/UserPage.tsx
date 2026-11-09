import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { SchemaCrudPage } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useConfirm } from '@nubitio/ui';

import { httpClient } from '@shared/api/HttpClient';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';
import { InviteUserDialog } from './components/InviteUserDialog';
import { invitationErrorMessage } from './usePendingInvitations';
import { userResource } from './userResource';

const SECURITY_QUERY_KEY = ['admin', 'user-security'] as const;

interface UserRow {
  id?: number | string;
  username?: string;
}

/** Users, with an administrator's "Restablecer 2FA" on the rows that have a second factor. */
export const UserPage = () => {
  const queryClient = useQueryClient();
  const { ask, ConfirmHost } = useConfirm();
  const canReset = Boolean(window.app_identity) && hasPermission('users.edit');
  const canInvite = Boolean(window.app_identity) && hasPermission('users.create');
  const [inviteOpen, setInviteOpen] = useState(false);

  const security = useQuery({
    queryKey: SECURITY_QUERY_KEY,
    enabled: canReset,
    retry: false,
    queryFn: async () =>
      (
        await httpClient.get<{ enabled: boolean; withSecondFactor: string[] }>(
          `${getCoreApiBaseUrl()}user-security`,
          { errorPresentation: 'inline' },
        )
      ).data.withSecondFactor,
  });

  const reset = useMutation({
    mutationFn: (row: UserRow) => httpClient.delete(`${getCoreApiBaseUrl()}users/${row.id}/totp`),
    onSuccess: (_result, row) => {
      toast(`${row.username} puede entrar solo con su contraseña.`, 'success');
      void queryClient.invalidateQueries({ queryKey: SECURITY_QUERY_KEY });
    },
    onError: (err) =>
      toast(invitationErrorMessage(err, 'No se pudo restablecer la verificación.'), 'error'),
  });

  const withSecondFactor = security.data;
  const resource = useMemo(
    () => ({
      ...userResource,
      // "Invitar usuario" sits in the grid's own toolbar, next to "Nuevo".
      toolbar: canInvite
        ? {
            primary: [
              {
                key: 'invite',
                text: 'Invitar usuario',
                icon: 'ph-envelope-simple',
                type: 'secondary',
                onClick: () => setInviteOpen(true),
              },
            ],
          }
        : undefined,
      rowActions: (row: UserRow) =>
        canReset && row.username && withSecondFactor?.includes(row.username)
          ? [
              {
                key: 'reset-2fa',
                text: 'Restablecer 2FA',
                icon: 'ph-shield-slash',
                type: 'warning',
                onClick: () =>
                  ask({
                    title: 'Restablecer verificación en dos pasos',
                    message: `${row.username} podrá entrar solo con su contraseña y tendrá que activarla de nuevo. Úsalo si perdió su teléfono y sus códigos de recuperación.`,
                    variant: 'warning',
                    confirmLabel: 'Restablecer',
                    onConfirm: () => reset.mutate(row),
                  }),
              },
            ]
          : [],
    }),
    [canReset, canInvite, withSecondFactor, ask, reset],
  );

  return (
    <>
      <SchemaCrudPage resource={resource} />
      <ConfirmHost />
      {inviteOpen && <InviteUserDialog onClose={() => setInviteOpen(false)} />}
    </>
  );
};

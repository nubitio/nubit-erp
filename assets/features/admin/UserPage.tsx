import { useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  ResourceStoreProvider,
  SchemaCrudPage,
  useResourceStoreFactory,
  type GridHandle,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useConfirm } from '@nubitio/ui';

import { httpClient } from '@shared/api/HttpClient';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';
import { InviteUserDialog } from './components/InviteUserDialog';
import { invitationErrorMessage, usePendingInvitationActions } from './usePendingInvitations';
import { userDirectoryEndpoints, userEndpoints } from './api/adminEndpoints';
import { userResource } from './userResource';
import { isInvitationRow, withUserDirectory } from './usersWithInvitations';

const SECURITY_QUERY_KEY = ['admin', 'user-security'] as const;

interface UserRow {
  id?: number | string;
  username?: string;
}

/**
 * Users and the people invited who have not accepted yet, in one grid: an invitation is a row with
 * the "Invitación pendiente" status that is resent or revoked. Administrators also get
 * "Restablecer 2FA" on the users that have a second factor.
 */
export const UserPage = () => {
  const parentFactory = useResourceStoreFactory();
  const factory = useMemo(
    () => withUserDirectory(parentFactory, userEndpoints.list(), userDirectoryEndpoints.list()),
    [parentFactory],
  );

  return (
    <ResourceStoreProvider factory={factory}>
      <UsersGrid />
    </ResourceStoreProvider>
  );
};

const UsersGrid = () => {
  const queryClient = useQueryClient();
  const gridRef = useRef<GridHandle>(null);
  const { ask, ConfirmHost } = useConfirm();
  const canReset = Boolean(window.app_identity) && hasPermission('users.edit');
  const canInvite = Boolean(window.app_identity) && hasPermission('users.create');
  const [inviteOpen, setInviteOpen] = useState(false);
  const { resend, revoke, busy } = usePendingInvitationActions(() => gridRef.current?.refresh());

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
      rowActions: (row: UserRow) => {
        if (isInvitationRow(row)) {
          const email = row.username ?? '';
          return canInvite
            ? [
                {
                  key: 'resend',
                  text: 'Reenviar invitación',
                  icon: 'ph-paper-plane-tilt',
                  disabled: busy,
                  onClick: () => resend.mutate(email),
                },
                {
                  key: 'revoke',
                  text: 'Revocar invitación',
                  icon: 'ph-prohibit',
                  type: 'danger',
                  disabled: busy,
                  onClick: () =>
                    ask({
                      title: 'Revocar invitación',
                      message: `El enlace enviado a ${email} dejará de funcionar de inmediato.`,
                      variant: 'danger',
                      confirmLabel: 'Revocar',
                      onConfirm: () => revoke.mutate(email),
                    }),
                },
              ]
            : [];
        }
        return canReset && row.username && withSecondFactor?.includes(row.username)
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
          : [];
      },
    }),
    [canReset, canInvite, withSecondFactor, ask, reset, resend, revoke, busy],
  );

  return (
    <>
      <SchemaCrudPage resource={resource} gridRef={gridRef} />
      <ConfirmHost />
      {inviteOpen && (
        <InviteUserDialog
          onClose={() => {
            setInviteOpen(false);
            gridRef.current?.refresh();
          }}
        />
      )}
    </>
  );
};

import { useMemo, useRef, useState } from 'react';
import {
  DataGridView,
  HydraAdapter,
  identityField,
  textField,
  type DataRecord,
  type GridCellContext,
  type GridHandle,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { Badge, useConfirm } from '@nubitio/ui';

import './InvitationsPage.scss';
import { hasPermission } from '@shared/permissions';
import { InviteUserDialog } from './components/InviteUserDialog';
import {
  expiryLabel,
  invitationsAvailable,
  usePendingInvitationActions,
  type PendingInvitation,
} from './usePendingInvitations';

type InvitationRow = DataRecord & PendingInvitation & { id: string };

/** Ajustes › Invitaciones: everyone invited who has not accepted yet, with "Reenviar" and "Revocar". */
export function InvitationsPage() {
  const gridRef = useRef<GridHandle>(null);
  const [inviteOpen, setInviteOpen] = useState(false);
  const { resend, revoke, busy } = usePendingInvitationActions(() => gridRef.current?.refresh());
  const { ask, ConfirmHost } = useConfirm();
  const canManage = hasPermission('users.create');

  const fields = useMemo(
    () => [
      identityField().visible(false).visibleOnForm(false).build(),
      textField()
        .name('email')
        .label('Correo')
        .width(360)
        .cardRole('title')
        .filterable(false)
        .sortable(false)
        .formatter((cell: GridCellContext) => <strong>{String(cell.value ?? '')}</strong>)
        .build(),
      textField()
        .name('role')
        .label('Rol')
        .width(110)
        .filterable(false)
        .sortable(false)
        .formatter((cell: GridCellContext) => {
          const role = (cell.data as InvitationRow | undefined)?.role;
          return role ? (
            <Badge pill variant="secondary">
              {role.name}
            </Badge>
          ) : null;
        })
        .build(),
      textField()
        .name('invitedBy')
        .label('Invitó')
        .width(130)
        .filterable(false)
        .sortable(false)
        .formatter((cell: GridCellContext) => String(cell.value ?? '—'))
        .build(),
      textField()
        .name('expiresAt')
        .label('Vence')
        .width(150)
        .filterable(false)
        .sortable(false)
        .formatter((cell: GridCellContext) => expiryLabel(String(cell.value ?? '')))
        .build(),
    ],
    [],
  );

  if (!invitationsAvailable()) {
    return <p>Las invitaciones no están habilitadas.</p>;
  }

  return (
    <div className="invitations-page" data-testid="invitations-page">
      <DataGridView
        id="pending-invitations"
        ref={gridRef}
        title="Invitaciones pendientes"
        url={`${getCoreApiBaseUrl()}user-invitations`}
        fields={fields}
        adapter={HydraAdapter}
        appearance="page"
        allowAdd={false}
        allowEdit={false}
        allowDelete={false}
        toolbar={
          canManage
            ? {
                primary: [
                  {
                    key: 'invite',
                    text: 'Invitar usuario',
                    icon: 'ph-envelope-simple',
                    type: 'primary',
                    onClick: () => setInviteOpen(true),
                  },
                ],
              }
            : undefined
        }
        rowActions={(row) =>
          canManage
            ? [
                {
                  key: 'resend',
                  text: 'Reenviar',
                  icon: 'ph-paper-plane-tilt',
                  disabled: busy,
                  onClick: () => resend.mutate((row as InvitationRow).email),
                },
                {
                  key: 'revoke',
                  text: 'Revocar',
                  icon: 'ph-prohibit',
                  type: 'danger',
                  disabled: busy,
                  onClick: () =>
                    ask({
                      title: 'Revocar invitación',
                      message: `El enlace enviado a ${(row as InvitationRow).email} dejará de funcionar de inmediato.`,
                      variant: 'danger',
                      confirmLabel: 'Revocar',
                      onConfirm: () => revoke.mutate((row as InvitationRow).email),
                    }),
                },
              ]
            : []
        }
        emptyState={{
          title: 'No hay invitaciones pendientes',
          description: 'Cuando invites a alguien aparecerá aquí hasta que acepte.',
          icon: 'envelope-simple',
        }}
      />
      <ConfirmHost />
      {inviteOpen && (
        <InviteUserDialog
          onClose={() => {
            setInviteOpen(false);
            gridRef.current?.refresh();
          }}
        />
      )}
    </div>
  );
}

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { Badge, Button, useConfirm } from '@nubitio/ui';
import { DateTime } from 'luxon';

import { httpClient } from '@shared/api/HttpClient';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';

import './PendingInvitations.scss';

export const PENDING_INVITATIONS_QUERY_KEY = ['admin', 'pending-invitations'] as const;

interface PendingInvitation {
  email: string;
  role: { id: number; name: string } | null;
  invitedBy: string | null;
  expiresAt: string;
}

const api = () => `${getCoreApiBaseUrl()}user-invitations`;

/** "vence en 3 días" / "vence mañana" / "vence hoy", by calendar day in the viewer's zone. */
export function expiryLabel(expiresAt: string, now: DateTime = DateTime.now()): string {
  const end = DateTime.fromISO(expiresAt).setZone(now.zone);
  if (!end.isValid) return '';
  const days = Math.round(end.startOf('day').diff(now.startOf('day'), 'days').days);
  if (days <= 0) return 'vence hoy';
  return days === 1 ? 'vence mañana' : `vence en ${days} días`;
}

/** How many invitations show before "Ver las N restantes": the list must not push the users grid away. */
export const COLLAPSED_COUNT = 3;

export function visibleInvitations<T>(items: T[], expanded: boolean): T[] {
  return expanded ? items : items.slice(0, COLLAPSED_COUNT);
}

/** The server's message when it is Spanish; never an English or technical one. */
export function invitationErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { data?: { message?: string } })?.data?.message;
  return message && /[áéíóúñ¿¡]|Esa invitación|Solo un|Escribe|Ese correo/.test(message)
    ? message
    : fallback;
}

/** Invitations sent but not yet accepted, with "Reenviar" and "Revocar". Only when invitations exist. */
export function PendingInvitations() {
  const enabled = Boolean(window.app_identity) && hasPermission('users.view');
  const canManage = hasPermission('users.create');
  const queryClient = useQueryClient();
  const { ask, ConfirmHost } = useConfirm();
  const [expanded, setExpanded] = useState(false);

  const query = useQuery({
    queryKey: PENDING_INVITATIONS_QUERY_KEY,
    enabled,
    retry: false,
    queryFn: async () =>
      (await httpClient.get<{ items: PendingInvitation[] }>(api(), { errorPresentation: 'inline' }))
        .data.items,
  });
  const refresh = () => queryClient.invalidateQueries({ queryKey: PENDING_INVITATIONS_QUERY_KEY });

  const resend = useMutation({
    mutationFn: (email: string) => httpClient.post(`${api()}/resend`, { email }),
    onSuccess: (_result, email) => {
      toast(`Enviamos un nuevo enlace a ${email}.`, 'success');
      void refresh();
    },
    onError: (err) =>
      toast(invitationErrorMessage(err, 'No se pudo reenviar la invitación.'), 'error'),
  });
  const revoke = useMutation({
    mutationFn: (email: string) => httpClient.post(`${api()}/revoke`, { email }),
    onSuccess: (_result, email) => {
      toast(`Invitación a ${email} revocada.`, 'success');
      void refresh();
    },
    onError: (err) =>
      toast(invitationErrorMessage(err, 'No se pudo revocar la invitación.'), 'error'),
  });

  const items = query.data ?? [];
  if (!enabled || items.length === 0) return null;
  const busy = resend.isPending || revoke.isPending;

  return (
    <section
      className="pending-invitations"
      aria-label="Invitaciones pendientes"
      data-testid="pending-invitations"
    >
      <h3>Invitaciones pendientes</h3>
      <ul>
        {visibleInvitations(items, expanded).map((invitation) => (
          <li key={invitation.email} data-testid="pending-invitation">
            <span className="pending-invitations__who">
              <strong>{invitation.email}</strong>
              <small>
                {invitation.invitedBy ? `Invitó ${invitation.invitedBy} · ` : ''}
                {expiryLabel(invitation.expiresAt)}
              </small>
            </span>
            {invitation.role ? (
              <Badge pill variant="secondary">
                {invitation.role.name}
              </Badge>
            ) : null}
            {canManage ? (
              <span className="pending-invitations__actions">
                <Button
                  variant="secondary"
                  size="sm"
                  disabled={busy}
                  onClick={() => resend.mutate(invitation.email)}
                >
                  Reenviar
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  disabled={busy}
                  onClick={() =>
                    ask({
                      title: 'Revocar invitación',
                      message: `El enlace enviado a ${invitation.email} dejará de funcionar de inmediato.`,
                      variant: 'danger',
                      confirmLabel: 'Revocar',
                      onConfirm: () => revoke.mutate(invitation.email),
                    })
                  }
                >
                  Revocar
                </Button>
              </span>
            ) : null}
          </li>
        ))}
      </ul>
      {items.length > COLLAPSED_COUNT ? (
        <Button
          variant="ghost"
          size="sm"
          aria-expanded={expanded}
          data-testid="pending-invitations-toggle"
          onClick={() => setExpanded((current) => !current)}
        >
          {expanded ? 'Mostrar menos' : `Ver las ${items.length - COLLAPSED_COUNT} restantes`}
        </Button>
      ) : null}
      <ConfirmHost />
    </section>
  );
}

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { DateTime } from 'luxon';

import { httpClient } from '@shared/api/HttpClient';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';

export const PENDING_INVITATIONS_QUERY_KEY = ['admin', 'pending-invitations'] as const;

export interface PendingInvitation {
  id: string;
  email: string;
  role: { id: number; name: string } | null;
  invitedBy: string | null;
  expiresAt: string;
}

const api = () => `${getCoreApiBaseUrl()}user-invitations`;

/** Invitations exist only while the identity module is on, and listing needs users.view. */
export const invitationsAvailable = (): boolean =>
  Boolean(window.app_identity) && hasPermission('users.view');

/** "vence en 3 días" / "vence mañana" / "vence hoy", by calendar day in the viewer's zone. */
export function expiryLabel(expiresAt: string, now: DateTime = DateTime.now()): string {
  const end = DateTime.fromISO(expiresAt).setZone(now.zone);
  if (!end.isValid) return '';
  const days = Math.round(end.startOf('day').diff(now.startOf('day'), 'days').days);
  if (days <= 0) return 'vence hoy';
  return days === 1 ? 'vence mañana' : `vence en ${days} días`;
}

/** The server's message when it is Spanish; never an English or technical one. */
export function invitationErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { data?: { message?: string } })?.data?.message;
  return message && /[áéíóúñ¿¡]|Esa invitación|Solo un|Escribe|Ese correo/.test(message)
    ? message
    : fallback;
}

/** Resend / revoke, with the toasts and the list refresh. */
export function usePendingInvitationActions(onChanged?: () => void) {
  const queryClient = useQueryClient();
  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: PENDING_INVITATIONS_QUERY_KEY });
    onChanged?.();
  };

  const resend = useMutation({
    mutationFn: (email: string) => httpClient.post(`${api()}/resend`, { email }),
    onSuccess: (_result, email) => {
      toast(`Enviamos un nuevo enlace a ${email}.`, 'success');
      refresh();
    },
    onError: (err) =>
      toast(invitationErrorMessage(err, 'No se pudo reenviar la invitación.'), 'error'),
  });
  const revoke = useMutation({
    mutationFn: (email: string) => httpClient.post(`${api()}/revoke`, { email }),
    onSuccess: (_result, email) => {
      toast(`Invitación a ${email} revocada.`, 'success');
      refresh();
    },
    onError: (err) =>
      toast(invitationErrorMessage(err, 'No se pudo revocar la invitación.'), 'error'),
  });

  return { resend, revoke, busy: resend.isPending || revoke.isPending };
}

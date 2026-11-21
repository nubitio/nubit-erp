import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, AppDialog, Button, Combobox, FormField, FormLayout, TextField } from '@nubitio/ui';

import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import toast from '@shared/lib/SharedUtils';
import { roleEndpoints } from '../api/roleEndpoints';
import { PENDING_INVITATIONS_QUERY_KEY } from '../usePendingInvitations';

import './InviteUserDialog.scss';

const FORM_ID = 'invite-user-form';

/** Role id 1 (super administrator) can only be handed out by a super administrator: use the API. */
const SUPER_ADMIN_ROLE_ID = 1;

interface RoleRow {
  id: number;
  name: string;
}

/** Spanish copy for the few messages the invitation endpoint can return. */
export function inviteErrorMessage(err: unknown): string {
  const message = (err as { data?: { message?: string } })?.data?.message;
  return message && /[áéíóúñ¿¡]|Escribe|Elige|Ese correo|Solo un/.test(message)
    ? message
    : 'No se pudo enviar la invitación. Inténtalo de nuevo.';
}

/** Dialog to invite a user by e-mail. Opened from the users grid toolbar (see UserPage). */
export const InviteUserDialog = ({ onClose }: { onClose: () => void }) => {
  const queryClient = useQueryClient();
  const roles = useQuery({
    queryKey: ['admin', 'roles', 'invite'],
    retry: false,
    queryFn: async () =>
      (await httpClient.get<RoleRow[]>(`${roleEndpoints.list()}?itemsPerPage=100`)).data,
  });
  const [email, setEmail] = useState('');
  const [roleId, setRoleId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const roleOptions = (Array.isArray(roles.data) ? roles.data : [])
    .filter((role) => role.id !== SUPER_ADMIN_ROLE_ID)
    .map((role) => ({ value: String(role.id), label: role.name }));
  const canSubmit = Boolean(email.trim() && roleId);

  const submit = async () => {
    setError(null);
    setLoading(true);
    try {
      await httpClient.post(`${getCoreApiBaseUrl()}user-invitations`, {
        email: email.trim(),
        roleId: Number(roleId),
      });
      toast(`Invitación enviada a ${email.trim()}.`, 'success');
      void queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
      void queryClient.invalidateQueries({ queryKey: PENDING_INVITATIONS_QUERY_KEY });
      onClose();
    } catch (err) {
      setError(inviteErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  return (
    <AppDialog
      open
      title="Invitar usuario"
      onClose={() => {
        if (!loading) onClose();
      }}
      closeOnEscape={!loading}
      showCloseButton={!loading}
      width={440}
      footer={
        <>
          <Button variant="secondary" disabled={loading} onClick={onClose}>
            Cancelar
          </Button>
          <Button
            type="submit"
            form={FORM_ID}
            variant="primary"
            loading={loading}
            disabled={!canSubmit}
          >
            Enviar invitación
          </Button>
        </>
      }
    >
      <FormLayout
        id={FORM_ID}
        onSubmit={(event) => {
          event.preventDefault();
          if (canSubmit) void submit();
        }}
      >
        <p className="invite-user__intro">
          Le enviaremos un correo con un enlace para que cree su propia contraseña. El enlace vence
          en 7 días.
        </p>
        <FormField label="Correo" required>
          <TextField
            type="email"
            autoComplete="off"
            required
            value={email}
            disabled={loading}
            onChange={(event) => {
              setEmail(event.target.value);
              setError(null);
            }}
          />
        </FormField>
        <FormField label="Rol" required>
          <Combobox
            aria-label="Rol"
            required
            placeholder="Elige un rol…"
            options={roleOptions}
            value={roleId}
            disabled={loading || roles.isPending}
            onChange={setRoleId}
          />
        </FormField>
        {error && <Alert tone="danger">{error}</Alert>}
      </FormLayout>
    </AppDialog>
  );
};

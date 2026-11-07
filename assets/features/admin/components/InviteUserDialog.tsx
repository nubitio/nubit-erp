import React, { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AppDialog, Button, SelectField, TextField } from '@nubitio/ui';

import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';
import { roleEndpoints } from '../api/roleEndpoints';

import './InviteUserDialog.scss';

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

const InviteForm = ({ onClose }: { onClose: () => void }) => {
  const queryClient = useQueryClient();
  const roles = useQuery({
    queryKey: ['admin', 'roles', 'invite'],
    retry: false,
    queryFn: async () =>
      (await httpClient.get<RoleRow[]>(`${roleEndpoints.list()}?itemsPerPage=100`)).data,
  });
  const [email, setEmail] = useState('');
  const [roleId, setRoleId] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const options = (Array.isArray(roles.data) ? roles.data : []).filter(
    (role) => role.id !== SUPER_ADMIN_ROLE_ID,
  );

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
      onClose={onClose}
      width={440}
      footer={
        <>
          <Button variant="secondary" disabled={loading} onClick={onClose}>
            Cancelar
          </Button>
          <Button
            variant="primary"
            loading={loading}
            disabled={!email.trim() || !roleId}
            onClick={() => void submit()}
          >
            Enviar invitación
          </Button>
        </>
      }
    >
      <div className="invite-user">
        <p>
          Le enviaremos un correo con un enlace para que cree su propia contraseña. El enlace vence
          en 7 días.
        </p>
        <label className="invite-user__field">
          <span>Correo</span>
          <TextField
            type="email"
            autoComplete="off"
            aria-label="Correo"
            value={email}
            disabled={loading}
            onChange={(event) => {
              setEmail(event.target.value);
              setError(null);
            }}
          />
        </label>
        <label className="invite-user__field">
          <span>Rol</span>
          <SelectField
            aria-label="Rol"
            value={roleId}
            disabled={loading || roles.isPending}
            onChange={(event) => setRoleId(event.target.value)}
          >
            <option value="">Elige un rol…</option>
            {options.map((role) => (
              <option key={role.id} value={role.id}>
                {role.name}
              </option>
            ))}
          </SelectField>
        </label>
        {error && (
          <div className="invite-user__error" role="alert">
            {error}
          </div>
        )}
      </div>
    </AppDialog>
  );
};

/** Toolbar above the users grid. Rendered only when invitations exist and the user may create users. */
export function InviteUserBar() {
  const [open, setOpen] = useState(false);
  if (!window.app_identity || !hasPermission('users.create')) return null;

  return (
    <div className="invite-user-bar">
      <Button variant="secondary" onClick={() => setOpen(true)}>
        <i className="ph ph-envelope-simple" aria-hidden="true" /> Invitar usuario
      </Button>
      {open && <InviteForm onClose={() => setOpen(false)} />}
    </div>
  );
}

import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import { Button, Card, FormField, TextField } from '@nubitio/ui';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { RoleCheckboxes } from '../components/admin/RoleCheckboxes';
import { CpAlert, CpForm, CpFormActions, CpPage, CpPageHeader } from '../components/layout';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';
import { CP_STRINGS } from '../i18n/es';

export const UserCreatePage = () => {
  const navigate = useNavigate();
  const { isSuperAdmin } = useControlPlaneRoles();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [roles, setRoles] = useState<string[]>(['ROLE_ADMIN']);
  const [error, setError] = useState<string | null>(null);

  const create = useMutation({
    mutationFn: () =>
      controlPlaneClient.createAdminUser({
        username,
        password,
        confirmPassword,
        roles,
      }),
    onSuccess: (result) => {
      navigate(`/users/${result.id}`);
    },
    onError: (err: Error) => setError(err.message),
  });

  if (!isSuperAdmin) {
    return (
      <CpPage>
        <CpAlert tone="danger">{CP_STRINGS.superAdminRequired}</CpAlert>
      </CpPage>
    );
  }

  return (
    <CpPage>
      <CpPageHeader
        title={CP_STRINGS.users.createTitle}
        breadcrumb={{ label: CP_STRINGS.users.title, to: '/users' }}
      />

      <div className="cp-narrow">
      <Card variant="panel">
        {error && <CpAlert tone="danger">{error}</CpAlert>}
        <CpForm
          onSubmit={(e) => {
            e.preventDefault();
            create.mutate();
          }}
        >
          <FormField label={CP_STRINGS.users.username} helpText={CP_STRINGS.users.usernameHint}>
            <TextField
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              pattern="[a-z0-9._-]{3,64}"
              required
              autoComplete="off"
            />
          </FormField>
          <FormField label={CP_STRINGS.users.password}>
            <TextField
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              minLength={12}
              required
              autoComplete="new-password"
            />
          </FormField>
          <FormField label={CP_STRINGS.users.confirmPassword}>
            <TextField
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              minLength={12}
              required
              autoComplete="new-password"
            />
          </FormField>
          <FormField label={CP_STRINGS.users.roles}>
            <RoleCheckboxes selected={roles} onChange={setRoles} />
          </FormField>
          <CpFormActions>
            <Button type="submit" loading={create.isPending}>
              {CP_STRINGS.users.createSubmit}
            </Button>
            <Link to="/users">
              <Button variant="ghost">{CP_STRINGS.cancel}</Button>
            </Link>
          </CpFormActions>
        </CpForm>
      </Card>
      </div>
    </CpPage>
  );
};
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { Button, Card, FormField, TextField } from '@nubitio/ui';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { RoleCheckboxes } from '../components/admin/RoleCheckboxes';
import {
  CpAlert,
  CpForm,
  CpFormActions,
  CpHubTabs,
  CpPage,
  CpPageHeader,
  CpPageSkeleton,
} from '../components/layout';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';
import { useConfirm } from '@nubitio/ui';
import { CP_STRINGS } from '../i18n/es';
import { cpNotify } from '../utils/notify';

type UserTab = 'general' | 'password';

const USER_TABS: Array<{ id: UserTab; label: string }> = [
  { id: 'general', label: CP_STRINGS.users.tabGeneral },
  { id: 'password', label: CP_STRINGS.users.tabPassword },
];

export const UserEditPage = () => {
  const { id } = useParams<{ id: string }>();
  const userId = Number(id);
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const { isSuperAdmin, user: currentUser } = useControlPlaneRoles();
  const { ask, ConfirmHost } = useConfirm();

  const detail = useQuery({
    queryKey: ['control-plane', 'users', userId],
    queryFn: () => controlPlaneClient.getAdminUser(userId),
    enabled: Number.isFinite(userId) && userId > 0,
  });

  const [roles, setRoles] = useState<string[] | null>(null);
  const [isActive, setIsActive] = useState<boolean | null>(null);
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState<string | null>(null);

  const activeTab: UserTab = searchParams.get('tab') === 'password' ? 'password' : 'general';
  const user = detail.data?.user;
  const selectedRoles = roles ?? user?.roles ?? [];
  const activeStatus = isActive ?? user?.is_active ?? true;
  const isSelf = currentUser === user?.username;

  const save = useMutation({
    mutationFn: () =>
      controlPlaneClient.updateAdminUser(userId, {
        roles: selectedRoles,
        isActive: activeStatus,
      }),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ['control-plane', 'users', userId] });
      queryClient.invalidateQueries({ queryKey: ['control-plane', 'users'] });
      cpNotify.success(CP_STRINGS.saved);
    },
    onError: (err: Error) => setError(err.message),
  });

  const resetPassword = useMutation({
    mutationFn: () =>
      controlPlaneClient.resetAdminUserPassword(userId, { password, confirmPassword }),
    onSuccess: () => {
      setPassword('');
      setConfirmPassword('');
      setError(null);
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

  if (!Number.isFinite(userId) || userId <= 0) {
    return (
      <CpPage>
        <CpAlert tone="danger">{CP_STRINGS.users.invalidId}</CpAlert>
      </CpPage>
    );
  }

  if (detail.isLoading) {
    return <CpPageSkeleton variant="form" />;
  }

  if (detail.error || !user) {
    return (
      <CpPage>
        <CpAlert tone="danger">
          {(detail.error as Error)?.message ?? CP_STRINGS.userNotFound}
        </CpAlert>
        <Link to="/users">
          <Button variant="ghost" icon="ph-arrow-left">
            {CP_STRINGS.users.backToUsers}
          </Button>
        </Link>
      </CpPage>
    );
  }

  const setTab = (tab: UserTab) => {
    setSearchParams(tab === 'general' ? {} : { tab });
  };

  return (
    <CpPage>
      <CpPageHeader
        title={user.username}
        subtitle={CP_STRINGS.users.editSubtitle}
        breadcrumb={{ label: CP_STRINGS.users.title, to: '/users' }}
        badge={{
          label: user.is_active ? CP_STRINGS.active : CP_STRINGS.inactive,
          variant: user.is_active ? 'success' : 'warning',
        }}
      />

      <CpHubTabs
        tabs={USER_TABS}
        active={activeTab}
        onChange={setTab}
        ariaLabel={CP_STRINGS.users.title}
      />

      <div className="cp-tab-panel">
        {error && <CpAlert tone="danger">{error}</CpAlert>}

        {activeTab === 'general' && (
          <div className="cp-narrow">
          <Card variant="panel">
            <CpForm
              onSubmit={(e) => {
                e.preventDefault();
                save.mutate();
              }}
            >
              <FormField
                label={CP_STRINGS.users.username}
                helpText={CP_STRINGS.users.usernameReadonly}
              >
                <TextField value={user.username} readOnly disabled />
              </FormField>
              <FormField label={CP_STRINGS.users.roles}>
                <RoleCheckboxes selected={selectedRoles} onChange={setRoles} />
              </FormField>
              <label className="cp-check cp-check--block">
                <input
                  type="checkbox"
                  checked={activeStatus}
                  disabled={isSelf}
                  onChange={(e) => setIsActive(e.target.checked)}
                />
                <span>{CP_STRINGS.active}</span>
              </label>
              {isSelf && (
                <CpAlert tone="warning">{CP_STRINGS.users.cannotDeactivateSelf}</CpAlert>
              )}
              <CpFormActions>
                <Button type="submit" loading={save.isPending}>
                  {CP_STRINGS.users.saveChanges}
                </Button>
                <Link to="/users">
                  <Button variant="ghost">{CP_STRINGS.cancel}</Button>
                </Link>
              </CpFormActions>
            </CpForm>
          </Card>
          </div>
        )}

        {activeTab === 'password' && (
          <div className="cp-narrow">
          <Card variant="panel">
            <p className="cp-muted">{CP_STRINGS.users.passwordHint(user.username)}</p>
            <CpForm
              onSubmit={(e) => {
                e.preventDefault();
                ask({
                  title: CP_STRINGS.users.resetConfirmTitle,
                  message: CP_STRINGS.users.resetConfirm(user.username),
                  variant: 'warning',
                  onConfirm: () => resetPassword.mutate(),
                });
              }}
            >
              <FormField label={CP_STRINGS.users.newPassword}>
                <TextField
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  minLength={12}
                  required
                  autoComplete="new-password"
                />
              </FormField>
              <FormField label={CP_STRINGS.users.confirmNewPassword}>
                <TextField
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  minLength={12}
                  required
                  autoComplete="new-password"
                />
              </FormField>
              <Button type="submit" variant="ghost" loading={resetPassword.isPending}>
                {CP_STRINGS.users.changePassword}
              </Button>
            </CpForm>
          </Card>
          </div>
        )}
      </div>
      <ConfirmHost />
    </CpPage>
  );
};
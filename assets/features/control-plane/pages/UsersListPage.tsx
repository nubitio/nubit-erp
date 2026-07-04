import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import { Badge, Button, Card } from '@nubitio/ui';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { CpAlert, CpPage, CpPageHeader, CpPageSkeleton, CpRowActions } from '../components/layout';
import { CP_STRINGS } from '../i18n/es';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';
import { useCpConfirm } from '../hooks/useCpConfirm';

const roleBadgeVariant = (role: string): 'danger' | 'info' | 'secondary' => {
  if (role === 'ROLE_SUPER_ADMIN') return 'danger';
  if (role === 'ROLE_ADMIN') return 'info';
  return 'secondary';
};

export const UsersListPage = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { isSuperAdmin, user: currentUser } = useControlPlaneRoles();
  const { ask, ConfirmHost } = useCpConfirm();

  const { data, isLoading, error } = useQuery({
    queryKey: ['control-plane', 'users'],
    queryFn: () => controlPlaneClient.getAdminUsers(),
  });

  const toggle = useMutation({
    mutationFn: (id: number) => controlPlaneClient.toggleAdminUser(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['control-plane', 'users'] }),
  });

  if (!isSuperAdmin) {
    return (
      <CpPage>
        <CpAlert tone="danger">{CP_STRINGS.superAdminRequired}</CpAlert>
      </CpPage>
    );
  }

  if (isLoading) {
    return <CpPageSkeleton variant="table" />;
  }

  if (error || !data) {
    return (
      <CpPage>
        <CpAlert tone="danger">
          {CP_STRINGS.users.loadError}: {(error as Error)?.message ?? CP_STRINGS.unknownError}
        </CpAlert>
      </CpPage>
    );
  }

  const me = currentUser;

  return (
    <CpPage fill wide>
      <CpPageHeader
        title={CP_STRINGS.users.title}
        subtitle={CP_STRINGS.users.subtitle}
        actions={
          <Link to="/users/new">
            <Button>{CP_STRINGS.users.new}</Button>
          </Link>
        }
      />

      <Card className="cp-table-surface">
        {data.users.length === 0 ? (
          <p className="cp-muted">{CP_STRINGS.users.empty}</p>
        ) : (
          <div className="cp-data-table-wrap cp-data-table-wrap--fill">
            <table className="cp-data-table">
              <thead>
                <tr>
                  <th>{CP_STRINGS.users.colId}</th>
                  <th>{CP_STRINGS.users.colUsername}</th>
                  <th>{CP_STRINGS.users.colRoles}</th>
                  <th>{CP_STRINGS.users.colStatus}</th>
                  <th>{CP_STRINGS.users.colLastLogin}</th>
                  <th>{CP_STRINGS.users.colCreated}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {data.users.map((user) => {
                  const isSelf = me === user.username;
                  return (
                    <tr key={user.id}>
                      <td className="cp-muted">{user.id}</td>
                      <td>
                        <strong>{user.username}</strong>
                        {isSelf && (
                          <Badge variant="info" size="sm" pill>
                            {CP_STRINGS.users.you}
                          </Badge>
                        )}
                      </td>
                      <td>
                        {user.roles
                          .filter((role) => role !== 'ROLE_USER')
                          .map((role) => (
                            <Badge key={role} variant={roleBadgeVariant(role)} size="sm" pill>
                              {role}
                            </Badge>
                          ))}
                      </td>
                      <td>
                        <Badge
                          variant={user.is_active ? 'success' : 'secondary'}
                          size="sm"
                          pill
                        >
                          {user.is_active ? CP_STRINGS.active : CP_STRINGS.inactive}
                        </Badge>
                      </td>
                      <td className="cp-muted">
                        {user.last_login_at ? user.last_login_at.slice(0, 16) : '—'}
                      </td>
                      <td className="cp-muted">
                        {user.created_at ? user.created_at.slice(0, 10) : '—'}
                      </td>
                      <td className="cp-data-table__actions">
                        <CpRowActions
                          items={[
                            {
                              label: CP_STRINGS.edit,
                              icon: 'pencil-simple',
                              onClick: () => navigate(`/users/${user.id}`),
                            },
                            ...(!isSelf
                              ? [
                                  {
                                    label: user.is_active
                                      ? CP_STRINGS.users.deactivate
                                      : CP_STRINGS.users.activate,
                                    icon: user.is_active ? 'pause' : 'play',
                                    onClick: () => {
                                      const action = user.is_active
                                        ? CP_STRINGS.users.deactivate
                                        : CP_STRINGS.users.activate;
                                      ask({
                                        title: CP_STRINGS.users.toggleConfirmTitle,
                                        message: CP_STRINGS.users.toggleConfirm(
                                          action,
                                          user.username,
                                        ),
                                        variant: 'warning',
                                        onConfirm: () => toggle.mutate(user.id),
                                      });
                                    },
                                  },
                                ]
                              : []),
                          ]}
                        />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </Card>
      <ConfirmHost />
    </CpPage>
  );
};
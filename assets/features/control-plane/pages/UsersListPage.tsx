import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import { Badge, Button, Card, DataTable, useConfirm } from '@nubitio/ui';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { CpAlert, CpPage, CpPageHeader, CpPageSkeleton } from '../components/layout';
import { CP_STRINGS } from '../i18n/es';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';

const roleBadgeVariant = (role: string): 'danger' | 'info' | 'secondary' => {
  if (role === 'ROLE_SUPER_ADMIN') return 'danger';
  if (role === 'ROLE_ADMIN') return 'info';
  return 'secondary';
};

export const UsersListPage = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { isSuperAdmin, user: currentUser } = useControlPlaneRoles();
  const { ask, ConfirmHost } = useConfirm();

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

      <Card className="nb-table-surface">
        <DataTable
          fill
          rows={data.users}
          getRowKey={(user) => user.id}
          emptyMessage={CP_STRINGS.users.empty}
          columns={[
            {
              id: 'id',
              header: CP_STRINGS.users.colId,
              muted: true,
              accessor: (user) => user.id,
            },
            {
              id: 'username',
              header: CP_STRINGS.users.colUsername,
              render: (user) => (
                <>
                  <strong>{user.username}</strong>
                  {me === user.username && (
                    <Badge variant="info" size="sm" pill>
                      {CP_STRINGS.users.you}
                    </Badge>
                  )}
                </>
              ),
            },
            {
              id: 'roles',
              header: CP_STRINGS.users.colRoles,
              render: (user) =>
                user.roles
                  .filter((role) => role !== 'ROLE_USER')
                  .map((role) => (
                    <Badge key={role} variant={roleBadgeVariant(role)} size="sm" pill>
                      {role}
                    </Badge>
                  )),
            },
            {
              id: 'status',
              header: CP_STRINGS.users.colStatus,
              render: (user) => (
                <Badge variant={user.is_active ? 'success' : 'secondary'} size="sm" pill>
                  {user.is_active ? CP_STRINGS.active : CP_STRINGS.inactive}
                </Badge>
              ),
            },
            {
              id: 'lastLogin',
              header: CP_STRINGS.users.colLastLogin,
              muted: true,
              accessor: (user) => (user.last_login_at ? user.last_login_at.slice(0, 16) : '—'),
            },
            {
              id: 'created',
              header: CP_STRINGS.users.colCreated,
              muted: true,
              accessor: (user) => (user.created_at ? user.created_at.slice(0, 10) : '—'),
            },
          ]}
          rowActions={(user) => {
            const isSelf = me === user.username;
            return [
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
                          message: CP_STRINGS.users.toggleConfirm(action, user.username),
                          variant: 'warning' as const,
                          onConfirm: () => toggle.mutate(user.id),
                        });
                      },
                    },
                  ]
                : []),
            ];
          }}
        />
      </Card>
      <ConfirmHost />
    </CpPage>
  );
};
import { useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { entityLabel } from '@shared/lib/displayFormat';
import { userEndpoints } from '../api/adminEndpoints';
import './RoleUserSummary.scss';

/** Above this many users the first page is not the whole tenant, so counts would mislead. */
const PAGE_SIZE = 200;

interface UserRow {
  role?: unknown;
}

export function countUsersByRole(users: readonly UserRow[]) {
  const counts = new Map<string, number>();
  for (const user of users) {
    const name = entityLabel(user.role) || 'Sin rol';
    counts.set(name, (counts.get(name) ?? 0) + 1);
  }
  return [...counts].map(([name, count]) => ({ name, count }));
}

export function RoleUserSummary() {
  const users = useQuery({
    queryKey: ['admin', 'users', 'role-summary'],
    retry: false,
    queryFn: async () =>
      (await httpClient.get<UserRow[]>(`${userEndpoints.list()}?itemsPerPage=${PAGE_SIZE}`)).data,
  });
  if (!users.data || !Array.isArray(users.data) || users.data.length >= PAGE_SIZE) return null;
  const roles = countUsersByRole(users.data);
  if (roles.length === 0) return null;

  return (
    <section className="role-user-summary" aria-label="Usuarios por rol">
      {roles.map(({ name, count }) => (
        <div key={name} className="role-user-summary__item">
          <strong>{name}</strong>
          <span>
            {count} {count === 1 ? 'usuario' : 'usuarios'}
          </span>
        </div>
      ))}
    </section>
  );
}

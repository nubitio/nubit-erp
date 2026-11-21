import {
  defineFields,
  defineResource,
  passwordField,
  textField,
  type DataRecord,
  type GridCellContext,
} from '@nubitio/crud';
import { Avatar, Badge } from '@nubitio/ui';
import { DateTime } from 'luxon';
import { entityLabel } from '@shared/lib/displayFormat';
import './userResource.scss';
import { userEndpoints } from './api/adminEndpoints';
import { expiryLabel } from './usePendingInvitations';
import { isInvitationRow, type UserGridRow } from './usersWithInvitations';

interface UserRecord extends DataRecord {
  id: number;
  username: string;
  role: unknown;
  email?: string | null;
  displayName?: string | null;
  phone?: string | null;
  lastLoginAt?: string | null;
  plainPassword?: string;
}

const plainPasswordField = passwordField()
  .name('plainPassword')
  .label('Contraseña')
  .helpText('Requerida al crear un usuario.')
  .visible(false)
  .build();

const USER_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['username', 'role', 'status', 'phone', 'lastLoginAt'],
  },
];

const statusField = textField()
  .name('status')
  .label('Estado')
  .width(170)
  .visibleOnForm(false)
  .filterable(false)
  .sortable(false)
  .formatter((cell: GridCellContext) =>
    cell.value === 'invited' ? (
      <Badge pill variant="warning">
        Invitación pendiente
      </Badge>
    ) : (
      <Badge pill variant="success">
        Activo
      </Badge>
    ),
  )
  .build();

const usernameCell = (cell: GridCellContext) => {
  const row = cell.data as UserGridRow | undefined;
  if (isInvitationRow(row)) {
    return (
      <span className="user-cell">
        <span className="user-cell__icon">
          <i className="ph ph-envelope-simple" aria-hidden="true" />
        </span>
        <span className="user-cell__text">
          <strong title={row.username}>{row.username}</strong>
          <small>{expiryLabel(String(row.expiresAt ?? ''))}</small>
        </span>
      </span>
    );
  }
  const user = row as UserRecord | undefined;
  const username = String(cell.value ?? '');
  const name = user?.displayName?.trim() || username;
  // The login name and the e-mail, unless the name already is the login name.
  const detail = [name === username ? null : username, user?.email].filter(Boolean).join(' · ');
  return (
    <span className="user-cell">
      <span className="user-cell__avatar">
        <Avatar owner={name} size={32} />
      </span>
      <span className="user-cell__text">
        <strong title={name}>{name}</strong>
        {detail ? <small title={detail}>{detail}</small> : null}
      </span>
    </span>
  );
};

/** "Hoy, 09:12" / "Ayer, 21:04" / "12/10/2026" / "—" (never signed in). */
export function formatLastLogin(value: unknown, now: DateTime = DateTime.now()): string {
  if (typeof value !== 'string' || value === '') return '—';
  const at = DateTime.fromISO(value).setZone(now.zone);
  if (!at.isValid) return '—';
  const days = now.startOf('day').diff(at.startOf('day'), 'days').days;
  if (days === 0) return `Hoy, ${at.toFormat('HH:mm')}`;
  if (days === 1) return `Ayer, ${at.toFormat('HH:mm')}`;
  return at.toFormat('dd/MM/yyyy');
}

const lastLoginCell = (cell: GridCellContext) => formatLastLogin(cell.value);

const roleCell = (cell: GridCellContext) => {
  const name = entityLabel(cell.value);
  return name ? (
    <Badge pill variant="secondary">
      {name}
    </Badge>
  ) : null;
};

export const userResource = defineResource<UserRecord>(userEndpoints.list(), {
  id: 'user',
  title: 'Usuarios',
  gridAppearance: 'page',
  filterRow: true,
  filterToolbarFields: ['username', 'role'],
  viewMode: 'dialog',
  defaultPreset: 'basic',
  // A pending invitation is not a user yet: it is resent or revoked, never edited or deleted.
  permissions: {
    canEditRow: (row) => !isInvitationRow(row),
    canDeleteRow: (row) => !isInvitationRow(row),
  },
  columnPresets: USER_COLUMN_PRESETS,
  fieldContract: defineFields<UserRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'username', patch: { label: 'Usuario', formatter: usernameCell } },
      { kind: 'override', key: 'role', patch: { label: 'Rol', formatter: roleCell } },
      { kind: 'override', key: 'email', patch: { label: 'Correo', visible: false } },
      // Read-only here: people change their own name and phone from "Ajustes › Perfil".
      {
        kind: 'override',
        key: 'displayName',
        patch: { label: 'Nombre', visible: false, visibleOnForm: false },
      },
      { kind: 'override', key: 'phone', patch: { label: 'Teléfono', visibleOnForm: false } },
      {
        kind: 'override',
        key: 'lastLoginAt',
        patch: { label: 'Último ingreso', formatter: lastLoginCell, visibleOnForm: false },
      },
      { kind: 'append', field: statusField },
      {
        kind: 'append',
        field: {
          ...plainPasswordField,
          operation: {
            only: 'create',
            required: { create: true },
          },
        },
      },
    ],
  }),
});

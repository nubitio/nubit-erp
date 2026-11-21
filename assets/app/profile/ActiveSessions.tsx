import { useCallback, useEffect, useState } from 'react';
import { DateTime } from 'luxon';
import { Alert, DataTable, Skeleton, Text, useConfirm, Button } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { describeUserAgent } from './describeUserAgent';
import { profileService, type SessionInfo } from './profileService';

const when = (iso: string | null): string =>
  iso ? DateTime.fromISO(iso).setLocale('es').toFormat('dd/MM/yyyy HH:mm') : '—';

/** Where the account is open. Needs the identity module (the session list is its API). */
export const ActiveSessions = () => {
  const [sessions, setSessions] = useState<SessionInfo[] | null>(null);
  const [failed, setFailed] = useState(false);
  const { ask, ConfirmHost } = useConfirm();

  const load = useCallback(async () => {
    try {
      setSessions((await profileService.sessions()).data.sessions);
      setFailed(false);
    } catch {
      setFailed(true);
    }
  }, []);

  useEffect(() => {
    queueMicrotask(() => void load());
  }, [load]);

  const revoke = (session: SessionInfo) =>
    ask({
      title: 'Cerrar sesión',
      message:
        'Se cerrará la sesión en ese dispositivo. Si es la que estás usando ahora, tendrás que volver a ingresar.',
      variant: 'danger',
      confirmLabel: 'Cerrar sesión',
      onConfirm: () => {
        void profileService
          .revokeSession(session.id)
          .then(() => {
            toast('Sesión cerrada', 'success');
            return load();
          })
          .catch(() => undefined);
      },
    });

  if (failed) return <Alert tone="warning">No se pudo cargar la lista de sesiones.</Alert>;
  if (sessions === null) return <Skeleton variant="text" lines={3} />;

  return (
    <>
      <div className="settings-sessions" data-testid="profile-sessions">
        <DataTable<SessionInfo>
          variant="flush"
          rows={sessions}
          getRowKey={(session) => session.id}
          emptyMessage={<Text tone="muted">No hay sesiones abiertas.</Text>}
          columns={[
            {
              id: 'device',
              header: 'Dispositivo',
              render: (session) => (
                <>
                  <strong>{describeUserAgent(session.userAgent)}</strong>
                  {session.ipAddress && <Text tone="muted"> · {session.ipAddress}</Text>}
                </>
              ),
            },
            {
              id: 'signedIn',
              header: 'Inicio',
              nowrap: true,
              muted: true,
              accessor: (session) => when(session.createdAt),
            },
            {
              id: 'lastUsed',
              header: 'Último uso',
              nowrap: true,
              muted: true,
              accessor: (session) => when(session.lastUsedAt ?? session.createdAt),
            },
          ]}
          renderActions={(session) => (
            <Button type="button" variant="ghost" size="sm" onClick={() => revoke(session)}>
              Cerrar
            </Button>
          )}
        />
      </div>
      <ConfirmHost />
    </>
  );
};

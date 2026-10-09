import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AppDialog,
  Badge,
  Button,
  Card,
  DataTable,
  EmptyState,
  Text,
  TextField,
  useConfirm,
} from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import {
  integrationService,
  type ApiClientDto,
  type ApiKeyDto,
} from '../services/integrationService';
import { SecretRevealDialog } from './SecretRevealDialog';

const QUERY_KEY = ['settings', 'integration', 'api-clients'];

const SCOPE_LABELS: Record<string, string> = {
  'documents:write': 'Emitir comprobantes',
  'documents:read': 'Consultar comprobantes',
  'webhooks:manage': 'Gestionar webhooks',
};

interface KeyRow {
  client: ApiClientDto;
  key: ApiKeyDto | null;
}

export const IntegrationApiKeysCard = () => {
  const queryClient = useQueryClient();
  const { ask, ConfirmHost } = useConfirm();

  const [formOpen, setFormOpen] = useState(false);
  const [name, setName] = useState('');
  const [scopes, setScopes] = useState<string[]>(['documents:write', 'documents:read']);
  const [revealed, setRevealed] = useState<{ secret: string; title: string } | null>(null);

  const query = useQuery({
    queryKey: QUERY_KEY,
    queryFn: () => integrationService.listApiClients(),
  });
  const invalidate = () => queryClient.invalidateQueries({ queryKey: QUERY_KEY });

  const createMutation = useMutation({
    mutationFn: () => integrationService.createApiClient(name.trim(), scopes),
    onSuccess: (client) => {
      setRevealed({ secret: client.secret, title: 'Llave de API creada' });
      setFormOpen(false);
      setName('');
      invalidate();
    },
  });

  const rotateMutation = useMutation({
    mutationFn: (clientId: string) => integrationService.rotateApiKey(clientId),
    onSuccess: (client) => {
      setRevealed({ secret: client.secret, title: 'Nueva llave generada' });
      invalidate();
    },
  });

  const revokeMutation = useMutation({
    mutationFn: (keyId: string) => integrationService.revokeApiKey(keyId),
    onSuccess: () => {
      toast('Llave revocada', 'success');
      invalidate();
    },
  });

  const grantable = query.data?.grantable_scopes ?? Object.keys(SCOPE_LABELS);
  const clients = query.data?.data ?? [];
  const rows: KeyRow[] = clients.flatMap((client) =>
    (client.keys.length ? client.keys : [null]).map((key) => ({ client, key })),
  );

  const toggleScope = (scope: string) =>
    setScopes((current) =>
      current.includes(scope) ? current.filter((s) => s !== scope) : [...current, scope],
    );

  return (
    <Card
      title="Llaves de API"
      description="Credenciales que otros sistemas (p. ej. nubit-control) usan para llamar a /api/v1."
    >
      <div className="integration-card__head">
        <Button variant="primary" size="sm" onClick={() => setFormOpen(true)}>
          Nueva llave
        </Button>
      </div>

      {clients.length === 0 ? (
        <EmptyState
          icon="key"
          title="Sin llaves"
          description="Crea una llave para que un sistema externo pueda emitir comprobantes."
        />
      ) : (
        <DataTable<KeyRow>
          variant="flush"
          rows={rows}
          getRowKey={({ client, key }) => key?.id ?? client.id}
          columns={[
            {
              id: 'client',
              header: 'Cliente',
              render: ({ client }) => (
                <div>
                  <strong>{client.name}</strong>
                  <div className="integration-chips">
                    {client.scopes.map((scope) => (
                      <Badge key={scope} variant="light" size="sm">
                        {SCOPE_LABELS[scope] ?? scope}
                      </Badge>
                    ))}
                  </div>
                </div>
              ),
            },
            {
              id: 'key',
              header: 'Llave',
              render: ({ key }) =>
                key ? (
                  <code className="integration-prefix">{key.prefix}…</code>
                ) : (
                  <Text tone="muted">—</Text>
                ),
            },
            {
              id: 'status',
              header: 'Estado',
              render: ({ key }) => {
                if (!key) return <Text tone="muted">—</Text>;
                if (key.revoked_at) return <Badge variant="danger">Revocada</Badge>;
                return (
                  <Badge variant="success">
                    {key.last_used_at
                      ? `Usada ${new Date(key.last_used_at).toLocaleDateString('es-PE')}`
                      : 'Activa'}
                  </Badge>
                );
              },
            },
          ]}
          renderActions={({ client, key }) => (
            <div className="integration-row-actions">
              <Button
                size="sm"
                variant="ghost"
                loading={rotateMutation.isPending}
                onClick={() => rotateMutation.mutate(client.id)}
              >
                Rotar
              </Button>
              {key && !key.revoked_at ? (
                <Button
                  size="sm"
                  variant="danger"
                  onClick={() =>
                    ask({
                      title: 'Revocar llave',
                      message: `${key.prefix}… dejará de funcionar de inmediato.`,
                      variant: 'danger',
                      onConfirm: () => revokeMutation.mutate(key.id),
                    })
                  }
                >
                  Revocar
                </Button>
              ) : null}
            </div>
          )}
        />
      )}

      <AppDialog
        open={formOpen}
        title="Nueva llave de API"
        onClose={() => setFormOpen(false)}
        maxWidth="30rem"
        footer={
          <div className="integration-dialog__footer">
            <Button variant="ghost" onClick={() => setFormOpen(false)}>
              Cancelar
            </Button>
            <Button
              variant="primary"
              loading={createMutation.isPending}
              disabled={!name.trim() || scopes.length === 0}
              onClick={() => createMutation.mutate()}
            >
              Crear
            </Button>
          </div>
        }
      >
        <label className="integration-field">
          <span>Nombre</span>
          <TextField
            value={name}
            placeholder="nubit-control — facturación"
            onChange={(e) => setName(e.target.value)}
          />
        </label>
        <fieldset className="integration-field">
          <span>Permisos</span>
          {grantable.map((scope) => (
            <label key={scope} className="integration-check">
              <input
                type="checkbox"
                checked={scopes.includes(scope)}
                onChange={() => toggleScope(scope)}
              />
              {SCOPE_LABELS[scope] ?? scope}
            </label>
          ))}
        </fieldset>
      </AppDialog>

      <ConfirmHost />

      {revealed ? (
        <SecretRevealDialog
          open
          title={revealed.title}
          secret={revealed.secret}
          hint="Guárdala en el secret store del sistema que la usará (EFACT_API_KEY en nubit-control)."
          onClose={() => setRevealed(null)}
        />
      ) : null}
    </Card>
  );
};

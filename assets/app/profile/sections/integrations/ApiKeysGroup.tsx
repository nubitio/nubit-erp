import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  AppDialog,
  Badge,
  Button,
  Checkbox,
  FormField,
  FormLayout,
  Skeleton,
  TextField,
  useConfirm,
} from '@nubitio/ui';
import { SecretRevealDialog } from '@features/settings/components/SecretRevealDialog';
import {
  integrationService,
  type ApiClientDto,
  type ApiKeyDto,
} from '@features/settings/services/integrationService';
import toast from '@shared/lib/SharedUtils';
import { validateApiClientName } from '../../integrationValidation';
import { SettingsGroup, SettingsRow } from '../../settings/SettingsRow';

const API_CLIENTS_KEY = ['settings', 'integration', 'api-clients'] as const;
const FORM_ID = 'api-key-form';

const SCOPE_LABELS: Record<string, string> = {
  'documents:write': 'Emitir comprobantes',
  'documents:read': 'Consultar comprobantes',
  'webhooks:manage': 'Gestionar webhooks',
};

interface Reveal {
  secret: string;
  title: string;
}

/** "Llaves de API": the credentials other systems use to call /api/v1. */
export const ApiKeysGroup = ({ canEdit }: { canEdit: boolean }) => {
  const queryClient = useQueryClient();
  const { ask, ConfirmHost } = useConfirm();
  const [creating, setCreating] = useState(false);
  const [revealed, setRevealed] = useState<Reveal | null>(null);

  const query = useQuery({
    queryKey: API_CLIENTS_KEY,
    queryFn: () => integrationService.listApiClients(),
  });
  const refresh = () => queryClient.invalidateQueries({ queryKey: API_CLIENTS_KEY });

  const rotate = useMutation({
    mutationFn: (clientId: string) => integrationService.rotateApiKey(clientId),
    onSuccess: (client) => {
      setRevealed({ secret: client.secret, title: 'Nueva llave generada' });
      void refresh();
    },
  });
  const revoke = useMutation({
    mutationFn: (keyId: string) => integrationService.revokeApiKey(keyId),
    onSuccess: () => {
      toast('Llave revocada', 'success');
      void refresh();
    },
  });

  if (query.isError) return <Alert tone="warning">No se pudieron cargar las llaves de API.</Alert>;
  if (query.isPending) return <Skeleton variant="rect" height={140} />;

  const entries = query.data.data.flatMap((client) =>
    (client.keys.length ? client.keys : [null]).map((key) => ({ client, key })),
  );

  return (
    <>
      <SettingsGroup
        title="Llaves de API"
        action={
          canEdit && (
            <Button
              type="button"
              variant="primary"
              size="sm"
              icon="ph ph-plus"
              onClick={() => setCreating(true)}
            >
              Nueva llave
            </Button>
          )
        }
      >
        {entries.length === 0 && (
          <p className="settings-muted">
            Sin llaves. Crea una para que un sistema externo pueda emitir comprobantes.
          </p>
        )}
        {entries.map(({ client, key }) => (
          <SettingsRow
            key={key?.id ?? client.id}
            wrap
            label={client.name}
            description={describe(client, key)}
          >
            <KeyState keyInfo={key} />
            {canEdit && (
              <>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  loading={rotate.isPending && rotate.variables === client.id}
                  onClick={() => rotate.mutate(client.id)}
                >
                  Rotar
                </Button>
                {key && !key.revoked_at && (
                  <Button
                    type="button"
                    variant="danger"
                    size="sm"
                    onClick={() =>
                      ask({
                        title: 'Revocar llave',
                        message: `${key.prefix}… dejará de funcionar de inmediato.`,
                        variant: 'danger',
                        onConfirm: () => revoke.mutate(key.id),
                      })
                    }
                  >
                    Revocar
                  </Button>
                )}
              </>
            )}
          </SettingsRow>
        ))}
      </SettingsGroup>

      {creating && (
        <NewKeyDialog
          grantable={query.data.grantable_scopes}
          onClose={() => setCreating(false)}
          onCreated={(secret) => {
            setCreating(false);
            setRevealed({ secret, title: 'Llave de API creada' });
            void refresh();
          }}
        />
      )}
      <ConfirmHost />
      {revealed && (
        <SecretRevealDialog
          open
          title={revealed.title}
          secret={revealed.secret}
          hint="Guárdala en el secret store del sistema que la usará (EFACT_API_KEY en nubit-control)."
          onClose={() => setRevealed(null)}
        />
      )}
    </>
  );
};

const describe = (client: ApiClientDto, key: ApiKeyDto | null) =>
  [
    client.scopes.map((scope) => SCOPE_LABELS[scope] ?? scope).join(', '),
    key ? `${key.prefix}…` : null,
  ]
    .filter(Boolean)
    .join(' · ');

const KeyState = ({ keyInfo }: { keyInfo: ApiKeyDto | null }) => {
  if (!keyInfo) return <Badge variant="secondary">Sin llave</Badge>;
  if (keyInfo.revoked_at) return <Badge variant="danger">Revocada</Badge>;
  return (
    <Badge variant="success">
      {keyInfo.last_used_at
        ? `Usada ${new Date(keyInfo.last_used_at).toLocaleDateString('es-PE')}`
        : 'Activa'}
    </Badge>
  );
};

const NewKeyDialog = ({
  grantable,
  onClose,
  onCreated,
}: {
  grantable: string[];
  onClose: () => void;
  onCreated: (secret: string) => void;
}) => {
  const [name, setName] = useState('');
  const [scopes, setScopes] = useState<string[]>(['documents:write', 'documents:read']);
  const [submitted, setSubmitted] = useState(false);

  const create = useMutation({
    mutationFn: () => integrationService.createApiClient(name.trim(), scopes),
    onSuccess: (client) => onCreated(client.secret),
  });

  const nameIssue = validateApiClientName(name);
  const scopeIssue = scopes.length === 0 ? 'Elige al menos un permiso.' : null;
  const saving = create.isPending;

  const toggle = (scope: string) =>
    setScopes((current) =>
      current.includes(scope) ? current.filter((item) => item !== scope) : [...current, scope],
    );

  return (
    <AppDialog
      open
      title="Nueva llave de API"
      width={480}
      closeOnOutsideClick={false}
      closeOnEscape={!saving}
      showCloseButton={!saving}
      onClose={() => {
        if (!saving) onClose();
      }}
      footer={
        <>
          <Button type="button" variant="secondary" disabled={saving} onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form={FORM_ID} variant="primary" loading={saving}>
            Crear llave
          </Button>
        </>
      }
    >
      <FormLayout
        id={FORM_ID}
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          setSubmitted(true);
          if (nameIssue || scopeIssue) return;
          create.mutate();
        }}
      >
        <FormField
          label="Nombre"
          htmlFor="api-key-name"
          required
          helpText="Para reconocer qué sistema la usa."
          error={submitted ? (nameIssue ?? undefined) : undefined}
        >
          <TextField
            id="api-key-name"
            autoFocus
            required
            maxLength={120}
            placeholder="nubit-control — facturación"
            value={name}
            invalid={submitted && nameIssue !== null}
            onChange={(event) => setName(event.target.value)}
          />
        </FormField>
        <FormField label="Permisos" error={submitted ? (scopeIssue ?? undefined) : undefined}>
          <div className="settings-checks">
            {grantable.map((scope) => (
              <Checkbox
                key={scope}
                label={SCOPE_LABELS[scope] ?? scope}
                checked={scopes.includes(scope)}
                onChange={() => toggle(scope)}
              />
            ))}
          </div>
        </FormField>
      </FormLayout>
    </AppDialog>
  );
};

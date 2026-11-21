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
  type BadgeVariant,
} from '@nubitio/ui';
import { SecretRevealDialog } from '@features/settings/components/SecretRevealDialog';
import {
  integrationService,
  type WebhookDeliveryDto,
  type WebhookEndpointDto,
} from '@features/settings/services/integrationService';
import toast from '@shared/lib/SharedUtils';
import { validateWebhookUrl } from '../../integrationValidation';
import { SettingsGroup, SettingsRow } from '../../settings/SettingsRow';

const WEBHOOKS_KEY = ['settings', 'integration', 'webhooks'] as const;
const FORM_ID = 'webhook-form';

const DELIVERY_TONE: Record<WebhookDeliveryDto['status'], BadgeVariant> = {
  delivered: 'success',
  pending: 'info',
  failed: 'warning',
  dead: 'danger',
};

const DELIVERY_LABEL: Record<WebhookDeliveryDto['status'], string> = {
  delivered: 'Entregado',
  pending: 'Pendiente',
  failed: 'Falló',
  dead: 'Descartado',
};

interface Reveal {
  secret: string;
  title: string;
}

/** "Webhooks": the URLs each fiscal event is sent to, signed with HMAC (Nb-Signature). */
export const WebhooksGroup = ({ canEdit }: { canEdit: boolean }) => {
  const queryClient = useQueryClient();
  const { ask, ConfirmHost } = useConfirm();
  const [creating, setCreating] = useState(false);
  const [revealed, setRevealed] = useState<Reveal | null>(null);
  const [logFor, setLogFor] = useState<WebhookEndpointDto | null>(null);

  const query = useQuery({
    queryKey: WEBHOOKS_KEY,
    queryFn: () => integrationService.listWebhooks(),
  });
  const refresh = () => queryClient.invalidateQueries({ queryKey: WEBHOOKS_KEY });

  const rotate = useMutation({
    mutationFn: (id: string) => integrationService.rotateWebhookSecret(id),
    onSuccess: (endpoint) => {
      setRevealed({ secret: endpoint.secret, title: 'Nuevo secreto de firma' });
      void refresh();
    },
  });
  const toggle = useMutation({
    mutationFn: (id: string) => integrationService.toggleWebhook(id),
    onSuccess: () => void refresh(),
  });
  const remove = useMutation({
    mutationFn: (id: string) => integrationService.deleteWebhook(id),
    onSuccess: () => {
      toast('Endpoint eliminado', 'success');
      void refresh();
    },
  });

  if (query.isError) return <Alert tone="warning">No se pudieron cargar los webhooks.</Alert>;
  if (query.isPending) return <Skeleton variant="rect" height={140} />;

  const endpoints = query.data.data;
  const busy = (mutation: { isPending: boolean; variables?: string }, id: string) =>
    mutation.isPending && mutation.variables === id;

  return (
    <>
      <SettingsGroup
        title="Webhooks"
        action={
          canEdit && (
            <Button
              type="button"
              variant="primary"
              size="sm"
              icon="ph ph-plus"
              onClick={() => setCreating(true)}
            >
              Agregar endpoint
            </Button>
          )
        }
      >
        {endpoints.length === 0 && (
          <p className="settings-muted">
            Sin endpoints. Registra la URL de nubit-control para recibir el resultado de SUNAT en
            tiempo real.
          </p>
        )}
        {endpoints.map((endpoint) => (
          <SettingsRow
            key={endpoint.id}
            wrap
            label={endpoint.url}
            description={[
              endpoint.types.join(', '),
              // "disabled from settings" is the server's word for a switch-off by hand.
              endpoint.status === 'disabled' &&
              endpoint.disabled_reason &&
              endpoint.disabled_reason !== 'disabled from settings'
                ? endpoint.disabled_reason
                : null,
              endpoint.consecutive_failures > 0
                ? `${endpoint.consecutive_failures} fallos seguidos`
                : null,
            ]
              .filter(Boolean)
              .join(' · ')}
          >
            {endpoint.status === 'enabled' ? (
              <Badge variant="success">Activo</Badge>
            ) : (
              <Badge variant="danger">Desactivado</Badge>
            )}
            <Button type="button" variant="ghost" size="sm" onClick={() => setLogFor(endpoint)}>
              Entregas
            </Button>
            {canEdit && (
              <>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  loading={busy(rotate, endpoint.id)}
                  onClick={() => rotate.mutate(endpoint.id)}
                >
                  Rotar secreto
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  loading={busy(toggle, endpoint.id)}
                  onClick={() => toggle.mutate(endpoint.id)}
                >
                  {endpoint.status === 'enabled' ? 'Desactivar' : 'Activar'}
                </Button>
                <Button
                  type="button"
                  variant="danger"
                  size="sm"
                  onClick={() =>
                    ask({
                      title: 'Eliminar endpoint',
                      message: `Dejaremos de enviar eventos a ${endpoint.url}.`,
                      variant: 'danger',
                      onConfirm: () => remove.mutate(endpoint.id),
                    })
                  }
                >
                  Eliminar
                </Button>
              </>
            )}
          </SettingsRow>
        ))}
      </SettingsGroup>

      {creating && (
        <NewWebhookDialog
          eventTypes={query.data.event_types}
          onClose={() => setCreating(false)}
          onCreated={(secret) => {
            setCreating(false);
            setRevealed({ secret, title: 'Endpoint registrado' });
            void refresh();
          }}
        />
      )}
      {logFor && (
        <DeliveriesDialog endpoint={logFor} onClose={() => setLogFor(null)} onRetried={refresh} />
      )}
      <ConfirmHost />
      {revealed && (
        <SecretRevealDialog
          open
          title={revealed.title}
          secret={revealed.secret}
          hint="Configúralo como NUBIT_EFACT_WEBHOOK_SECRET en nubit-control."
          onClose={() => setRevealed(null)}
        />
      )}
    </>
  );
};

const NewWebhookDialog = ({
  eventTypes,
  onClose,
  onCreated,
}: {
  eventTypes: string[];
  onClose: () => void;
  onCreated: (secret: string) => void;
}) => {
  const [url, setUrl] = useState('');
  const [types, setTypes] = useState<string[]>(['document.*']);
  const [submitted, setSubmitted] = useState(false);

  const create = useMutation({
    mutationFn: () => integrationService.createWebhook(url.trim(), types),
    onSuccess: (endpoint) => onCreated(endpoint.secret),
  });

  const urlIssue = validateWebhookUrl(url);
  const typeIssue = types.length === 0 ? 'Elige al menos un evento.' : null;
  const saving = create.isPending;

  const toggleType = (type: string) =>
    setTypes((current) =>
      current.includes(type) ? current.filter((item) => item !== type) : [...current, type],
    );

  return (
    <AppDialog
      open
      title="Nuevo endpoint de webhook"
      width={520}
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
            Registrar endpoint
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
          if (urlIssue || typeIssue) return;
          create.mutate();
        }}
      >
        <FormField
          label="URL"
          htmlFor="webhook-url"
          required
          helpText="Debe ser https."
          error={submitted ? (urlIssue ?? undefined) : undefined}
        >
          <TextField
            id="webhook-url"
            type="url"
            autoFocus
            required
            placeholder="https://panel.nubit.pe/api/fiscal/efact/webhook"
            value={url}
            invalid={submitted && urlIssue !== null}
            onChange={(event) => setUrl(event.target.value)}
          />
        </FormField>
        <FormField label="Eventos" error={submitted ? (typeIssue ?? undefined) : undefined}>
          <div className="settings-checks">
            <Checkbox
              label="document.* (todos los de comprobante)"
              checked={types.includes('document.*')}
              onChange={() => toggleType('document.*')}
            />
            {eventTypes.map((type) => (
              <Checkbox
                key={type}
                label={type}
                checked={types.includes(type)}
                onChange={() => toggleType(type)}
              />
            ))}
          </div>
        </FormField>
      </FormLayout>
    </AppDialog>
  );
};

const DeliveriesDialog = ({
  endpoint,
  onClose,
  onRetried,
}: {
  endpoint: WebhookEndpointDto;
  onClose: () => void;
  onRetried: () => void;
}) => {
  const query = useQuery({
    queryKey: ['settings', 'integration', 'deliveries', endpoint.id],
    queryFn: () => integrationService.listDeliveries(endpoint.id),
  });
  const retry = useMutation({
    mutationFn: (deliveryId: string) => integrationService.retryDelivery(deliveryId),
    onSuccess: () => {
      toast('Reencolado', 'success');
      void query.refetch();
      onRetried();
    },
  });

  return (
    <AppDialog
      open
      title="Entregas recientes"
      width={640}
      onClose={onClose}
      footer={
        <Button type="button" variant="secondary" onClick={onClose}>
          Cerrar
        </Button>
      }
    >
      <p className="settings-muted">{endpoint.url}</p>
      {query.isError && <Alert tone="warning">No se pudieron cargar las entregas.</Alert>}
      {query.isPending && <Skeleton variant="rect" height={120} />}
      {query.data && query.data.length === 0 && (
        <p className="settings-muted">Aún no se ha enviado ningún evento a este endpoint.</p>
      )}
      {query.data?.map((delivery) => (
        <SettingsRow
          key={delivery.id}
          wrap
          label={delivery.event_type}
          description={`Intento ${delivery.attempt} · ${new Date(delivery.created_at).toLocaleString('es-PE')}`}
        >
          <Badge variant={DELIVERY_TONE[delivery.status]}>
            {DELIVERY_LABEL[delivery.status]}
            {delivery.response_status ? ` · ${delivery.response_status}` : ''}
          </Badge>
          {delivery.status !== 'delivered' && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              loading={retry.isPending && retry.variables === delivery.id}
              onClick={() => retry.mutate(delivery.id)}
            >
              Reintentar
            </Button>
          )}
        </SettingsRow>
      ))}
    </AppDialog>
  );
};

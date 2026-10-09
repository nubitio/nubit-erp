import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AppDialog,
  Badge,
  Button,
  Card,
  DataTable,
  Drawer,
  EmptyState,
  Text,
  TextField,
  useConfirm,
  type BadgeVariant,
} from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import {
  integrationService,
  type WebhookDeliveryDto,
  type WebhookEndpointDto,
} from '../services/integrationService';
import { SecretRevealDialog } from './SecretRevealDialog';

const QUERY_KEY = ['settings', 'integration', 'webhooks'];

const DELIVERY_TONE: Record<WebhookDeliveryDto['status'], BadgeVariant> = {
  delivered: 'success',
  pending: 'info',
  failed: 'warning',
  dead: 'danger',
};

export const IntegrationWebhooksCard = () => {
  const queryClient = useQueryClient();
  const { ask, ConfirmHost } = useConfirm();

  const [formOpen, setFormOpen] = useState(false);
  const [url, setUrl] = useState('');
  const [types, setTypes] = useState<string[]>(['document.*']);
  const [revealed, setRevealed] = useState<{ secret: string; title: string } | null>(null);
  const [logFor, setLogFor] = useState<WebhookEndpointDto | null>(null);

  const query = useQuery({ queryKey: QUERY_KEY, queryFn: () => integrationService.listWebhooks() });
  const invalidate = () => queryClient.invalidateQueries({ queryKey: QUERY_KEY });

  const createMutation = useMutation({
    mutationFn: () => integrationService.createWebhook(url.trim(), types),
    onSuccess: (endpoint) => {
      setRevealed({ secret: endpoint.secret, title: 'Endpoint registrado' });
      setFormOpen(false);
      setUrl('');
      invalidate();
    },
  });

  const rotateMutation = useMutation({
    mutationFn: (id: string) => integrationService.rotateWebhookSecret(id),
    onSuccess: (endpoint) => {
      setRevealed({ secret: endpoint.secret, title: 'Nuevo secreto de firma' });
      invalidate();
    },
  });

  const toggleMutation = useMutation({
    mutationFn: (id: string) => integrationService.toggleWebhook(id),
    onSuccess: () => invalidate(),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => integrationService.deleteWebhook(id),
    onSuccess: () => {
      toast('Endpoint eliminado', 'success');
      invalidate();
    },
  });

  const eventTypes = query.data?.event_types ?? [];
  const endpoints = query.data?.data ?? [];

  const toggleType = (type: string) =>
    setTypes((current) =>
      current.includes(type) ? current.filter((t) => t !== type) : [...current, type],
    );

  return (
    <Card
      title="Webhooks"
      description="URLs a las que enviamos cada evento fiscal, firmadas con HMAC (Nb-Signature)."
    >
      <div className="integration-card__head">
        <Button variant="primary" size="sm" onClick={() => setFormOpen(true)}>
          Agregar endpoint
        </Button>
      </div>

      {endpoints.length === 0 ? (
        <EmptyState
          icon="webhooks-logo"
          title="Sin endpoints"
          description="Registra la URL de nubit-control para recibir el resultado de SUNAT en tiempo real."
        />
      ) : (
        <DataTable<WebhookEndpointDto>
          variant="flush"
          rows={endpoints}
          getRowKey={(row) => row.id}
          columns={[
            {
              id: 'url',
              header: 'URL',
              render: (row) => (
                <div>
                  <code className="integration-prefix">{row.url}</code>
                  <div className="integration-chips">
                    {row.types.map((type) => (
                      <Badge key={type} variant="light" size="sm">
                        {type}
                      </Badge>
                    ))}
                  </div>
                </div>
              ),
            },
            {
              id: 'status',
              header: 'Estado',
              render: (row) =>
                row.status === 'enabled' ? (
                  <Badge variant="success">Activo</Badge>
                ) : (
                  <Badge variant="danger">{row.disabled_reason ?? 'Desactivado'}</Badge>
                ),
            },
            {
              id: 'failures',
              header: 'Fallos seguidos',
              align: 'right',
              render: (row) =>
                row.consecutive_failures > 0 ? (
                  <Text tone="muted">{row.consecutive_failures}</Text>
                ) : (
                  <Text tone="muted">0</Text>
                ),
            },
          ]}
          renderActions={(row) => (
            <div className="integration-row-actions">
              <Button size="sm" variant="ghost" onClick={() => setLogFor(row)}>
                Entregas
              </Button>
              <Button
                size="sm"
                variant="ghost"
                loading={rotateMutation.isPending}
                onClick={() => rotateMutation.mutate(row.id)}
              >
                Rotar secreto
              </Button>
              <Button
                size="sm"
                variant="ghost"
                loading={toggleMutation.isPending}
                onClick={() => toggleMutation.mutate(row.id)}
              >
                {row.status === 'enabled' ? 'Desactivar' : 'Activar'}
              </Button>
              <Button
                size="sm"
                variant="danger"
                onClick={() =>
                  ask({
                    title: 'Eliminar endpoint',
                    message: `Dejaremos de enviar eventos a ${row.url}.`,
                    variant: 'danger',
                    onConfirm: () => deleteMutation.mutate(row.id),
                  })
                }
              >
                Eliminar
              </Button>
            </div>
          )}
        />
      )}

      <AppDialog
        open={formOpen}
        title="Nuevo endpoint de webhook"
        onClose={() => setFormOpen(false)}
        maxWidth="32rem"
        footer={
          <div className="integration-dialog__footer">
            <Button variant="ghost" onClick={() => setFormOpen(false)}>
              Cancelar
            </Button>
            <Button
              variant="primary"
              loading={createMutation.isPending}
              disabled={!url.trim().startsWith('https://') || types.length === 0}
              onClick={() => createMutation.mutate()}
            >
              Registrar
            </Button>
          </div>
        }
      >
        <label className="integration-field">
          <span>URL (https)</span>
          <TextField
            value={url}
            placeholder="https://panel.nubit.pe/api/fiscal/efact/webhook"
            onChange={(e) => setUrl(e.target.value)}
          />
        </label>
        <fieldset className="integration-field">
          <span>Eventos</span>
          <label className="integration-check">
            <input
              type="checkbox"
              checked={types.includes('document.*')}
              onChange={() => toggleType('document.*')}
            />
            document.* (todos los de comprobante)
          </label>
          {eventTypes.map((type) => (
            <label key={type} className="integration-check">
              <input
                type="checkbox"
                checked={types.includes(type)}
                onChange={() => toggleType(type)}
              />
              {type}
            </label>
          ))}
        </fieldset>
      </AppDialog>

      <DeliveryLogDrawer endpoint={logFor} onClose={() => setLogFor(null)} onRetried={invalidate} />

      <ConfirmHost />

      {revealed ? (
        <SecretRevealDialog
          open
          title={revealed.title}
          secret={revealed.secret}
          hint="Configúralo como NUBIT_EFACT_WEBHOOK_SECRET en nubit-control."
          onClose={() => setRevealed(null)}
        />
      ) : null}
    </Card>
  );
};

interface DeliveryLogDrawerProps {
  endpoint: WebhookEndpointDto | null;
  onClose: () => void;
  onRetried: () => void;
}

const DeliveryLogDrawer = ({ endpoint, onClose, onRetried }: DeliveryLogDrawerProps) => {
  const query = useQuery({
    queryKey: ['settings', 'integration', 'deliveries', endpoint?.id],
    queryFn: () => integrationService.listDeliveries(endpoint!.id),
    enabled: endpoint !== null,
  });

  const retry = useMutation({
    mutationFn: (deliveryId: string) => integrationService.retryDelivery(deliveryId),
    onSuccess: () => {
      toast('Reencolado', 'success');
      query.refetch();
      onRetried();
    },
  });

  const rows = query.data ?? [];

  return (
    <Drawer isOpen={endpoint !== null} onClose={onClose} title="Entregas recientes" width="40rem">
      {rows.length === 0 ? (
        <EmptyState
          icon="tray"
          title="Sin entregas"
          description="Aún no se ha enviado ningún evento a este endpoint."
        />
      ) : (
        <DataTable<WebhookDeliveryDto>
          variant="flush"
          rows={rows}
          getRowKey={(row) => row.id}
          columns={[
            {
              id: 'event',
              header: 'Evento',
              render: (row) => (
                <div>
                  <strong>{row.event_type}</strong>
                  <Text tone="muted"> · intento {row.attempt}</Text>
                </div>
              ),
            },
            {
              id: 'status',
              header: 'Estado',
              render: (row) => (
                <Badge variant={DELIVERY_TONE[row.status]}>
                  {row.status}
                  {row.response_status ? ` · ${row.response_status}` : ''}
                </Badge>
              ),
            },
            {
              id: 'when',
              header: 'Cuándo',
              render: (row) => (
                <Text tone="muted">{new Date(row.created_at).toLocaleString('es-PE')}</Text>
              ),
            },
          ]}
          renderActions={(row) =>
            row.status !== 'delivered' ? (
              <Button
                size="sm"
                variant="ghost"
                loading={retry.isPending}
                onClick={() => retry.mutate(row.id)}
              >
                Reintentar
              </Button>
            ) : null
          }
        />
      )}
    </Drawer>
  );
};

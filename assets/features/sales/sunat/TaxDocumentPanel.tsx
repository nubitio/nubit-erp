import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Badge,
  Button,
  ButtonLink,
  ConfirmDialog,
  DescriptionList,
  Section,
  Timeline,
  TimelineItem,
} from '@nubitio/ui';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { hasPermission } from '@shared/permissions';
import { useSessionInfo } from '@shared/sessionInfo';
import { taxDocumentService } from '../services/taxDocumentService';
import { saleService } from '../services/saleService';
import { receiptLinks, saleDate, type SaleDetail } from '../detail/saleDetailModel';
import { sunatStatus } from './sunatStatus';
import { downloadReceiptFile } from './downloadReceiptFile';

interface TaxDocumentDetail {
  id: string;
  sunatStatus?: string | null;
  sunatMessage?: string | null;
  digest?: string | null;
  cdrPath?: string | null;
  voided?: boolean;
  series?: string;
  number?: string;
}
interface DocumentEvent {
  id: number;
  eventType: string;
  code?: string | null;
  message?: string | null;
  createdAt: string;
}

export function TaxDocumentPanel({ sale }: { sale: SaleDetail }) {
  const queryClient = useQueryClient();
  const { currentBranchId } = useSessionInfo();
  const [confirmation, setConfirmation] = useState<'send' | 'replace' | null>(null);
  const id = sale.taxDocumentId;
  const api = `${getCoreApiBaseUrl()}tax-documents/${id}`;
  const document = useQuery({
    queryKey: ['sales', 'tax-document', currentBranchId, id],
    enabled: Boolean(id),
    retry: false,
    queryFn: async () => (await httpClient.get<TaxDocumentDetail>(api)).data,
  });
  const events = useQuery({
    queryKey: ['sales', 'tax-events', currentBranchId, id],
    enabled: Boolean(id) && document.isSuccess,
    retry: false,
    queryFn: async () => (await httpClient.get<DocumentEvent[]>(`${api}/events`)).data,
  });
  const status = sunatStatus(document.data?.sunatStatus ?? sale.sunatStatus);
  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['sales'] });
  };
  const operation = useMutation({
    mutationFn: async (action: 'send' | 'replace' | 'consult') => {
      const response =
        action === 'replace'
          ? await saleService.replaceRejected(sale.id)
          : action === 'send'
            ? await taxDocumentService.sendDocument(id!)
            : await taxDocumentService.consultDocument(id!);
      if (!response.data.success)
        throw new Error(response.data.message || 'La operación no se completó.');
      return response.data.message;
    },
    onSettled: refresh,
  });
  const download = useMutation({
    mutationFn: ({ url, kind }: { url: string; kind: 'xml' | 'cdr' }) =>
      downloadReceiptFile(
        url,
        `${kind === 'cdr' ? 'CDR-' : ''}${sale.invoiceFullNumber || id}.${kind === 'cdr' ? 'zip' : 'xml'}`,
        kind,
      ),
  });
  const links = receiptLinks(sale.publicReceiptUrl);
  const active = sale.status !== 'VOIDED' && !document.data?.voided;
  const canCreate = hasPermission('sales.create') && active && document.isSuccess;
  const busy = operation.isPending || document.isFetching;
  const eventLabels: Record<string, string> = {
    sunat_send: 'Envío a SUNAT',
    sunat_consult: 'Consulta de CDR',
    sunat_void: 'Anulación',
    document_replaced: 'Comprobante reemplazado',
    sunat_replacement_created: 'Reemplazo generado',
    issued: 'Comprobante emitido',
  };

  return (
    <Section variant="panel" title="Comprobante electrónico">
      {!id ? (
        <p>Esta venta no tiene un comprobante electrónico asociado.</p>
      ) : document.isPending ? (
        <p role="status">Cargando comprobante…</p>
      ) : document.isError && !document.data ? (
        <>
          <Alert tone="warning">No se pudo consultar el comprobante.</Alert>
          <Button onClick={() => void document.refetch()}>Reintentar comprobante</Button>
        </>
      ) : (
        <>
          <Badge
            pill
            variant={
              status.stage === 'accepted'
                ? 'success'
                : status.stage === 'rejected'
                  ? 'danger'
                  : 'warning'
            }
          >
            {status.label}
          </Badge>
          <DescriptionList
            items={[
              { term: 'Número', value: sale.invoiceFullNumber || 'No disponible' },
              { term: 'Código SUNAT', value: status.code || 'Sin respuesta registrada' },
              ...(document.data?.digest ? [{ term: 'Hash', value: document.data.digest }] : []),
            ]}
          />
          {document.data?.sunatMessage && (
            <p className="sale-detail__note">{document.data.sunatMessage}</p>
          )}
          {status.stage === 'rejected' && (
            <Alert tone="danger">
              Este comprobante fue rechazado. Revisa el motivo antes de generar un reemplazo.
            </Alert>
          )}
          {status.stage === 'accepted' && (
            <p>Comprobante aceptado. Las correcciones se gestionan mediante notas de crédito.</p>
          )}
          {status.stage === 'sent' && (
            <p>Consulta el CDR para comprobar la respuesta antes de volver a enviar.</p>
          )}
          {operation.isError && <Alert tone="danger">{operation.error.message}</Alert>}
          {operation.isSuccess && operation.data && <p role="status">{operation.data}</p>}
          <div className="sale-detail__actions">
            <Button disabled={busy} onClick={() => operation.mutate('consult')}>
              Consultar CDR
            </Button>
            {canCreate && status.canSend && (
              <Button disabled={busy} onClick={() => setConfirmation('send')}>
                Enviar a SUNAT
              </Button>
            )}
            {canCreate && status.canReplace && (
              <Button disabled={busy} variant="primary" onClick={() => setConfirmation('replace')}>
                Reemplazar rechazado
              </Button>
            )}
            {status.stage === 'accepted' && active && hasPermission('sales.void') && (
              <>
                <ButtonLink as={Link} to={`/billing/credit-notes/new/${id}`} variant="primary">
                  Emitir nota de crédito
                </ButtonLink>
                <ButtonLink as={Link} to="/billing/credit-notes">
                  Gestionar notas de crédito
                </ButtonLink>
              </>
            )}
          </div>
          {links && (
            <div className="sale-detail__document-links">
              <Button
                size="sm"
                variant="ghost"
                disabled={download.isPending}
                onClick={() => download.mutate({ url: links.xml, kind: 'xml' })}
              >
                Descargar XML
              </Button>
              {document.data?.cdrPath && (
                <Button
                  size="sm"
                  variant="ghost"
                  disabled={download.isPending}
                  onClick={() => download.mutate({ url: links.cdr, kind: 'cdr' })}
                >
                  Descargar CDR
                </Button>
              )}
            </div>
          )}
          {download.isError && <Alert tone="warning">{download.error.message}</Alert>}
          <h3 className="sale-detail__event-title">Actividad del comprobante</h3>
          {events.isPending ? (
            <p role="status">Cargando actividad…</p>
          ) : events.isError ? (
            <Button onClick={() => void events.refetch()}>Reintentar actividad</Button>
          ) : events.data?.length ? (
            <Timeline variant="log" aria-label="Actividad del comprobante">
              {events.data.map((event) => (
                <TimelineItem
                  key={event.id}
                  status="complete"
                  title={eventLabels[event.eventType] || 'Evento del comprobante'}
                  dateTime={event.createdAt}
                  timestamp={saleDate(event.createdAt, true)}
                  tone={sunatStatus(event.code).stage === 'rejected' ? 'danger' : 'info'}
                >
                  {event.message}
                </TimelineItem>
              ))}
            </Timeline>
          ) : (
            <p>No hay eventos registrados para este comprobante.</p>
          )}
        </>
      )}
      <ConfirmDialog
        open={Boolean(confirmation)}
        title={
          confirmation === 'replace'
            ? 'Reemplazar comprobante rechazado'
            : 'Enviar comprobante a SUNAT'
        }
        message={
          confirmation === 'replace'
            ? 'Se generará un nuevo comprobante vinculado a esta venta. El documento rechazado se conservará en el historial.'
            : 'Se enviará este comprobante al servicio SUNAT configurado para la empresa.'
        }
        confirmLabel={confirmation === 'replace' ? 'Generar reemplazo' : 'Confirmar envío'}
        onCancel={() => setConfirmation(null)}
        onConfirm={() => {
          const action = confirmation;
          setConfirmation(null);
          if (action) operation.mutate(action);
        }}
      />
    </Section>
  );
}

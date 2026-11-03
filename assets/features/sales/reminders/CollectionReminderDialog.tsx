import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, AppDialog, Badge, Button, SegmentedControl, Text } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { formatInvoiceNumber, formatMoney } from '@shared/lib/displayFormat';
import {
  collectionReminderService,
  type ReminderChannel,
  type ReminderTone,
} from './collectionReminderService';
import {
  CHANNEL_OPTIONS,
  TONE_OPTIONS,
  formatWhatsappNumber,
  outcomeMessage,
  overdueLabel,
  reminderErrorInfo,
} from './collectionReminderModel';
import './CollectionReminderDialog.scss';

interface Props {
  saleId: string;
  documentNumber: string | null;
  balanceDue: string;
  currency: string;
  customerName: string;
  onClose: () => void;
}

/**
 * Manual payment reminder for one overdue document (Figma 80:2634). WhatsApp opens with
 * the message ready and is recorded as "prepared"; e-mail is sent by the server.
 */
export function CollectionReminderDialog({
  saleId,
  documentNumber,
  balanceDue,
  currency,
  customerName,
  onClose,
}: Props) {
  const queryClient = useQueryClient();
  const [channel, setChannel] = useState<ReminderChannel>('whatsapp');
  const [tone, setTone] = useState<ReminderTone>('direct');
  const [deliveryFailure, setDeliveryFailure] = useState<string | null>(null);

  const preview = useQuery({
    queryKey: ['receivable-reminder-preview', saleId, channel, tone],
    queryFn: ({ signal }) => collectionReminderService.preview(saleId, channel, tone, signal),
    retry: false,
    staleTime: 0,
  });

  const send = useMutation({
    mutationFn: (force: boolean) =>
      collectionReminderService.create({ saleId, channel, tone, force }),
    onMutate: () => setDeliveryFailure(null),
    onSuccess: (record) => {
      void queryClient.invalidateQueries({ queryKey: ['receivable-reminder-preview'] });
      const outcome = outcomeMessage(record.channel, record.status, record.failureReason);
      if (record.whatsappUrl) window.open(record.whatsappUrl, '_blank', 'noopener,noreferrer');
      if (outcome.tone === 'error') {
        // The attempt was recorded as failed; keep the dialog open so it can be retried.
        setDeliveryFailure(outcome.text);

        return;
      }
      toast(outcome.text, 'success');
      onClose();
    },
  });

  const previewError = preview.isError ? reminderErrorInfo(preview.error) : null;
  const sendError = send.isError ? reminderErrorInfo(send.error) : null;
  const data = preview.data;
  const overdue = data ? overdueLabel(data.daysOverdue) : null;
  const recipientLine = data
    ? channel === 'whatsapp'
      ? formatWhatsappNumber(data.recipient.phone)
      : (data.recipient.email ?? '')
    : '';
  const number = formatInvoiceNumber(documentNumber) || 'Saldo pendiente';

  return (
    <AppDialog
      open
      title={`Recordar pago a ${customerName}`}
      onClose={onClose}
      width={560}
      footer={
        <div className="reminder-dialog__footer">
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button
            variant="primary"
            data-testid="reminder-submit"
            disabled={!data || send.isPending}
            loading={send.isPending}
            onClick={() => send.mutate(false)}
          >
            {channel === 'whatsapp' ? 'Abrir WhatsApp' : 'Enviar correo'}
          </Button>
        </div>
      }
    >
      <div className="reminder-dialog" data-testid="reminder-dialog">
        <p className="reminder-dialog__document">
          <span>
            {number} · {formatMoney(balanceDue, currency)}
          </span>
          {overdue && (
            <Badge variant="danger" outlined>
              {overdue}
            </Badge>
          )}
        </p>

        <div className="reminder-dialog__choices">
          <div>
            <span className="reminder-dialog__label">Canal</span>
            <SegmentedControl
              ariaLabel="Canal del recordatorio"
              options={CHANNEL_OPTIONS}
              value={channel}
              onChange={setChannel}
            />
          </div>
          <div>
            <span className="reminder-dialog__label">Tono</span>
            <SegmentedControl
              ariaLabel="Tono del recordatorio"
              options={TONE_OPTIONS}
              value={tone}
              onChange={setTone}
            />
          </div>
        </div>

        {previewError && <Alert tone="danger">{previewError.message}</Alert>}

        {preview.isPending && <p role="status">Preparando el mensaje…</p>}

        {data && (
          <>
            <div className="reminder-dialog__recipient" data-testid="reminder-recipient">
              <strong>{data.recipient.name}</strong>
              <span>{recipientLine}</span>
            </div>

            <div>
              <span className="reminder-dialog__label">Mensaje</span>
              <pre className="reminder-dialog__message" data-testid="reminder-message">
                {data.body}
              </pre>
            </div>

            {data.lastReminder && (
              <Text tone="muted" as="p">
                Último recordatorio:{' '}
                {new Date(data.lastReminder.createdAt).toLocaleDateString('es-PE')} por{' '}
                {data.lastReminder.channel === 'whatsapp' ? 'WhatsApp' : 'correo'}.
              </Text>
            )}

            {channel === 'whatsapp' && (
              <Text tone="muted" as="p">
                Se abre WhatsApp con el mensaje listo. Queda registrado como «preparado»: no podemos
                confirmar que lo envíes.
              </Text>
            )}
          </>
        )}

        {sendError && (
          <Alert tone={sendError.tooFrequent ? 'warning' : 'danger'}>
            {sendError.message}{' '}
            {sendError.tooFrequent && (
              <Button size="sm" variant="secondary" onClick={() => send.mutate(true)}>
                Enviar de todos modos
              </Button>
            )}
          </Alert>
        )}
        {deliveryFailure && <Alert tone="danger">{deliveryFailure}</Alert>}
      </div>
    </AppDialog>
  );
}

import { useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Badge,
  Button,
  Card,
  Checkbox,
  Chip,
  ConfirmDialog,
  DetailLayout,
  EmptyState,
  FormField,
  Page,
  PageHeader,
  QuantityStepper,
  Section,
  SelectField,
  TextField,
} from '@nubitio/ui';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { DateTime } from 'luxon';
import { httpClient } from '@shared/api/HttpClient';
import { formatMoney } from '@shared/lib/displayFormat';
import { hydraMembers, type HydraCollection } from '@shared/api/hydraCollection';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';
import { sunatStatus } from '../sunat/sunatStatus';
import {
  CREDIT_NOTE_REASONS,
  FREQUENT_REASON_CODES,
  annulsDocument,
  buildCreditNotePayload,
  creditNoteBlockedReason,
  creditNoteIssueErrors,
  defaultReturnQuantity,
  lineReturnCents,
  returnAllLines,
  returnSummary,
  returnsEverything,
  type OriginLine,
  type Returns,
} from './creditNoteIssueModel';
import './CreditNoteIssuePage.scss';

const API = getCoreApiBaseUrl();

interface OriginDocument {
  id: string;
  series?: string;
  number?: string;
  currency?: string | null;
  total?: string;
  sunatStatus?: string | null;
  voided?: boolean;
  customer?: { businessName?: string | null } | null;
}

const reasonLabel = (code: string) => CREDIT_NOTE_REASONS.find((r) => r.value === code)?.text ?? '';

const centsToMoney = (cents: bigint | null, currency: string) =>
  cents === null
    ? '—'
    : formatMoney(`${cents / 100n}.${(cents % 100n).toString().padStart(2, '0')}`, currency);

export function CreditNoteIssuePage() {
  const { documentId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const canIssue = hasPermission('sales.void');
  const [reasonCode, setReasonCode] = useState('');
  const [reasonDescription, setReasonDescription] = useState('');
  const [returns, setReturns] = useState<Returns>({});
  const [submitted, setSubmitted] = useState(false);

  const documentQuery = useQuery({
    queryKey: ['credit-note-issue', 'document', documentId],
    enabled: canIssue && Boolean(documentId),
    retry: false,
    queryFn: async () =>
      (await httpClient.get<OriginDocument>(`${API}tax-documents/${documentId}`)).data,
  });
  const linesQuery = useQuery({
    queryKey: ['credit-note-issue', 'lines', documentId],
    enabled: documentQuery.isSuccess,
    retry: false,
    queryFn: async () =>
      hydraMembers(
        (
          await httpClient.get<HydraCollection<OriginLine>>(
            `${API}tax-documents/${documentId}/lines`,
          )
        ).data,
      ).sort((a, b) => a.lineNumber - b.lineNumber),
  });

  const lines = useMemo(() => linesQuery.data ?? [], [linesQuery.data]);
  const origin = documentQuery.data;
  const currency = origin?.currency || 'PEN';
  const originNumber = [origin?.series, origin?.number].filter(Boolean).join('-');
  const locked = returnsEverything(reasonCode);
  const errors = creditNoteIssueErrors({ reasonCode, reasonDescription, lines, returns });
  const summary = returnSummary(lines, returns);

  const chooseReason = (code: string) => {
    setReasonCode(code);
    if (returnsEverything(code)) setReturns(returnAllLines(lines));
    else if (returnsEverything(reasonCode)) setReturns({});
  };
  const toggleLine = (line: OriginLine, checked: boolean) =>
    setReturns((current) => {
      const next = { ...current };
      if (checked) next[line.lineNumber] = defaultReturnQuantity(line);
      else delete next[line.lineNumber];
      return next;
    });
  const setLineQuantity = (line: OriginLine, quantity: number) =>
    setReturns((current) => {
      const next = { ...current };
      if (quantity <= 0) delete next[line.lineNumber];
      else next[line.lineNumber] = String(quantity);
      return next;
    });

  const issue = useMutation({
    mutationFn: async () =>
      (
        await httpClient.post<{ id: string }>(
          `${API}tax-documents`,
          buildCreditNotePayload({
            relatedTaxDocumentIri: `${API}tax-documents/${documentId}`,
            issueDate: DateTime.now().setZone('America/Lima').toISODate()!,
            reasonCode,
            reasonDescription,
            lines,
            returns,
          }),
        )
      ).data,
    onSuccess: (created) => {
      void queryClient.invalidateQueries({ queryKey: ['sales'] });
      toast('Nota de crédito registrada. Envíala a SUNAT desde Notas de crédito.', 'success');
      navigate(`/billing/credit-notes/${created.id}`);
    },
  });

  const [confirmingAnnul, setConfirmingAnnul] = useState(false);
  const submit = () => {
    setSubmitted(true);
    if (Object.keys(errors).length > 0 || issue.isPending) return;
    // An annulling reason cancels the sale and restocks it: ask once, plainly.
    if (annulsDocument(reasonCode)) setConfirmingAnnul(true);
    else issue.mutate();
  };

  const back = <Link to="/billing/sales">Volver a ventas</Link>;
  if (!canIssue)
    return (
      <Page>
        <EmptyState
          title="Acceso restringido"
          description="No tienes permiso para emitir notas de crédito."
          action={back}
        />
      </Page>
    );
  if (documentQuery.isError)
    return (
      <Page>
        <EmptyState
          variant="danger"
          title="No se pudo cargar el comprobante"
          description="Revisa que exista y pertenezca a esta sucursal."
          action={
            <>
              {back}
              <Button onClick={() => void documentQuery.refetch()}>Reintentar</Button>
            </>
          }
        />
      </Page>
    );
  if (!origin)
    return (
      <Page>
        <p role="status">Cargando comprobante…</p>
      </Page>
    );

  const status = sunatStatus(origin.sunatStatus);
  const blocked = creditNoteBlockedReason({ voided: origin.voided, stage: status.stage });

  return (
    <Page className="credit-note-issue" aria-busy={issue.isPending}>
      <PageHeader
        title="Emitir nota de crédito"
        breadcrumbSlot={
          <nav aria-label="Ruta" className="credit-note-issue__crumbs">
            <Link to="/billing/sales">Ventas</Link>
            <span aria-hidden="true"> / </span>
            <span>{originNumber}</span>
          </nav>
        }
      />
      <DetailLayout
        asideLabel="Resumen de la nota"
        aside={
          <Card variant="panel">
            <Section variant="panel" title="Se emitirá">
              <p className="credit-note-issue__kind">
                Nota de crédito sobre {originNumber || 'el comprobante'}
              </p>
              <dl className="credit-note-issue__totals">
                <div>
                  <dt>Valor de venta</dt>
                  <dd>{formatMoney(summary.valueSold, currency)}</dd>
                </div>
                <div>
                  <dt>IGV</dt>
                  <dd>{formatMoney(summary.tax, currency)}</dd>
                </div>
                <div className="credit-note-issue__total">
                  <dt>Total a devolver</dt>
                  <dd data-testid="credit-note-total">{formatMoney(summary.total, currency)}</dd>
                </div>
              </dl>
              <p className="credit-note-issue__hint">
                Estimado con los precios del comprobante; el sistema confirma los importes al
                emitir.
              </p>
              {annulsDocument(reasonCode) && (
                <Alert tone="warning">
                  Este motivo anula la venta y repone el stock. No se puede deshacer.
                </Alert>
              )}
              {issue.isError && <Alert tone="danger">{issue.error.message}</Alert>}
              {blocked && <Alert tone="danger">{blocked}</Alert>}
              <div className="credit-note-issue__actions">
                <Button
                  variant="primary"
                  disabled={issue.isPending || Boolean(blocked)}
                  onClick={submit}
                >
                  {issue.isPending ? 'Emitiendo…' : 'Emitir nota de crédito'}
                </Button>
                <Link to="/billing/sales" className="credit-note-issue__cancel">
                  Cancelar
                </Link>
              </div>
            </Section>
          </Card>
        }
      >
        <Card variant="panel">
          <div className="credit-note-issue__origin">
            <div>
              <span className="credit-note-issue__eyebrow">Afecta a</span>
              <strong>
                Comprobante {originNumber}{' '}
                <Badge pill variant={status.stage === 'accepted' ? 'success' : 'warning'}>
                  {status.label}
                </Badge>
              </strong>
            </div>
            <div>
              <span className="credit-note-issue__eyebrow">Cliente</span>
              <strong>{origin.customer?.businessName || 'Cliente no registrado'}</strong>
            </div>
            <div>
              <span className="credit-note-issue__eyebrow">Total</span>
              <strong>{formatMoney(origin.total ?? '0', currency)}</strong>
            </div>
          </div>
        </Card>
        <Card variant="panel">
          <Section variant="panel" title="Motivo">
            <p className="credit-note-issue__hint">
              SUNAT exige un tipo de nota (catálogo 09) y una explicación.
            </p>
            <div className="credit-note-issue__fields">
              <FormField
                label="Tipo de nota de crédito"
                error={submitted ? errors.reason : undefined}
              >
                <SelectField
                  value={reasonCode}
                  invalid={submitted && Boolean(errors.reason)}
                  onChange={(event) => chooseReason(event.target.value)}
                >
                  <option value="">Selecciona un motivo</option>
                  {CREDIT_NOTE_REASONS.map((reason) => (
                    <option key={reason.value} value={reason.value}>
                      {reason.value} – {reason.text}
                    </option>
                  ))}
                </SelectField>
              </FormField>
              <FormField
                label="Sustento"
                helpText="Aparece impreso en la nota"
                error={submitted ? errors.description : undefined}
              >
                <TextField
                  value={reasonDescription}
                  maxLength={250}
                  invalid={submitted && Boolean(errors.description)}
                  onChange={(event) => setReasonDescription(event.target.value)}
                />
              </FormField>
            </div>
            <div className="credit-note-issue__chips" role="group" aria-label="Motivos frecuentes">
              <span>Más usados:</span>
              {FREQUENT_REASON_CODES.map((code) => (
                <Chip
                  key={code}
                  size="sm"
                  active={reasonCode === code}
                  label={`${code} ${reasonLabel(code)}`}
                  onClick={() => chooseReason(code)}
                />
              ))}
            </div>
          </Section>
        </Card>
        <Card variant="panel">
          <Section variant="panel" title="Ítems a devolver">
            <p className="credit-note-issue__hint">
              {locked
                ? 'Este motivo devuelve todos los ítems completos.'
                : 'Marca lo que vuelve y la cantidad. El resto del comprobante no cambia.'}
            </p>
            {linesQuery.isPending ? (
              <p role="status">Cargando ítems…</p>
            ) : linesQuery.isError ? (
              <Button onClick={() => void linesQuery.refetch()}>Reintentar ítems</Button>
            ) : (
              <div
                className="credit-note-issue__lines"
                role="table"
                aria-label="Ítems del comprobante"
              >
                <div className="credit-note-issue__row is-head" role="row">
                  <span role="columnheader">Producto</span>
                  <span role="columnheader">Vendido</span>
                  <span role="columnheader">Devolver</span>
                  <span role="columnheader">Importe</span>
                </div>
                {lines.map((line) => {
                  const quantity = returns[line.lineNumber];
                  const checked = quantity !== undefined;
                  const cents = checked ? lineReturnCents(line, quantity) : null;
                  return (
                    <div
                      key={line.lineNumber}
                      className={`credit-note-issue__row${checked ? ' is-selected' : ''}`}
                      role="row"
                    >
                      <span role="cell">
                        <Checkbox
                          checked={checked}
                          disabled={locked}
                          aria-label={`Devolver ${line.productName}`}
                          onChange={(event) => toggleLine(line, event.target.checked)}
                          label={
                            <span>
                              <strong>{line.productName}</strong>
                              <small>
                                {[line.productCode, `${formatMoney(line.unitPrice, currency)} c/u`]
                                  .filter(Boolean)
                                  .join(' · ')}
                              </small>
                            </span>
                          }
                        />
                      </span>
                      <span role="cell">{Number(line.quantity)}</span>
                      <span role="cell" className="credit-note-issue__stepper">
                        <QuantityStepper
                          label={`Cantidad a devolver de ${line.productName}`}
                          value={checked ? Number(quantity) : 0}
                          max={Number(line.quantity)}
                          disabled={locked || !checked}
                          onChange={(next) => setLineQuantity(line, next)}
                        />
                      </span>
                      <span role="cell">{checked ? centsToMoney(cents, currency) : '—'}</span>
                    </div>
                  );
                })}
              </div>
            )}
            {submitted && errors.lines && <Alert tone="danger">{errors.lines}</Alert>}
          </Section>
        </Card>
      </DetailLayout>
      <ConfirmDialog
        open={confirmingAnnul}
        title="Anular la venta"
        variant="danger"
        message={`La nota ${reasonCode} anula la venta ${originNumber} y repone el stock. No se puede deshacer. ¿Emitir la nota de crédito?`}
        confirmLabel="Emitir y anular"
        cancelLabel="Cancelar"
        onCancel={() => setConfirmingAnnul(false)}
        onConfirm={() => {
          setConfirmingAnnul(false);
          issue.mutate();
        }}
      />
    </Page>
  );
}

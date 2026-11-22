import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Badge,
  Button,
  ButtonLink,
  Card,
  Checkbox,
  Combobox,
  ConfirmDialog,
  DataTable,
  DetailLayout,
  EmptyState,
  FormField,
  Page,
  PageHeader,
  QuantityStepper,
  Section,
  TextAreaField,
  type ComboboxOption,
  type DataTableColumn,
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
  deleteDraft,
  fetchDraft,
  fetchNextNumber,
  fetchPreviewPdf,
  listDrafts,
  saveDraft,
  searchEligibleDocuments,
  type EligibleDocument,
} from './creditNoteApi';
import {
  CREDIT_NOTE_REASONS,
  annulsDocument,
  buildCreditNotePayload,
  buildReturnChoices,
  choosesRestock,
  creditNoteBlockedReason,
  creditNoteIssueErrors,
  lineReturnCents,
  padNumber,
  returnAllLines,
  returnSummary,
  returnsEverything,
  returnsFromDraft,
  type OriginLine,
  type Returns,
} from './creditNoteIssueModel';
import './CreditNoteIssuePage.scss';

const API = getCoreApiBaseUrl();

interface OriginDocument {
  id: string;
  documentType?: string;
  series?: string;
  number?: string;
  issueDate?: string;
  currency?: string | null;
  total?: string;
  sunatStatus?: string | null;
  voided?: boolean;
  customer?: { businessName?: string | null; ruc?: string | null } | null;
}

const REASON_OPTIONS: ComboboxOption[] = CREDIT_NOTE_REASONS.map((reason) => ({
  value: reason.value,
  label: `${reason.value} — ${reason.text}`,
}));

const DOCUMENT_LABEL: Record<string, string> = { '01': 'Factura', '03': 'Boleta de venta' };

const centsToMoney = (cents: bigint | null, currency: string) =>
  cents === null
    ? '—'
    : formatMoney(`${cents / 100n}.${(cents % 100n).toString().padStart(2, '0')}`, currency);

const documentOption = (doc: EligibleDocument): ComboboxOption => ({
  value: doc.id,
  label: doc.fullNumber,
  content: (
    <span className="credit-note-issue__option">
      <strong>{doc.fullNumber}</strong>
      <small>
        {[DOCUMENT_LABEL[doc.documentType], doc.customerName, formatMoney(doc.total, doc.currency)]
          .filter(Boolean)
          .join(' · ')}
      </small>
    </span>
  ),
});

function reasonHint(code: string): string | null {
  const reason = CREDIT_NOTE_REASONS.find((item) => item.value === code);
  if (!reason) return null;
  const name = `${reason.value} — ${reason.text}`;
  if (annulsDocument(code)) return `${name}: anula el comprobante completo y repone todo su stock.`;
  if (choosesRestock(code))
    return `${name}: permite anular el importe de uno o más productos del comprobante.`;
  return `${name}: acredita el importe de los ítems indicados; no repone stock.`;
}

export function CreditNoteIssuePage() {
  const { documentId: routeDocumentId = '' } = useParams();
  const [search, setSearch] = useSearchParams();
  const draftParam = search.get('draft') ?? '';
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const canIssue = hasPermission('sales.void');

  const [reasonCode, setReasonCode] = useState('');
  const [reasonDescription, setReasonDescription] = useState('');
  const [returns, setReturns] = useState<Returns>({});
  const [submitted, setSubmitted] = useState(false);
  const [draftId, setDraftId] = useState(draftParam);
  const [confirmingAnnul, setConfirmingAnnul] = useState(false);
  const hydratedDraft = useRef('');

  const draftQuery = useQuery({
    queryKey: ['credit-note-issue', 'draft', draftParam],
    enabled: canIssue && Boolean(draftParam),
    retry: false,
    queryFn: () => fetchDraft(draftParam),
  });
  const documentId = routeDocumentId || draftQuery.data?.relatedTaxDocument || '';

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
  const numberQuery = useQuery({
    queryKey: ['credit-note-issue', 'next-number', documentId],
    enabled: canIssue && documentQuery.isSuccess,
    retry: false,
    queryFn: () => fetchNextNumber(documentId),
  });
  const draftsQuery = useQuery({
    queryKey: ['credit-note-issue', 'drafts'],
    enabled: canIssue && !documentId,
    queryFn: listDrafts,
  });

  const lines = useMemo(() => linesQuery.data ?? [], [linesQuery.data]);
  const origin = documentQuery.data;
  const currency = origin?.currency || 'PEN';
  const originNumber = [origin?.series, origin?.number].filter(Boolean).join('-');

  // Restore a saved draft once, after the document's lines are known.
  useEffect(() => {
    const draft = draftQuery.data;
    if (!draft || linesQuery.data === undefined || hydratedDraft.current === draft.id) return;
    hydratedDraft.current = draft.id;
    setReasonCode(draft.reasonCode ?? '');
    setReasonDescription(draft.reasonDescription ?? '');
    setReturns(
      returnsEverything(draft.reasonCode ?? '')
        ? returnAllLines(linesQuery.data)
        : returnsFromDraft(linesQuery.data, draft.lines),
    );
    setDraftId(draft.id);
  }, [draftQuery.data, linesQuery.data]);

  const locked = returnsEverything(reasonCode);
  const showRestock = choosesRestock(reasonCode);
  const errors = creditNoteIssueErrors({ reasonCode, reasonDescription, lines, returns });
  const summary = returnSummary(lines, returns);

  const loadDocuments = async (query: string, { signal }: { signal: AbortSignal }) => ({
    options: (await searchEligibleDocuments(query, signal)).map(documentOption),
  });
  const pickDocument = (id: string | null) => {
    if (!id) return;
    setReturns({});
    setDraftId('');
    navigate(`/billing/credit-notes/new/${id}`);
  };

  const chooseReason = (code: string | null) => {
    const next = code ?? '';
    setReasonCode(next);
    if (returnsEverything(next)) setReturns(returnAllLines(lines));
    else if (returnsEverything(reasonCode)) setReturns({});
  };
  const setLineQuantity = (line: OriginLine, quantity: number) =>
    setReturns((current) => {
      const next = { ...current };
      if (quantity <= 0) delete next[line.lineNumber];
      else
        next[line.lineNumber] = {
          quantity: String(quantity),
          restock: current[line.lineNumber]?.restock ?? true,
        };
      return next;
    });
  const setLineRestock = (line: OriginLine, restock: boolean) =>
    setReturns((current) =>
      current[line.lineNumber]
        ? { ...current, [line.lineNumber]: { ...current[line.lineNumber]!, restock } }
        : current,
    );

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
    onSuccess: async (created) => {
      if (draftId) await deleteDraft(draftId).catch(() => undefined);
      void queryClient.invalidateQueries({ queryKey: ['sales'] });
      void queryClient.invalidateQueries({ queryKey: ['credit-note-issue'] });
      toast('Nota de crédito registrada. Envíala a SUNAT desde el Historial.', 'success');
      navigate(`/billing/credit-notes/${created.id}`);
    },
  });

  const draftInput = () => ({
    relatedTaxDocument: documentId,
    reasonCode,
    reasonDescription,
    lines: buildReturnChoices(lines, returns),
  });
  const saving = useMutation({
    mutationFn: () => saveDraft({ ...draftInput(), ...(draftId ? { id: draftId } : {}) }),
    onSuccess: (draft) => {
      setDraftId(draft.id);
      hydratedDraft.current = draft.id;
      setSearch({ draft: draft.id }, { replace: true });
      void queryClient.invalidateQueries({ queryKey: ['credit-note-issue', 'drafts'] });
      toast('Borrador guardado.', 'success');
    },
  });
  const previewing = useMutation({
    mutationFn: async () => {
      // Open the tab synchronously so the browser does not treat it as an unrequested popup.
      const tab = window.open('', '_blank');
      try {
        const pdf = await fetchPreviewPdf(draftInput());
        const url = URL.createObjectURL(pdf);
        if (tab) tab.location.href = url;
        else window.location.assign(url);
      } catch (error) {
        tab?.close();
        throw error;
      }
    },
  });
  const removeDraft = useMutation({
    mutationFn: (id: string) => deleteDraft(id),
    onSuccess: () =>
      void queryClient.invalidateQueries({ queryKey: ['credit-note-issue', 'drafts'] }),
  });

  const submit = () => {
    setSubmitted(true);
    if (Object.keys(errors).length > 0 || issue.isPending) return;
    // An annulling reason cancels the sale and restocks it: ask once, plainly.
    if (annulsDocument(reasonCode)) setConfirmingAnnul(true);
    else issue.mutate();
  };
  const previewable = Boolean(reasonCode) && summary.count > 0;

  if (!canIssue)
    return (
      <Page>
        <EmptyState
          title="Acceso restringido"
          description="No tienes permiso para emitir notas de crédito."
        />
      </Page>
    );

  const header = (
    <>
      <PageHeader
        actions={
          <ButtonLink as={Link} to="/billing/credit-notes" variant="secondary">
            Ver historial
          </ButtonLink>
        }
        title="Emitir nota de crédito"
        subtitle="Anula, corrige o devuelve un comprobante de venta con una nota de crédito electrónica (SUNAT)."
      />
    </>
  );

  const pickerField = (
    <FormField label="Comprobante de referencia" required>
      <Combobox
        aria-label="Buscar comprobante"
        placeholder="Busca por serie-número, cliente o RUC"
        value={documentId || null}
        options={
          origin && documentId
            ? [
                documentOption({
                  id: documentId,
                  fullNumber: originNumber,
                  documentType: (origin.documentType as '01' | '03') ?? '01',
                  issueDate: origin.issueDate ?? '',
                  customerName: origin.customer?.businessName ?? null,
                  customerDocument: origin.customer?.ruc ?? null,
                  currency,
                  total: origin.total ?? '0',
                }),
              ]
            : []
        }
        loadOptions={loadDocuments}
        minSearchLength={0}
        emptyMessage="No hay comprobantes aceptados que coincidan."
        onChange={pickDocument}
        data-testid="credit-note-document-picker"
      />
    </FormField>
  );

  if (!documentId)
    return (
      <Page className="credit-note-issue">
        {header}
        <Card variant="panel">{pickerField}</Card>
        {draftsQuery.data && draftsQuery.data.length > 0 && (
          <Card variant="panel">
            <Section variant="panel" title="Borradores">
              <p className="credit-note-issue__hint">Notas que dejaste a medias.</p>
              <ul className="credit-note-issue__drafts">
                {draftsQuery.data.map((draft) => (
                  <li key={draft.id}>
                    <span>
                      <strong>{draft.relatedFullNumber ?? 'Comprobante'}</strong>
                      <small>
                        {[
                          CREDIT_NOTE_REASONS.find((r) => r.value === draft.reasonCode)?.text,
                          DateTime.fromISO(draft.updatedAt).toFormat('dd/MM/yyyy HH:mm'),
                        ]
                          .filter(Boolean)
                          .join(' · ')}
                      </small>
                    </span>
                    <Button
                      variant="secondary"
                      onClick={() => navigate(`/billing/credit-notes/new?draft=${draft.id}`)}
                    >
                      Continuar
                    </Button>
                    <Button
                      variant="secondary"
                      loading={removeDraft.isPending && removeDraft.variables === draft.id}
                      onClick={() => removeDraft.mutate(draft.id)}
                    >
                      Eliminar
                    </Button>
                  </li>
                ))}
              </ul>
            </Section>
          </Card>
        )}
      </Page>
    );

  if (documentQuery.isError || draftQuery.isError)
    return (
      <Page>
        <EmptyState
          variant="danger"
          title="No se pudo cargar el comprobante"
          description="Revisa que exista y pertenezca a esta sucursal."
          action={
            <>
              <Link to="/billing/credit-notes/new">Elegir otro comprobante</Link>
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
  const hint = reasonHint(reasonCode);

  const columns: DataTableColumn<OriginLine>[] = [
    { id: 'n', header: '#', render: (line) => line.lineNumber },
    {
      id: 'product',
      header: 'Producto',
      render: (line) => (
        <span className="credit-note-issue__product">
          <strong>{line.productName}</strong>
          {line.productCode && <small>{line.productCode}</small>}
        </span>
      ),
    },
    { id: 'sold', header: 'Vendido', align: 'right', render: (line) => Number(line.quantity) },
    {
      id: 'qty',
      header: 'A devolver *',
      align: 'right',
      render: (line) => (
        <QuantityStepper
          label={`Cantidad a devolver de ${line.productName}`}
          value={Number(returns[line.lineNumber]?.quantity ?? 0)}
          max={Number(line.quantity)}
          disabled={locked}
          onChange={(next) => setLineQuantity(line, next)}
        />
      ),
    },
    {
      id: 'price',
      header: 'Precio unitario',
      align: 'right',
      nowrap: true,
      render: (line) => formatMoney(line.unitPrice, currency),
    },
    {
      id: 'amount',
      header: 'Importe',
      align: 'right',
      nowrap: true,
      render: (line) => {
        const choice = returns[line.lineNumber];
        return choice ? centsToMoney(lineReturnCents(line, choice.quantity), currency) : '—';
      },
    },
    ...(showRestock
      ? [
          {
            id: 'restock',
            header: 'Reingresar a inventario',
            align: 'center' as const,
            render: (line: OriginLine) => (
              <Checkbox
                aria-label={`Reingresar ${line.productName} a inventario`}
                checked={returns[line.lineNumber]?.restock ?? false}
                disabled={!returns[line.lineNumber]}
                onChange={(event) => setLineRestock(line, event.target.checked)}
              />
            ),
          },
        ]
      : []),
  ];

  return (
    <Page className="credit-note-issue" aria-busy={issue.isPending}>
      {header}
      <DetailLayout
        className="credit-note-issue__layout"
        asideLabel="Resumen de la nota"
        aside={
          <Card variant="panel">
            <Section variant="panel" title="Resumen de nota de crédito">
              <dl className="credit-note-issue__totals">
                <div>
                  <dt>Serie y número</dt>
                  <dd data-testid="credit-note-next-number">
                    {numberQuery.data
                      ? `${numberQuery.data.series}-${padNumber(numberQuery.data.number)}`
                      : numberQuery.isError
                        ? 'Sin serie configurada'
                        : '…'}
                  </dd>
                </div>
                <div>
                  <dt>Comprobante afectado</dt>
                  <dd>{originNumber}</dd>
                </div>
                <div>
                  <dt>Base imponible</dt>
                  <dd>{formatMoney(summary.valueSold, currency)}</dd>
                </div>
                <div>
                  <dt>IGV</dt>
                  <dd>{formatMoney(summary.tax, currency)}</dd>
                </div>
                <div className="credit-note-issue__total">
                  <dt>Total a acreditar</dt>
                  <dd data-testid="credit-note-total">{formatMoney(summary.total, currency)}</dd>
                </div>
              </dl>
              {annulsDocument(reasonCode) && (
                <Alert tone="warning">
                  Anula la venta y repone todo el stock. No se puede deshacer.
                </Alert>
              )}
              {numberQuery.isError && (
                <Alert tone="danger">
                  No hay una serie de nota de crédito configurada para este comprobante.
                </Alert>
              )}
              {issue.isError && <Alert tone="danger">{issue.error.message}</Alert>}
              {blocked && <Alert tone="danger">{blocked}</Alert>}
              <div className="credit-note-issue__actions">
                <Button
                  variant="primary"
                  icon="ph ph-paper-plane-tilt"
                  loading={issue.isPending}
                  disabled={Boolean(blocked) || numberQuery.isError}
                  onClick={submit}
                  data-testid="credit-note-submit"
                >
                  Emitir nota de crédito
                </Button>
                <div className="credit-note-issue__actions-secondary">
                  <Button
                    variant="secondary"
                    icon="ph ph-eye"
                    loading={previewing.isPending}
                    disabled={!previewable}
                    onClick={() => previewing.mutate()}
                    data-testid="credit-note-preview"
                  >
                    Vista previa
                  </Button>
                  <Button
                    variant="secondary"
                    icon="ph ph-floppy-disk"
                    loading={saving.isPending}
                    onClick={() => saving.mutate()}
                    data-testid="credit-note-save-draft"
                  >
                    Guardar borrador
                  </Button>
                </div>
              </div>
            </Section>
          </Card>
        }
      >
        <Card variant="panel">
          {pickerField}
          <dl className="credit-note-issue__origin">
            <div>
              <dt>{DOCUMENT_LABEL[origin.documentType ?? ''] ?? 'Comprobante'}</dt>
              <dd>
                {originNumber}{' '}
                <Badge
                  pill
                  variant={
                    sunatStatus(origin.sunatStatus).stage === 'accepted' ? 'success' : 'warning'
                  }
                >
                  {status.label}
                </Badge>
              </dd>
            </div>
            <div>
              <dt>Fecha de emisión</dt>
              <dd>
                {origin.issueDate ? DateTime.fromISO(origin.issueDate).toFormat('dd/MM/yyyy') : '—'}
              </dd>
            </div>
            <div className="credit-note-issue__origin-wide">
              <dt>Cliente</dt>
              <dd title={origin.customer?.businessName ?? undefined}>
                {origin.customer?.businessName || 'Cliente no registrado'}
              </dd>
            </div>
            <div>
              <dt>{origin.customer?.ruc?.length === 11 ? 'RUC' : 'Documento'}</dt>
              <dd>{origin.customer?.ruc || '—'}</dd>
            </div>
          </dl>
          <div className="credit-note-issue__fields">
            <FormField
              label="Motivo de emisión (SUNAT)"
              required
              error={submitted ? errors.reason : undefined}
            >
              <Combobox
                aria-label="Motivo de emisión (SUNAT)"
                placeholder="Selecciona un motivo"
                options={REASON_OPTIONS}
                value={reasonCode || null}
                invalid={submitted && Boolean(errors.reason)}
                onChange={chooseReason}
                data-testid="credit-note-reason"
              />
            </FormField>
            <FormField
              label="Detalle del motivo (se imprime en la nota)"
              required
              error={submitted ? errors.description : undefined}
            >
              <TextAreaField
                rows={1}
                value={reasonDescription}
                maxLength={250}
                required
                invalid={submitted && Boolean(errors.description)}
                onChange={(event) => setReasonDescription(event.target.value)}
                data-testid="credit-note-detail"
              />
            </FormField>
          </div>
          {hint && (
            <p className="credit-note-issue__reason-hint">
              <i className="ph ph-info" aria-hidden="true" /> {hint}
            </p>
          )}
        </Card>
        <Card variant="panel">
          <div className="credit-note-issue__lines-head">
            <h3>Productos afectados</h3>
            <span>
              {locked
                ? 'Este motivo devuelve todos los ítems completos y repone su stock.'
                : showRestock
                  ? 'Desmarca “Reingresar” en los productos que no deben volver al inventario.'
                  : 'Indica las cantidades que se acreditarán.'}
            </span>
          </div>
          {linesQuery.isPending ? (
            <p role="status">Cargando ítems…</p>
          ) : linesQuery.isError ? (
            <Button onClick={() => void linesQuery.refetch()}>Reintentar ítems</Button>
          ) : (
            <DataTable
              variant="flush"
              testId="credit-note-lines"
              columns={columns}
              rows={lines}
              getRowKey={(line) => line.lineNumber}
              emptyMessage="El comprobante no tiene ítems."
            />
          )}
          {submitted && errors.lines && <Alert tone="danger">{errors.lines}</Alert>}
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

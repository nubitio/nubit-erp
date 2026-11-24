import './NewInvoicePage.scss';
import { useMemo, useRef, useState, type ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import { Link } from 'react-router-dom';
import {
  Alert,
  Badge,
  Button,
  ButtonLink,
  Combobox,
  DatePicker,
  FormField,
  IconButton,
  Page,
  PageHeader,
  SegmentedControl,
  Stack,
  TextAreaField,
  TextField,
} from '@nubitio/ui';
import { EntityDropdown, mapApiViolations } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { generateIdempotencyKey } from '@shared/lib/idempotency';
import { useTenantIgvRate } from '@shared/hooks/useTenantIgvRate';
import { useWarehouses } from '@shared/hooks/useWarehouses';
import { useCurrencySettings } from '@shared/hooks/useCurrencySettings';
import { isCreditPaymentMethod, nowUtcIso, todayBusinessDate } from '@shared/pos/helpers';
import { usePosPaymentMethods } from '@features/pos/usePosCatalogData';
import { mapPosCustomer } from '@features/pos/lib/mapPosCustomer';
import { httpRequestData } from '@shared/api/network';
import { priceListEndpoints } from '@features/catalog/api/priceListEndpoints';
import type { PriceListApplyDto } from '@features/catalog/types/PriceListItemDto';
import { formatPosCustomerLabel } from '@features/pos/lib/mapPosCustomer';
import {
  addDays,
  applyPriceList,
  buildSalePayload,
  draftTotals,
  lineTotal,
  newLine,
  pickPrice,
  validateDraft,
  type PriceListPrices,
  type InvoiceCurrency,
  type InvoiceDraft,
  type InvoiceLineDraft,
  type PaymentCondition,
  type VoucherChoice,
} from './invoiceDraft';

const API = getCoreApiBaseUrl();
const SUNAT_BACKDATE_DAYS = 3;

const VOUCHER_OPTIONS: { value: VoucherChoice; label: string }[] = [
  { value: 'AUTO', label: 'Automático' },
  { value: 'FACTURA', label: 'Factura' },
  { value: 'BOLETA', label: 'Boleta' },
];
const CONDITION_OPTIONS: { value: PaymentCondition; label: string }[] = [
  { value: 'CREDIT', label: 'Crédito' },
  { value: 'CASH', label: 'Contado' },
];
const CURRENCY_OPTIONS: { value: InvoiceCurrency; label: string }[] = [
  { value: 'PEN', label: 'Soles' },
  { value: 'USD', label: 'Dólares' },
];

const Panel = ({
  title,
  aside,
  children,
}: {
  title: string;
  aside?: ReactNode;
  children: ReactNode;
}) => (
  <section className="new-invoice__panel">
    <header className="new-invoice__panel-head">
      <h2>{title}</h2>
      {aside}
    </header>
    <div className="new-invoice__panel-body">{children}</div>
  </section>
);

/**
 * B2B document-style invoice: customer, conditions, free service lines, totals.
 * It posts to the same `sales` endpoint as the POS, so taxes, stock rules, receivables and the
 * SUNAT voucher follow the one server-side path; only the screen differs.
 */
export const NewInvoicePage = () => {
  const navigate = useNavigate();
  const today = todayBusinessDate();
  const { igvRate } = useTenantIgvRate();
  const { currency: defaultCurrency, exchangeRate: defaultRate } = useCurrencySettings();
  const { data: paymentMethods = [] } = usePosPaymentMethods();
  const { warehouses } = useWarehouses();
  const idempotencyKey = useRef(generateIdempotencyKey());

  const [draft, setDraft] = useState<InvoiceDraft>(() => ({
    customerId: null,
    warehouseId: null,
    voucher: 'AUTO',
    currency: defaultCurrency,
    exchangeRate: defaultRate,
    issuedDate: today,
    condition: 'CREDIT',
    paymentMethodId: null,
    termDays: '30',
    note: '',
    lines: [newLine()],
  }));
  const [customerLabel, setCustomerLabel] = useState('');
  const [prices, setPrices] = useState<PriceListPrices>(new Map());
  const [priceListName, setPriceListName] = useState<string | null>(null);
  const [saving, setSaving] = useState<'emit' | 'save' | null>(null);
  const [formError, setFormError] = useState<string | null>(null);

  const set = <K extends keyof InvoiceDraft>(key: K, value: InvoiceDraft[K]) =>
    setDraft((current) => ({ ...current, [key]: value }));
  const setLine = (key: string, patch: Partial<InvoiceLineDraft>) =>
    setDraft((current) => ({
      ...current,
      lines: current.lines.map((line) => (line.key === key ? { ...line, ...patch } : line)),
    }));

  /** The customer's price list decides line prices, like in the POS. */
  const selectCustomer = async (value: string | null, item?: Record<string, unknown> | null) => {
    set('customerId', value);
    setCustomerLabel(item ? String(item['businessName'] ?? '') : '');
    const priceListId = mapPosCustomer(item)?.priceListId;
    let next: PriceListPrices = new Map();
    if (priceListId != null) {
      try {
        const applied = await httpRequestData<PriceListApplyDto[]>(
          httpClient.get<PriceListApplyDto[]>(priceListEndpoints.apply(priceListId)),
        );
        next = new Map(applied.map((entry) => [String(entry.id), String(entry.price)]));
      } catch {
        toast(
          'No se pudo cargar la tarifa del cliente; se usan los precios de catálogo.',
          'warning',
        );
      }
    }
    setPrices(next);
    setPriceListName(next.size > 0 ? 'Tarifa del cliente' : null);
    setDraft((current) => ({ ...current, lines: applyPriceList(current.lines, next) }));
  };

  const warehouseId = draft.warehouseId ?? (warehouses[0] ? String(warehouses[0].id) : null);
  const effective: InvoiceDraft = { ...draft, warehouseId };

  const creditMethod = paymentMethods.find((method) => isCreditPaymentMethod(method));
  const cashMethods = paymentMethods.filter((method) => !isCreditPaymentMethod(method));
  const totals = useMemo(() => draftTotals(draft.lines, igvRate), [draft.lines, igvRate]);
  const money = (value: number) => NumberUtils.formatCurrency(value, draft.currency);
  const dueDate = addDays(draft.issuedDate, Math.floor(Number(draft.termDays) || 0));

  const submit = async (emit: boolean) => {
    const problem = validateDraft(effective, totals.total, paymentMethods);
    if (problem) {
      setFormError(problem);
      return;
    }
    setFormError(null);
    setSaving(emit ? 'emit' : 'save');
    try {
      const response = await httpClient.post<Record<string, unknown>>(
        `${API}sales`,
        buildSalePayload(
          API,
          effective,
          totals.total,
          paymentMethods,
          creditMethod?.id ?? null,
          emit,
          nowUtcIso(),
        ),
        { headers: { 'Idempotency-Key': idempotencyKey.current } },
      );
      const id = String(response.data.id ?? '');
      toast(emit ? 'Factura emitida.' : 'Venta registrada.', 'success');
      navigate(id ? `/billing/sales/${id}` : '/billing/sales');
    } catch (error) {
      // A 422 with violations is not toasted by HttpClient: this screen shows it. Other failures
      // were already reported by HttpClient.
      const violations = (error as { data?: { violations?: unknown } } | null)?.data?.violations;
      if (Array.isArray(violations)) {
        const mapped = mapApiViolations(violations);
        setFormError([...mapped.unassigned, ...Object.values(mapped.fieldErrors)].join(' '));
      }
      // A failed attempt may be retried with edits: it is a new request, not a replay.
      idempotencyKey.current = generateIdempotencyKey();
    } finally {
      setSaving(null);
    }
  };

  const taxPercent = Number((igvRate * 100).toFixed(2));
  const voucherLabel = VOUCHER_OPTIONS.find((o) => o.value === draft.voucher)?.label ?? '';
  const actionButtons = () => (
    <>
      <Button
        variant="primary"
        fullWidth
        icon="ph ph-paper-plane-tilt"
        loading={saving === 'emit'}
        disabled={saving !== null}
        onClick={() => void submit(true)}
        data-testid="new-invoice-emit-panel"
      >
        Emitir factura
      </Button>
      <Button
        variant="secondary"
        fullWidth
        icon="ph ph-floppy-disk"
        loading={saving === 'save'}
        disabled={saving !== null}
        onClick={() => void submit(false)}
        data-testid="new-invoice-save-panel"
      >
        Guardar borrador
      </Button>
      <ButtonLink
        as={Link}
        to="/billing/sales"
        variant="secondary"
        icon="ph ph-x"
        data-testid="new-invoice-cancel"
      >
        Cancelar
      </ButtonLink>
    </>
  );

  return (
    <Page className="new-invoice">
      <PageHeader
        title="Nueva factura"
        subtitle="Venta de servicios o productos a clientes empresa."
      />

      {formError ? <Alert tone="danger">{formError}</Alert> : null}

      <div className="new-invoice__layout">
        <Stack gap={4}>
          <Panel title="Cliente">
            <div className="new-invoice__cols-2">
              <FormField label="Cliente" required>
                <EntityDropdown
                  url={`${API}customers`}
                  valueField="id"
                  textField="businessName"
                  searchExpr={['businessName', 'ruc', 'dni']}
                  placeholder="Busca por nombre o RUC"
                  itemFormatter={(item) => formatPosCustomerLabel(item)}
                  value={draft.customerId}
                  onChange={(value, item) => void selectCustomer(value, item)}
                  required
                />
              </FormField>
              <FormField label="Almacén" required>
                <Combobox
                  options={warehouses.map((w) => ({ value: String(w.id), label: w.name }))}
                  value={warehouseId}
                  onChange={(value) => set('warehouseId', value)}
                />
              </FormField>
            </div>
          </Panel>

          <Panel title="Condiciones">
            <div className="new-invoice__cols-2">
              <FormField label="Comprobante">
                <SegmentedControl
                  ariaLabel="Tipo de comprobante"
                  options={VOUCHER_OPTIONS}
                  value={draft.voucher}
                  onChange={(value) => set('voucher', value)}
                />
              </FormField>
              <FormField label="Moneda">
                <SegmentedControl
                  ariaLabel="Moneda"
                  options={CURRENCY_OPTIONS}
                  value={draft.currency}
                  onChange={(value) => set('currency', value)}
                />
              </FormField>
            </div>
            <div className="new-invoice__cols-3">
              <FormField
                label="Fecha de emisión"
                helpText={`SUNAT admite hasta ${SUNAT_BACKDATE_DAYS} días atrás.`}
              >
                <DatePicker
                  value={draft.issuedDate}
                  min={addDays(today, -SUNAT_BACKDATE_DAYS)}
                  max={today}
                  clearable={false}
                  locale="es-PE"
                  onChange={(value) => set('issuedDate', value ?? today)}
                />
              </FormField>
              <FormField label="Condición de pago">
                <SegmentedControl
                  ariaLabel="Condición de pago"
                  options={CONDITION_OPTIONS}
                  value={draft.condition}
                  onChange={(value) => set('condition', value)}
                />
              </FormField>
              {draft.condition === 'CREDIT' ? (
                <FormField label="Días de crédito" helpText={`Vence el ${dueDate}.`} required>
                  <TextField
                    inputMode="numeric"
                    value={draft.termDays}
                    onChange={(event) => set('termDays', event.target.value.replace(/\D/g, ''))}
                  />
                </FormField>
              ) : (
                <FormField label="Medio de pago" required>
                  <Combobox
                    options={cashMethods.map((m) => ({ value: String(m.id), label: m.name }))}
                    value={draft.paymentMethodId}
                    onChange={(value) => set('paymentMethodId', value)}
                  />
                </FormField>
              )}
            </div>
            {draft.currency === 'USD' ? (
              <div className="new-invoice__cols-3">
                <FormField label="Tipo de cambio" required>
                  <TextField
                    inputMode="decimal"
                    value={draft.exchangeRate}
                    onChange={(event) => set('exchangeRate', event.target.value)}
                  />
                </FormField>
              </div>
            ) : null}
          </Panel>

          <Panel title="Detalle de factura">
            <div className="new-invoice__lines" role="table" aria-label="Detalle de factura">
              <div className="new-invoice__line new-invoice__line--head" role="row">
                <span role="columnheader">#</span>
                <span role="columnheader">Producto o servicio</span>
                <span role="columnheader">Descripción</span>
                <span role="columnheader">Cant.</span>
                <span role="columnheader">Precio (con IGV)</span>
                <span role="columnheader" className="new-invoice__num">
                  Subtotal
                </span>
                <span />
              </div>
              {draft.lines.map((line, index) => (
                <div className="new-invoice__line" role="row" key={line.key}>
                  <span className="new-invoice__index">{index + 1}</span>
                  <EntityDropdown
                    url={`${API}products`}
                    valueField="id"
                    textField="name"
                    searchExpr={['name', 'code', 'sku']}
                    placeholder="Selecciona un producto o servicio"
                    value={line.productId}
                    onChange={(value, item) => {
                      const basePrice = String(item?.['salePrice'] ?? '');
                      setLine(line.key, {
                        productId: value,
                        description: line.description || String(item?.['name'] ?? ''),
                        basePrice,
                        unitPrice: value ? pickPrice(value, basePrice, prices) : '',
                      });
                    }}
                  />
                  <TextField
                    aria-label={`Descripción de la línea ${index + 1}`}
                    placeholder="Descripción"
                    value={line.description}
                    onChange={(event) => setLine(line.key, { description: event.target.value })}
                  />
                  <TextField
                    aria-label={`Cantidad de la línea ${index + 1}`}
                    inputMode="decimal"
                    value={line.quantity}
                    onChange={(event) => setLine(line.key, { quantity: event.target.value })}
                  />
                  <TextField
                    aria-label={`Precio de la línea ${index + 1}`}
                    inputMode="decimal"
                    placeholder="0.00"
                    value={line.unitPrice}
                    onChange={(event) => setLine(line.key, { unitPrice: event.target.value })}
                  />
                  <span className="new-invoice__num">{money(lineTotal(line))}</span>
                  <IconButton
                    icon="ph ph-trash"
                    label="Quitar línea"
                    variant="danger"
                    disabled={draft.lines.length === 1}
                    onClick={() =>
                      set(
                        'lines',
                        draft.lines.filter((l) => l.key !== line.key),
                      )
                    }
                  />
                </div>
              ))}
            </div>
            <div>
              <Button
                variant="primary"
                icon="ph ph-plus"
                onClick={() => set('lines', [...draft.lines, newLine()])}
                data-testid="new-invoice-add-line"
              >
                Agregar ítem
              </Button>
            </div>
          </Panel>

          <Panel title="Observaciones (opcional)">
            <TextAreaField
              aria-label="Observaciones"
              rows={3}
              value={draft.note}
              placeholder="Ej. Indicar condiciones de entrega, datos adicionales, etc."
              onChange={(event) => set('note', event.target.value)}
            />
          </Panel>
        </Stack>

        <aside className="new-invoice__summary" aria-label="Resumen de factura">
          <Panel
            title="Resumen de factura"
            aside={
              <Badge variant="primary" pill>
                Borrador
              </Badge>
            }
          >
            <dl className="new-invoice__facts">
              <div>
                <dt>Cliente</dt>
                <dd>{customerLabel || '—'}</dd>
              </div>
              {priceListName ? (
                <div>
                  <dt>Tarifa</dt>
                  <dd>{priceListName}</dd>
                </div>
              ) : null}
              <div>
                <dt>Comprobante</dt>
                <dd>{voucherLabel}</dd>
              </div>
              <div>
                <dt>Condición de pago</dt>
                <dd>
                  <Badge variant="info" pill>
                    {draft.condition === 'CREDIT'
                      ? `Crédito (${draft.termDays || 0} días)`
                      : 'Contado'}
                  </Badge>
                </dd>
              </div>
              {draft.condition === 'CREDIT' ? (
                <div>
                  <dt>Vencimiento</dt>
                  <dd>{dueDate}</dd>
                </div>
              ) : null}
            </dl>
            <div className="new-invoice__totals">
              <div>
                <span>Op. gravada</span>
                <span>{money(totals.total - totals.includedTax)}</span>
              </div>
              <div>
                <span>IGV ({taxPercent}%)</span>
                <span>{money(totals.includedTax)}</span>
              </div>
              <div>
                <span>Descuento</span>
                <span>{money(0)}</span>
              </div>
              <div className="new-invoice__totals-grand">
                <span>Total</span>
                <span>{money(totals.total)}</span>
              </div>
            </div>
            <Stack gap={2}>{actionButtons()}</Stack>
          </Panel>
        </aside>
      </div>
    </Page>
  );
};

import {
  Alert,
  AppDialog,
  Badge,
  Button,
  DescriptionList,
  DetailLayout,
  EmptyState,
  FormField,
  PageHeader,
  Section,
  TextField,
} from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import supplierBillService, {
  type SupplierBillPayment,
  type SupplierBillSummary,
} from './services/supplierBillService';
import { useIdempotencyKey } from '@shared/lib/idempotency';
import { supplierPaymentAmount } from './model/paymentAmount';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';
import { formatDate, formatMoney } from '@shared/lib/displayFormat';
import { MoneyAccountPicker } from '../treasury/MoneyAccountPicker';
import { treasuryErrorMessage } from '../treasury/api/treasuryApi';
import './SupplierBillPay.scss';

const API = getCoreApiBaseUrl();
const METHOD_FILTERS = [
  { field: 'settlementBehavior', operator: '=', value: 'COLLECTED' },
  { field: 'isActive', operator: '=', value: 1 },
];
const METHOD_SEARCH = ['name'];

/** Remount on a different invoice so no draft or late response crosses records. */
export const SupplierBillPay = () => {
  const { id } = useParams<{ id: string }>();
  return <SupplierBillPaymentPage key={id} id={id} />;
};

function SupplierBillPaymentPage({ id }: { id?: string }) {
  const navigate = useNavigate();
  const amountRef = useRef<HTMLInputElement>(null);
  const methodRef = useRef<HTMLDivElement>(null);
  const inFlight = useRef(false);
  const [amount, setAmount] = useState('');
  const [reference, setReference] = useState('');
  const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);
  const [moneyAccountId, setMoneyAccountId] = useState('');
  const [busy, setBusy] = useState(false);
  const [bill, setBill] = useState<SupplierBillSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [amountError, setAmountError] = useState(false);
  const [methodError, setMethodError] = useState(false);
  const idempotency = useIdempotencyKey(
    [id, amount, paymentMethodId, moneyAccountId, reference].join('|'),
  );
  const [reloadKey, setReloadKey] = useState(0);
  const [reversing, setReversing] = useState<SupplierBillPayment | null>(null);
  const canPay = hasPermission('accounts_payable.pay');
  const canView = hasPermission('accounts_payable.view');

  useEffect(() => {
    if (!id || !canView) return;
    const controller = new AbortController();
    supplierBillService()
      .get(id, controller.signal)
      .then(({ data }) => {
        if (!controller.signal.aborted) setBill(data);
      })
      .catch(() => {
        if (!controller.signal.aborted) setLoadError(true);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [id, reloadKey, canView]);

  const currency = bill?.currency ?? 'PEN';
  const isVoid = bill?.status === 'VOID';
  const hasBalance = bill?.balanceDue != null && Number(bill.balanceDue) > 0;
  const money = (value?: string) => (value == null ? '—' : formatMoney(value, currency));
  const document = [bill?.series, bill?.number].filter(Boolean).join('-') || 'Sin número';

  async function registerPayment() {
    if (
      !id ||
      !bill ||
      !canPay ||
      loading ||
      loadError ||
      isVoid ||
      !hasBalance ||
      inFlight.current
    )
      return;
    const canonical = supplierPaymentAmount(amount, bill.balanceDue!, currency);
    setAmountError(canonical === null);
    setMethodError(!paymentMethodId);
    if (canonical === null) {
      amountRef.current?.focus();
      return;
    }
    if (!paymentMethodId) {
      methodRef.current?.querySelector('input')?.focus();
      return;
    }
    inFlight.current = true;
    setBusy(true);
    setActionError(null);
    try {
      await supplierBillService().pay(id, {
        amount: canonical,
        paymentMethodId: Number(paymentMethodId),
        reference: reference.trim() || undefined,
        moneyAccountId,
        idempotencyKey: idempotency.current(),
      });
      idempotency.renew();
      toast('Pago registrado', 'success');
      navigate('/accounts-payable');
    } catch (error) {
      // The server says why (no open cash drawer, wrong currency, balance exceeded); show that, not a guess.
      setActionError(
        treasuryErrorMessage(
          error,
          'No se pudo registrar el pago. Revisa el saldo y el método seleccionado e inténtalo nuevamente.',
        ),
      );
    } finally {
      inFlight.current = false;
      setBusy(false);
    }
  }

  async function reversePayment() {
    if (!id || !reversing || reversing.reversedAt || !canPay || isVoid || inFlight.current) return;
    inFlight.current = true;
    setBusy(true);
    setActionError(null);
    try {
      const { data } = await supplierBillService().reverse(id, reversing.id);
      setBill(data);
      setReversing(null);
      toast('Pago reversado', 'success');
    } catch {
      setActionError(
        'No se pudo reversar el pago. El historial se conserva; inténtalo nuevamente.',
      );
    } finally {
      inFlight.current = false;
      setBusy(false);
    }
  }

  return (
    <div className="supplier-payment-page">
      <PageHeader
        title="Registrar pago"
        subtitle="Abona a una factura de proveedor y conserva su historial."
        breadcrumbSlot={
          busy ? (
            <span>Cuentas por pagar</span>
          ) : (
            <Link to="/accounts-payable">← Cuentas por pagar</Link>
          )
        }
      />
      {!canView ? (
        <EmptyState
          title="Sin acceso a cuentas por pagar"
          description="Tu usuario no tiene permiso para consultar esta factura."
          icon="lock"
        />
      ) : loading ? (
        <p role="status">Cargando factura…</p>
      ) : loadError || !bill ? (
        <Alert tone="danger">
          No se pudo cargar el contexto de la factura.{' '}
          <Button
            variant="secondary"
            size="sm"
            onClick={() => {
              setLoadError(false);
              setLoading(true);
              setReloadKey((value) => value + 1);
            }}
          >
            Reintentar factura
          </Button>
        </Alert>
      ) : (
        <DetailLayout
          asideLabel="Resumen de la factura"
          aside={
            <Section title="Factura del proveedor" variant="panel">
              <strong className="supplier-payment-page__supplier">
                {bill.supplier?.businessName ?? 'Proveedor'}
              </strong>
              <p className="supplier-payment-page__document">{document}</p>
              <Badge variant={isVoid ? 'danger' : hasBalance ? 'warning' : 'success'} outlined>
                {isVoid
                  ? 'Anulada'
                  : hasBalance
                    ? bill.status === 'PARTIAL'
                      ? 'Pago parcial'
                      : 'Pendiente de pago'
                    : 'Pagada'}
              </Badge>
              <DescriptionList
                items={[
                  { term: 'Emisión', value: bill.issueDate ? formatDate(bill.issueDate) : '—' },
                  { term: 'Vencimiento', value: bill.dueDate ? formatDate(bill.dueDate) : '—' },
                  { term: 'Importe original', value: money(bill.amount) },
                  { term: 'Pagado', value: money(bill.paidTotal) },
                  { term: 'Saldo pendiente', value: <strong>{money(bill.balanceDue)}</strong> },
                ]}
              />
            </Section>
          }
        >
          {actionError && !reversing && <Alert tone="danger">{actionError}</Alert>}
          {isVoid ? (
            <Alert tone="warning">Esta factura está anulada. No admite pagos ni reversiones.</Alert>
          ) : !canPay ? (
            <Alert>
              Consulta de factura. Tu usuario no tiene permiso para registrar o reversar pagos.
            </Alert>
          ) : !hasBalance ? (
            <p role="status">
              Esta factura está totalmente pagada. Puedes reversar un pago desde el historial.
            </p>
          ) : (
            <Section title="Datos del pago" variant="panel">
              <form
                onSubmit={(event) => {
                  event.preventDefault();
                  void registerPayment();
                }}
                noValidate
                aria-busy={busy}
              >
                <FormField label={`Monto (${currency})`} htmlFor="supplier-bill-pay-amount">
                  <TextField
                    ref={amountRef}
                    id="supplier-bill-pay-amount"
                    type="number"
                    min={0.01}
                    max={bill.balanceDue}
                    step={0.01}
                    required
                    placeholder="0.00"
                    value={amount}
                    disabled={busy}
                    aria-invalid={amountError || undefined}
                    aria-describedby={
                      amountError ? 'supplier-payment-amount-error' : 'supplier-payment-amount-help'
                    }
                    onChange={(event) => {
                      setAmount(event.target.value);
                      setAmountError(false);
                    }}
                  />
                  <p id="supplier-payment-amount-help" className="supplier-payment-page__hint">
                    Saldo disponible para este pago: {money(bill.balanceDue)}
                  </p>
                  {amountError && (
                    <p
                      id="supplier-payment-amount-error"
                      className="supplier-payment-page__error"
                      role="alert"
                    >
                      Ingresa un importe mayor a cero, con hasta dos decimales y sin exceder el
                      saldo.
                    </p>
                  )}
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={busy}
                    onClick={() => {
                      setAmount(bill.balanceDue!);
                      setAmountError(false);
                      amountRef.current?.focus();
                    }}
                  >
                    Pagar saldo completo
                  </Button>
                </FormField>
                <div ref={methodRef}>
                  <FormField label="Método de pago" htmlFor="supplier-bill-pay-method">
                    <EntityDropdown
                      id="supplier-bill-pay-method"
                      label="Método de pago"
                      required
                      invalid={methodError}
                      describedBy={methodError ? 'supplier-payment-method-error' : undefined}
                      url={`${API}payment-methods`}
                      filters={METHOD_FILTERS}
                      value={paymentMethodId}
                      onChange={(value) => {
                        setPaymentMethodId(value);
                        setMoneyAccountId('');
                        setMethodError(false);
                      }}
                      disabled={busy}
                      placeholder="Selecciona un método de pago"
                      searchExpr={METHOD_SEARCH}
                    />
                    {methodError && (
                      <p
                        id="supplier-payment-method-error"
                        className="supplier-payment-page__error"
                        role="alert"
                      >
                        Selecciona un método de pago.
                      </p>
                    )}
                  </FormField>
                </div>
                <MoneyAccountPicker
                  currency={currency}
                  paymentMethodId={paymentMethodId}
                  value={moneyAccountId}
                  onChange={setMoneyAccountId}
                  disabled={busy}
                />
                <FormField label="Referencia (opcional)" htmlFor="supplier-bill-pay-reference">
                  <TextField
                    id="supplier-bill-pay-reference"
                    placeholder="N° de operación"
                    maxLength={100}
                    value={reference}
                    disabled={busy}
                    onChange={(event) => setReference(event.target.value)}
                  />
                </FormField>
                <div className="supplier-payment-page__actions">
                  <Button
                    variant="secondary"
                    disabled={busy}
                    onClick={() => navigate('/accounts-payable')}
                  >
                    Cancelar
                  </Button>
                  <Button
                    type="submit"
                    variant="primary"
                    loading={busy}
                    disabled={busy}
                    data-testid="supplier-bill-pay-submit-btn"
                  >
                    {busy ? 'Registrando…' : 'Registrar pago'}
                  </Button>
                </div>
              </form>
            </Section>
          )}
          <Section title="Historial de pagos" variant="panel">
            {bill.payments?.length ? (
              <ul className="supplier-payment-page__history">
                {bill.payments.map((payment) => (
                  <li key={payment.id}>
                    <div>
                      <strong>{money(payment.amount)}</strong>
                      <p>
                        {formatDate(payment.paidAt)}
                        {payment.reference ? ` · ${payment.reference}` : ''}
                      </p>
                    </div>
                    {payment.reversedAt ? (
                      <Badge variant="secondary" outlined>
                        Reversado
                      </Badge>
                    ) : canPay && !isVoid ? (
                      <Button
                        variant="ghost"
                        size="sm"
                        disabled={busy}
                        onClick={() => {
                          setActionError(null);
                          setReversing(payment);
                        }}
                      >
                        Reversar
                      </Button>
                    ) : (
                      <Badge variant="success" outlined>
                        Registrado
                      </Badge>
                    )}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="supplier-payment-page__hint">
                Aún no se registraron pagos para esta factura.
              </p>
            )}
          </Section>
        </DetailLayout>
      )}
      <AppDialog
        open={reversing !== null}
        title="Reversar pago"
        role="alertdialog"
        onClose={() => {
          if (!busy) {
            setReversing(null);
            setActionError(null);
          }
        }}
        showCloseButton={!busy}
        closeOnEscape={!busy}
        closeOnOutsideClick={!busy}
        width={440}
        footer={
          <>
            <Button
              variant="secondary"
              disabled={busy}
              onClick={() => {
                setReversing(null);
                setActionError(null);
              }}
            >
              Cancelar
            </Button>
            <Button
              variant="danger"
              loading={busy}
              disabled={busy}
              onClick={() => void reversePayment()}
            >
              Confirmar reversión
            </Button>
          </>
        }
      >
        <p>
          Se reversará el pago de <strong>{money(reversing?.amount)}</strong> de{' '}
          {bill?.supplier?.businessName ?? 'este proveedor'} y se restituirá el saldo pendiente. El
          pago permanecerá en el historial.
        </p>
        {actionError && <Alert tone="danger">{actionError}</Alert>}
      </AppDialog>
    </div>
  );
}

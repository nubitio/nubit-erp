import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Button,
  Card,
  DataTable,
  DescriptionList,
  DetailLayout,
  EmptyState,
  Page,
  PageHeader,
  Section,
  Timeline,
  TimelineItem,
  useTheme,
} from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import { hasPermission } from '@shared/permissions';
import { useSessionInfo } from '@shared/sessionInfo';
import toast from '@shared/lib/SharedUtils';
import { saleEndpoints } from '../api/saleEndpoints';
import { runVoidSaleAction } from '../lib/saleActions';
import { SalePrintPreviewPopup } from '../SalePrintPreviewPopup';
import { SaleStatusBadges } from './SaleStatusBadges';
import { SaleCollectPaymentDialog } from './SaleCollectPaymentDialog';
import {
  saleDate,
  saleMoney,
  saleTitle,
  type SaleDetail,
  type SaleDetailItem,
} from './saleDetailModel';
import './SaleDetailPage.scss';
import { TaxDocumentPanel } from '../sunat/TaxDocumentPanel';

function collection<T>(data: T[] | { member?: T[]; 'hydra:member'?: T[] }): T[] {
  return Array.isArray(data) ? data : (data.member ?? data['hydra:member'] ?? []);
}

export function SaleDetailPage() {
  const { id = '' } = useParams();
  const { theme } = useTheme();
  const { currentBranchId } = useSessionInfo();
  const queryClient = useQueryClient();
  const canView = hasPermission('sales.view');
  const [collecting, setCollecting] = useState(false);
  const [preview, setPreview] = useState(false);
  const [busy, setBusy] = useState(false);
  const saleQuery = useQuery({
    queryKey: ['sales', 'detail', currentBranchId, id],
    queryFn: async () => (await httpClient.get<SaleDetail>(saleEndpoints.detail(id))).data,
    enabled: canView && Boolean(id),
    retry: false,
  });
  const itemsQuery = useQuery({
    queryKey: ['sales', 'detail-items', currentBranchId, id],
    queryFn: async () =>
      collection(
        (
          await httpClient.get<
            SaleDetailItem[] | { member?: SaleDetailItem[]; 'hydra:member'?: SaleDetailItem[] }
          >(saleEndpoints.items(id))
        ).data,
      ).sort((a, b) => a.lineNo - b.lineNo),
    enabled: saleQuery.isSuccess && canView,
    retry: false,
  });
  const back = <Link to="/billing/sales">Volver a ventas</Link>;
  const status = (saleQuery.error as { status?: number } | null)?.status;
  if (!canView || status === 403)
    return (
      <Page>
        <EmptyState
          title="Acceso restringido"
          description="No tienes permiso para consultar esta venta."
          action={back}
        />
      </Page>
    );
  if (saleQuery.isError)
    return (
      <Page>
        <EmptyState
          variant="danger"
          title={status === 404 ? 'Venta no encontrada' : 'No se pudo cargar la venta'}
          description={
            status === 404
              ? 'La venta no existe o no está disponible en esta sucursal.'
              : 'Intenta consultar la venta nuevamente.'
          }
          action={
            <>
              {back}
              {status !== 404 && (
                <Button onClick={() => void saleQuery.refetch()}>Reintentar</Button>
              )}
            </>
          }
        />
      </Page>
    );
  if (!saleQuery.data)
    return (
      <Page>
        <p role="status">Cargando venta…</p>
      </Page>
    );
  const sale = saleQuery.data;
  const money = (value: string | null | undefined) => saleMoney(value, sale.currency);
  const canCollect =
    hasPermission('sales.collect') &&
    sale.status !== 'VOIDED' &&
    sale.paymentState === 'due' &&
    Number(sale.balanceDue) > 0;
  const canVoid =
    hasPermission('sales.void') && sale.status !== 'VOIDED' && sale.hasInvoice === false;
  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['sales'] });
  };
  const openPdf = () =>
    window.open(
      `/receipt/${encodeURIComponent(sale.id)}/ticket?format=a4`,
      '_blank',
      'noopener,noreferrer',
    );
  const activity = [
    { label: 'Venta registrada', date: sale.createdAt },
    { label: 'Venta emitida', date: sale.issuedAt },
    ...(sale.updatedAt && sale.updatedAt !== sale.createdAt
      ? [{ label: 'Venta actualizada', date: sale.updatedAt }]
      : []),
  ]
    .filter((item) => item.date)
    .sort((a, b) => Date.parse(b.date!) - Date.parse(a.date!));

  return (
    <Page className="sale-detail" aria-busy={busy}>
      <PageHeader
        title={saleTitle(sale)}
        breadcrumbSlot={
          <Link to="/billing/sales" className="nb-page-header__breadcrumb">
            <span className="sale-detail__back-icon">
              <img src="/assets/images/design-system/sale-detail/arrow-right.svg" alt="" />
            </span>{' '}
            Ventas
          </Link>
        }
        metadata={
          <SaleStatusBadges
            paymentState={sale.paymentState}
            sunatState={sale.sunatState}
            sunatCode={sale.sunatStatus}
            hasInvoice={sale.hasInvoice}
          />
        }
        subtitle={`Emitida: ${saleDate(sale.issuedAt ?? sale.issuedDate, Boolean(sale.issuedAt))}${sale.warehouse?.name ? ` · ${sale.warehouse.name}` : ''}`}
        actions={
          <div className="sale-detail__actions">
            <Button onClick={() => setPreview(true)} disabled={busy}>
              Vista previa
            </Button>
            <Button onClick={openPdf} disabled={busy}>
              PDF{' '}
              <img
                src={`/assets/images/design-system/sale-detail/pdf${theme === 'dark' ? '-dark' : ''}.svg`}
                alt=""
              />
            </Button>
            {canVoid && (
              <Button
                variant="danger"
                disabled={busy}
                onClick={() =>
                  void runVoidSaleAction(sale.id, {
                    showLoading: () => setBusy(true),
                    hideLoading: () => setBusy(false),
                    refreshGrid: refresh,
                    notify: (message, type) => toast(message, type),
                  })
                }
              >
                Anular venta
              </Button>
            )}
            {canCollect && (
              <Button
                variant="primary"
                onClick={() => setCollecting(true)}
                disabled={busy || saleQuery.isFetching}
              >
                Registrar pago{' '}
                <img src="/assets/images/design-system/sale-detail/payment.svg" alt="" />
              </Button>
            )}
          </div>
        }
      />
      <DetailLayout
        asideLabel="Cliente y comprobante"
        aside={
          <>
            <Card variant="panel">
              <Section variant="panel" title="Cliente">
                <DescriptionList
                  items={[
                    {
                      term: 'Razón social',
                      value: sale.customer?.businessName || 'Cliente no registrado',
                    },
                    { term: 'RUC / DNI', value: sale.customer?.ruc || 'Sin documento registrado' },
                    {
                      term: 'Dirección',
                      value: sale.customer?.fiscalAddress || 'Sin dirección registrada',
                    },
                    { term: 'Correo', value: sale.customer?.email || 'Sin correo registrado' },
                  ]}
                />
              </Section>
            </Card>
            <Card variant="panel">
              <TaxDocumentPanel sale={sale} />
            </Card>
            <Card variant="panel">
              <Section variant="panel" title="Actividad">
                {activity.length ? (
                  <Timeline
                    variant="log"
                    className="sale-detail__activity"
                    aria-label="Actividad registrada"
                  >
                    {activity.map((item) => (
                      <TimelineItem
                        key={item.label}
                        status="complete"
                        title={item.label}
                        timestamp={saleDate(item.date, true)}
                        dateTime={item.date ?? undefined}
                        tone="info"
                      />
                    ))}
                  </Timeline>
                ) : (
                  <p>No hay fechas de actividad registradas.</p>
                )}
              </Section>
            </Card>
          </>
        }
      >
        <Card variant="panel">
          <Section variant="panel" title="Detalle">
            {itemsQuery.isPending ? (
              <p role="status">Cargando productos…</p>
            ) : itemsQuery.isError ? (
              <EmptyState
                size="sm"
                title="No se pudieron cargar los productos"
                action={
                  <Button onClick={() => void itemsQuery.refetch()}>Reintentar productos</Button>
                }
              />
            ) : (
              <DataTable
                testId="sale-detail-items"
                variant="flush"
                rows={itemsQuery.data ?? []}
                getRowKey={(item) => item.id}
                emptyMessage="No hay productos registrados en esta venta."
                columns={[
                  {
                    id: 'description',
                    header: 'Descripción',
                    render: (item) => (
                      <span>
                        {item.nameSnapshot || 'Producto sin descripción'}
                        {item.skuSnapshot && (
                          <small className="sale-detail__sku">{item.skuSnapshot}</small>
                        )}
                      </span>
                    ),
                  },
                  {
                    id: 'qty',
                    header: 'Cant.',
                    align: 'right',
                    render: (item) =>
                      new Intl.NumberFormat('es-PE', { maximumFractionDigits: 3 }).format(
                        Number(item.qty),
                      ),
                  },
                  {
                    id: 'price',
                    header: 'P. unitario',
                    align: 'right',
                    nowrap: true,
                    render: (item) => money(item.unitPrice),
                  },
                  {
                    id: 'total',
                    header: 'Importe',
                    align: 'right',
                    nowrap: true,
                    render: (item) => money(item.netAmount),
                  },
                ]}
              />
            )}
            <div className="sale-detail__totals">
              <DescriptionList
                items={[
                  { term: 'Subtotal', value: money(sale.subtotal) },
                  { term: 'Descuento', value: money(sale.discountTotal) },
                  { term: 'Impuestos', value: money(sale.taxTotal) },
                  ...(Number(sale.cashRoundingAdjustment ?? 0) !== 0
                    ? [{ term: 'Ajuste de efectivo', value: money(sale.cashRoundingAdjustment) }]
                    : []),
                  { term: 'Total', value: <strong>{money(sale.grandTotal)}</strong> },
                ]}
              />
            </div>
            {sale.note && <p className="sale-detail__note">{sale.note}</p>}
          </Section>
        </Card>
        <Card variant="panel">
          <Section variant="panel" title="Pagos y vencimiento">
            <DescriptionList
              items={[
                { term: 'Saldo pendiente', value: <strong>{money(sale.balanceDue)}</strong> },
                ...(Number(sale.changeTotal) > 0
                  ? [{ term: 'Vuelto', value: money(sale.changeTotal) }]
                  : []),
                ...(sale.dueDate ? [{ term: 'Vencimiento', value: saleDate(sale.dueDate) }] : []),
              ]}
            />
            <DataTable
              testId="sale-detail-payments"
              variant="flush"
              rows={(sale.payments ?? []).map((payment, index) => ({ ...payment, rowKey: index }))}
              getRowKey={(payment) => payment.rowKey}
              emptyMessage="No hay pagos registrados."
              columns={[
                { id: 'date', header: 'Fecha', render: (payment) => saleDate(payment.paid_at) },
                {
                  id: 'method',
                  header: 'Método',
                  render: (payment) => payment.method_name || 'Sin método registrado',
                },
                {
                  id: 'reference',
                  header: 'Referencia',
                  render: (payment) => payment.reference || '—',
                },
                {
                  id: 'amount',
                  header: 'Monto',
                  align: 'right',
                  nowrap: true,
                  render: (payment) => money(payment.amount),
                },
              ]}
            />
          </Section>
        </Card>
      </DetailLayout>
      {collecting && canCollect && (
        <SaleCollectPaymentDialog
          sale={sale}
          onClose={() => setCollecting(false)}
          onDone={() => {
            setCollecting(false);
            refresh();
          }}
        />
      )}
      <SalePrintPreviewPopup
        saleId={sale.id}
        visible={preview}
        onNegative={() => setPreview(false)}
        onPositive={() => {
          setPreview(false);
          openPdf();
        }}
      />
    </Page>
  );
}

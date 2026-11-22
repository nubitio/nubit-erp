import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import {
  Alert,
  Badge,
  Card,
  DataTable,
  EmptyState,
  KpiMetricRow,
  Section,
  Skeleton,
  Stack,
  Text,
  type DataTableColumn,
} from '@nubitio/ui';
import NumberUtils from '@shared/lib/NumberUtils';
import { countBySeller, formatPercent, totalImpact, type PriceReviewLine } from './priceReview';
import { usePriceReview } from './usePriceReview';

const money = (value: string | number) => NumberUtils.formatCurrency(Number(value), 'PEN');

const formatDate = (iso: string | null): string => {
  if (!iso) return '—';
  const date = new Date(iso);
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
};

/**
 * "Facturación › Precios a revisar": offline sales that were accepted at a price other than
 * the one in force when they reached the server. Nothing here changes a sale — the customer
 * already has the ticket — it is only for someone to look at.
 */
export function PriceReviewPage() {
  const review = usePriceReview();
  const lines = useMemo(() => review.data ?? [], [review.data]);
  const impact = totalImpact(lines);
  const sellers = useMemo(() => countBySeller(lines), [lines]);

  const columns = useMemo<Array<DataTableColumn<PriceReviewLine>>>(
    () => [
      {
        id: 'document',
        header: 'Comprobante',
        nowrap: true,
        render: (row) => <Link to={`/billing/sales/${row.saleId}`}>{row.document || '—'}</Link>,
      },
      {
        id: 'issuedAt',
        header: 'Fecha',
        nowrap: true,
        accessor: (row) => formatDate(row.issuedAt),
      },
      { id: 'seller', header: 'Vendedor', accessor: (row) => row.seller ?? '—' },
      { id: 'product', header: 'Producto', accessor: (row) => row.product },
      {
        id: 'quantity',
        header: 'Cantidad',
        align: 'right',
        accessor: (row) => Number(row.quantity),
      },
      {
        id: 'charged',
        header: 'Cobrado',
        align: 'right',
        accessor: (row) => money(row.chargedPrice),
      },
      {
        id: 'expected',
        header: 'Vigente',
        align: 'right',
        accessor: (row) => money(row.expectedPrice),
      },
      {
        id: 'difference',
        header: 'Diferencia',
        align: 'right',
        render: (row) => (
          <Badge variant={Number(row.difference) < 0 ? 'danger' : 'success'} size="sm">
            {formatPercent(row.differencePercent)}
          </Badge>
        ),
      },
      { id: 'impact', header: 'Efecto', align: 'right', accessor: (row) => money(row.impact) },
    ],
    [],
  );

  return (
    <Stack gap={4} data-testid="price-review-page">
      <Alert tone="info">
        Ventas hechas sin conexión que se aceptaron con un precio distinto al vigente al llegar al
        servidor, normalmente porque esa caja no había actualizado su catálogo. El cliente ya
        recibió su comprobante, así que aquí solo se revisan.
      </Alert>

      {review.isError ? (
        <Alert tone="danger">
          No se pudo cargar el listado. Inténtalo de nuevo en unos minutos.
        </Alert>
      ) : (
        <>
          <Section title="Resumen" variant="panel">
            <KpiMetricRow
              layout="cards"
              density="compact"
              loading={review.isPending}
              items={[
                { label: 'Líneas a revisar', value: lines.length },
                {
                  label: 'Efecto sobre ventas',
                  value: money(impact),
                  tone: impact < 0 ? 'danger' : 'default',
                  hint: 'Contra el precio vigente',
                },
                ...sellers.slice(0, 2).map((entry) => ({
                  label: entry.seller,
                  value: entry.count,
                  hint: 'líneas',
                })),
              ]}
            />
          </Section>

          <Card variant="panel">
            {review.isPending ? (
              <Skeleton />
            ) : lines.length === 0 ? (
              <EmptyState
                icon="check-circle"
                title="Nada que revisar"
                description="Ninguna venta sin conexión se aceptó con un precio distinto al vigente."
              />
            ) : (
              <Stack gap={2}>
                <DataTable
                  variant="flush"
                  columns={columns}
                  rows={lines}
                  getRowKey={(row) => row.lineId}
                  testId="price-review-table"
                />
                <Text tone="muted">
                  Se muestran las últimas 500 líneas, de la más reciente a la más antigua.
                </Text>
              </Stack>
            )}
          </Card>
        </>
      )}
    </Stack>
  );
}

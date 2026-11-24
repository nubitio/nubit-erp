import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Alert, Badge, DataTable, Pagination, type DataTableColumn } from '@nubitio/ui';
import NumberUtils from '@shared/lib/NumberUtils';
import type { CashSessionRecentDto } from '../services/cashboxService';
import { useCashboxSummary, useRecentShifts } from '../hooks/useCashboxOverview';
import { openClosureReport } from '../model/closureReport';
import { closureOutcome, formatDateTime, formatSignedMoney, shiftCode } from '../model/format';

const STALE_AFTER_MS = 24 * 60 * 60 * 1000;

function StatusBadge({ shift, now }: { shift: CashSessionRecentDto; now: number }) {
  if (shift.status === 'open') {
    const stale = now - new Date(shift.openedAt).getTime() > STALE_AFTER_MS;
    return stale ? (
      <Badge variant="warning" pill outlined>
        Arqueo pendiente
      </Badge>
    ) : (
      <Badge variant="success" pill outlined>
        Abierto
      </Badge>
    );
  }

  const outcome = closureOutcome(shift.difference);
  if (outcome === 'shortage' || outcome === 'overage') {
    return (
      <Badge variant={outcome === 'shortage' ? 'danger' : 'warning'} pill outlined>
        Cerrado · {outcome === 'shortage' ? 'faltante' : 'sobrante'}{' '}
        {formatSignedMoney(Number(shift.difference))}
      </Badge>
    );
  }

  return (
    <Badge variant={outcome === 'balanced' ? 'success' : 'secondary'} pill outlined>
      {outcome === 'balanced' ? 'Cerrado · cuadrado' : 'Cerrado'}
    </Badge>
  );
}

interface Props {
  /** Rows per page. */
  limit: number;
  title?: string;
  /** Offer pages (the history); without it the table is just the latest `limit` shifts. */
  paged?: boolean;
  /** Only the shifts of this register. */
  registerId?: number;
}

/** The latest shifts of the branch, open or closed, with what each sold and how it closed. */
export function RecentShiftsTable({
  limit,
  title = 'Turnos recientes',
  paged = false,
  registerId,
}: Props) {
  const navigate = useNavigate();
  const [page, setPage] = useState(1);
  const { data, dataUpdatedAt, isLoading, isError } = useRecentShifts(
    limit,
    paged ? page : 1,
    registerId,
  );
  const summary = useCashboxSummary(registerId);
  const total = summary.data?.shiftsTotal ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / limit));
  const rows = data ?? [];
  // The moment the list was read: a shift is stale as of then, and the render stays pure.
  const now = dataUpdatedAt;

  const columns: DataTableColumn<CashSessionRecentDto>[] = [
    {
      id: 'code',
      header: 'Turno',
      render: (shift) => (
        <Link className="cashbox-shift-code" to={`/cash-session/${shift.id}/movements`}>
          {shiftCode(shift.id)}
        </Link>
      ),
    },
    // Narrowed to one register, the column would repeat the same name in every row.
    ...(registerId
      ? []
      : [
          {
            id: 'register',
            header: 'Caja',
            render: (shift: CashSessionRecentDto) => shift.cashRegisterName,
          },
        ]),
    { id: 'cashier', header: 'Cajero', render: (shift) => shift.cashier ?? '—' },
    {
      id: 'opened',
      nowrap: true,
      header: 'Apertura',
      render: (shift) => formatDateTime(shift.openedAt),
    },
    {
      id: 'closed',
      nowrap: true,
      header: 'Cierre',
      render: (shift) => (shift.closedAt ? formatDateTime(shift.closedAt) : '—'),
    },
    {
      id: 'opening',
      header: 'Fondo inicial',
      align: 'right',
      render: (shift) => NumberUtils.formatCurrency(Number(shift.openingAmount)),
    },
    {
      id: 'sales',
      header: 'Ventas',
      align: 'right',
      render: (shift) => NumberUtils.formatCurrency(Number(shift.sales)),
    },
    {
      id: 'status',
      nowrap: true,
      header: 'Estado',
      render: (shift) => <StatusBadge shift={shift} now={now} />,
    },
  ];

  return (
    <section className="cashbox-panel" data-testid="cashbox-recent-shifts">
      <header className="cashbox-panel__header">
        <h3 className="cashbox-panel__title">{title}</h3>
        {!isLoading && rows.length > 0 && (
          <span className="cashbox-panel__meta">
            {paged && total > 0
              ? `Mostrando ${(page - 1) * limit + 1}–${(page - 1) * limit + rows.length} de ${total} turnos`
              : rows.length === 1
                ? '1 turno'
                : `${rows.length} turnos`}
          </span>
        )}
      </header>
      {isError ? (
        <Alert tone="danger">No se pudieron cargar los turnos.</Alert>
      ) : (
        <DataTable
          columns={columns}
          rows={rows}
          variant="flush"
          getRowKey={(shift) => String(shift.id)}
          rowActionsLabel="Acciones del turno"
          rowActions={(shift) => [
            {
              label: 'Ver turno',
              icon: 'ph ph-eye',
              onClick: () => navigate(`/cash-session/${shift.id}/movements`),
            },
            ...(shift.status === 'closed'
              ? [
                  {
                    label: 'Reporte PDF',
                    icon: 'ph ph-file-pdf',
                    onClick: () => openClosureReport(shift.id),
                  },
                ]
              : []),
          ]}
          emptyMessage={isLoading ? 'Cargando turnos…' : 'Todavía no hay turnos.'}
        />
      )}
      {paged && totalPages > 1 && (
        <footer className="cashbox-panel__footer">
          <Pagination
            page={page}
            totalPages={totalPages}
            onPageChange={setPage}
            ariaLabel="Páginas del historial de turnos"
          />
        </footer>
      )}
    </section>
  );
}

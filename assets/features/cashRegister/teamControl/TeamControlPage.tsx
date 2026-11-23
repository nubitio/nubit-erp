import { useMemo, useState } from 'react';
import {
  Alert,
  AppDropdown,
  Badge,
  Button,
  Card,
  DataTable,
  Drawer,
  EmptyState,
  KpiMetricRow,
  Page,
  PageHeader,
  Skeleton,
  Stack,
  Text,
} from '@nubitio/ui';
import { formatMoney } from '@shared/lib/displayFormat';
import {
  EVENT_LABELS,
  RANGE_OPTIONS,
  describeDifference,
  formatEventTime,
  formatPercent,
  rangeToDates,
  type CashDifference,
  type MemberEvent,
  type TeamControlRange,
  type TeamMember,
} from './teamControlFormat';
import { useMemberEvents, useTeamControl } from './useTeamControl';

/**
 * "Caja › Control": for the owner, how each person's sales, voids, discounts and cash closes
 * compare. It raises signals worth a conversation; it never accuses. Read-only.
 */
export function TeamControlPage() {
  const [range, setRange] = useState<TeamControlRange>('7d');
  const period = useMemo(() => rangeToDates(range), [range]);
  const overview = useTeamControl(period);
  const [selected, setSelected] = useState<{ userId: number; name: string } | null>(null);
  const events = useMemberEvents(selected?.userId ?? null, period);

  const data = overview.data;
  const unattributedCount =
    (data?.unattributed.voidCount ?? 0) + (data?.unattributed.creditNoteCount ?? 0);

  return (
    <Page wide gap={4} data-testid="team-control-page">
      <PageHeader
        title="Control de caja y equipo"
        subtitle="Compara a cada persona con el resto del equipo. Una señal es una razón para conversar y revisar, no una acusación."
        actions={
          <AppDropdown
            variant="toolbar"
            ariaLabel="Periodo"
            icon="ph ph-calendar-blank"
            value={range}
            options={RANGE_OPTIONS}
            onChange={(value) => setRange(value as TeamControlRange)}
          />
        }
      />

      {overview.isError ? (
        <Alert tone="danger">
          No se pudo cargar el control de caja.{' '}
          <Button variant="secondary" size="sm" onClick={() => void overview.refetch()}>
            Reintentar
          </Button>
        </Alert>
      ) : (
        <>
          <KpiMetricRow
            layout="cards"
            density="compact"
            loading={overview.isPending}
            testId="team-control-kpis"
            items={[
              {
                label: 'Vendido en el periodo',
                value: formatMoney(data?.team.salesTotal ?? 0),
                icon: 'ph ph-coins',
              },
              { label: 'Ventas', value: data?.team.salesCount ?? 0, icon: 'ph ph-receipt' },
              {
                label: 'Para revisar',
                value: data?.team.membersToReview ?? 0,
                tone: (data?.team.membersToReview ?? 0) > 0 ? 'warning' : 'success',
                hint:
                  (data?.team.membersToReview ?? 0) > 0
                    ? 'personas con señales'
                    : 'sin señales en este periodo',
                icon: 'ph ph-eye',
              },
            ]}
          />

          <Card variant="panel" title="Por colaborador">
            <Stack gap={3}>
              {overview.isPending ? (
                <div role="status" aria-label="Cargando control de caja">
                  <Skeleton variant="text" lines={5} />
                </div>
              ) : (data?.members.length ?? 0) === 0 ? (
                <div data-testid="team-control-empty">
                  <EmptyState
                    icon="users-three"
                    title="Sin ventas en este periodo"
                    description="Cuando el equipo venda, aquí verás cómo se compara cada persona."
                    size="sm"
                  />
                </div>
              ) : (
                <DataTable<TeamMember>
                  variant="flush"
                  testId="team-control-members"
                  rows={data?.members ?? []}
                  getRowKey={(member) => member.userId}
                  columns={[
                    {
                      id: 'name',
                      header: 'Colaborador',
                      render: (member) => (
                        <Stack gap={1}>
                          <span>
                            <strong>{member.name}</strong>{' '}
                            {member.level === 'review' ? (
                              <Badge variant="warning" size="sm">
                                Revisar
                              </Badge>
                            ) : (
                              <Badge variant="success" size="sm" outlined>
                                En orden
                              </Badge>
                            )}
                          </span>
                          {member.signals.map((signal) => (
                            <Text key={signal.key} tone="muted">
                              <i className="ph ph-warning" aria-hidden="true" /> {signal.detail}
                            </Text>
                          ))}
                        </Stack>
                      ),
                    },
                    {
                      id: 'sales',
                      header: 'Ventas',
                      align: 'right',
                      nowrap: true,
                      render: (member) => (
                        <>
                          {formatMoney(member.metrics.salesTotal)}
                          <br />
                          <Text tone="muted">{member.metrics.salesCount} ventas</Text>
                        </>
                      ),
                    },
                    {
                      id: 'voids',
                      header: 'Anulaciones',
                      align: 'right',
                      nowrap: true,
                      render: (member) =>
                        member.metrics.voidCount === 0 ? (
                          <Text tone="muted">—</Text>
                        ) : (
                          <>
                            {member.metrics.voidCount} · {formatMoney(member.metrics.voidTotal)}
                            <br />
                            <Text tone="muted">{formatPercent(member.voidRate)} de sus ventas</Text>
                          </>
                        ),
                    },
                    {
                      id: 'creditNotes',
                      header: 'Notas de crédito',
                      align: 'right',
                      nowrap: true,
                      render: (member) =>
                        member.metrics.creditNoteCount === 0 ? (
                          <Text tone="muted">—</Text>
                        ) : (
                          <>
                            {member.metrics.creditNoteCount} ·{' '}
                            {formatMoney(member.metrics.creditNoteTotal)}
                          </>
                        ),
                    },
                    {
                      id: 'discounts',
                      header: 'Descuentos',
                      align: 'right',
                      nowrap: true,
                      render: (member) =>
                        member.metrics.discountTotal === 0 ? (
                          <Text tone="muted">—</Text>
                        ) : (
                          <>
                            {formatMoney(member.metrics.discountTotal)}
                            <br />
                            <Text tone="muted">
                              {formatPercent(member.discountRate)} de lo vendido
                            </Text>
                          </>
                        ),
                    },
                    {
                      id: 'cash',
                      header: 'Cierres con faltante',
                      align: 'right',
                      nowrap: true,
                      render: (member) =>
                        member.metrics.cashShortCount === 0 ? (
                          <Text tone="muted">
                            {member.metrics.cashSessions} cierres, sin faltante
                          </Text>
                        ) : (
                          <>
                            {member.metrics.cashShortCount} ·{' '}
                            {formatMoney(Math.abs(member.metrics.cashShortage))}
                          </>
                        ),
                    },
                    {
                      id: 'detail',
                      header: '',
                      align: 'right',
                      render: (member) => (
                        <Button
                          variant="ghost"
                          size="sm"
                          icon="ph ph-list-magnifying-glass"
                          data-testid={`team-control-detail-${member.userId}`}
                          onClick={() => setSelected({ userId: member.userId, name: member.name })}
                        >
                          Ver detalle
                        </Button>
                      ),
                    },
                  ]}
                />
              )}
            </Stack>

            {unattributedCount > 0 ? (
              <Text tone="muted">
                <i className="ph ph-info" aria-hidden="true" /> Hay {data?.unattributed.voidCount}{' '}
                anulaciones y {data?.unattributed.creditNoteCount} notas de crédito de este periodo
                registradas antes de que el sistema guardara quién las hizo; no se pueden atribuir a
                nadie.{' '}
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setSelected({ userId: 0, name: 'Sin autor registrado' })}
                >
                  Verlas
                </Button>
              </Text>
            ) : null}
          </Card>

          {(data?.cashDifferences.length ?? 0) > 0 ? (
            <Card variant="panel" title="Últimos cierres de caja con diferencia">
              <Stack gap={3}>
                <DataTable<CashDifference>
                  variant="flush"
                  testId="team-control-cash-differences"
                  rows={data?.cashDifferences ?? []}
                  getRowKey={(row) => row.sessionId}
                  columns={[
                    {
                      id: 'at',
                      header: 'Cierre',
                      nowrap: true,
                      render: (r) => formatEventTime(r.closedAt),
                    },
                    { id: 'name', header: 'Cajero', render: (r) => r.name },
                    {
                      id: 'expected',
                      header: 'Esperado',
                      align: 'right',
                      render: (r) => formatMoney(r.expected),
                    },
                    {
                      id: 'counted',
                      header: 'Contado',
                      align: 'right',
                      render: (r) => formatMoney(r.counted),
                    },
                    {
                      id: 'difference',
                      header: 'Diferencia',
                      align: 'right',
                      nowrap: true,
                      render: (r) => (
                        <Badge variant={r.difference < 0 ? 'danger' : 'info'} size="sm">
                          {formatMoney(Math.abs(r.difference))} {describeDifference(r.difference)}
                        </Badge>
                      ),
                    },
                  ]}
                />
              </Stack>
              <Text tone="muted">
                Las diferencias menores a {formatMoney(data?.cashTolerance ?? 1)} no se cuentan:
                suelen ser redondeo de monedas.
              </Text>
            </Card>
          ) : null}
        </>
      )}

      <Drawer
        isOpen={selected !== null}
        onClose={() => setSelected(null)}
        title={selected ? `Detalle de ${selected.name}` : ''}
        width={560}
      >
        {events.isError ? (
          <Alert tone="danger">No se pudo cargar el detalle.</Alert>
        ) : events.isPending ? (
          <Skeleton variant="text" lines={6} />
        ) : (events.data?.length ?? 0) === 0 ? (
          <EmptyState
            icon="check-circle"
            title="Nada para revisar"
            description="No hay anulaciones, notas de crédito, descuentos ni diferencias de caja en este periodo."
            size="sm"
          />
        ) : (
          <DataTable<MemberEvent>
            variant="flush"
            testId="team-control-events"
            rows={events.data ?? []}
            getRowKey={(event) =>
              `${event.type}-${event.at}-${event.reference ?? ''}-${event.amount}`
            }
            columns={[
              {
                id: 'type',
                header: 'Qué pasó',
                render: (event) => (
                  <Stack gap={1}>
                    <strong>{EVENT_LABELS[event.type]}</strong>
                    {event.note ? <Text tone="muted">{event.note}</Text> : null}
                  </Stack>
                ),
              },
              {
                id: 'at',
                header: 'Cuándo',
                nowrap: true,
                render: (event) => formatEventTime(event.at),
              },
              {
                id: 'reference',
                header: 'Documento',
                nowrap: true,
                render: (event) => event.reference ?? '—',
              },
              {
                id: 'amount',
                header: 'Monto',
                align: 'right',
                nowrap: true,
                render: (event) => formatMoney(event.amount),
              },
            ]}
          />
        )}
      </Drawer>
    </Page>
  );
}

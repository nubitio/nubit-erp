import { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  AppDropdown,
  Button,
  Card,
  Cluster,
  EmptyState,
  KpiMetricRow,
  Skeleton,
  Stack,
  Text,
  TextField,
} from '@nubitio/ui';

import { ActivityEntry } from './ActivityEntry';
import {
  DEFAULT_FILTERS,
  groupByDay,
  RANGE_OPTIONS,
  summaryTone,
  TYPE_OPTIONS,
  type ActivityFilters,
  type ActivityRange,
  type ActivityTypeFilter,
} from './activityFormat';
import {
  activityExportUrl,
  useActivityActors,
  useActivityList,
  useActivitySummary,
} from './useActivity';

import './ActivityPage.scss';

const SEARCH_DEBOUNCE_MS = 300;

/** Debounced copy of a value; state is only set from the timer callback, never synchronously. */
function useDebounced<T>(value: T, delay: number): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);
  return debounced;
}

/** "Usuarios y permisos › Actividad": who did what, newest first. Read-only. */
export function ActivityPage() {
  const [filters, setFilters] = useState<ActivityFilters>(DEFAULT_FILTERS);
  const [searchText, setSearchText] = useState('');
  const search = useDebounced(searchText, SEARCH_DEBOUNCE_MS);
  const effective = useMemo(() => ({ ...filters, search }), [filters, search]);

  const list = useActivityList(effective);
  const summary = useActivitySummary();
  const actors = useActivityActors();

  const items = useMemo(() => list.data?.pages.flatMap((page) => page.items) ?? [], [list.data]);
  const groups = useMemo(() => groupByDay(items), [items]);
  const total = list.data?.pages[0]?.total ?? 0;

  const set = <K extends keyof ActivityFilters>(key: K, value: ActivityFilters[K]) =>
    setFilters((current) => ({ ...current, [key]: value }));

  const actorOptions = useMemo(
    () => [
      { value: '', label: 'Todos los usuarios' },
      ...(actors.data ?? []).map((name) => ({ value: name, label: name })),
    ],
    [actors.data],
  );

  const summaryItems = summary.data?.items ?? [];

  return (
    <Stack gap={4} fill data-testid="activity-page">
      <section aria-label="Resumen de la semana" data-testid="activity-summary">
        <Text tone="muted" className="activity-eyebrow">
          Esta semana
        </Text>
        {summary.isPending || summaryItems.length > 0 ? (
          <KpiMetricRow
            layout="strip"
            density="compact"
            loading={summary.isPending}
            items={summaryItems.map((entry) => ({
              label: entry.label,
              value: entry.count,
              tone: summaryTone(entry.type),
            }))}
          />
        ) : (
          <Text tone="muted">Sin actividad registrada esta semana.</Text>
        )}
      </section>

      <Cluster gap={2}>
        <div className="activity-search">
          <TextField
            type="search"
            aria-label="Buscar en la actividad"
            placeholder="Buscar por usuario, rol o palabra"
            value={searchText}
            onChange={(event) => setSearchText(event.target.value)}
          />
        </div>
        <AppDropdown
          variant="toolbar"
          ariaLabel="Tipo"
          icon="ph ph-funnel"
          value={filters.type}
          options={TYPE_OPTIONS.map((option) => ({
            ...option,
            selectedLabel: `Tipo: ${option.label}`,
          }))}
          onChange={(value) => set('type', value as ActivityTypeFilter)}
        />
        <AppDropdown
          variant="toolbar"
          ariaLabel="Usuario"
          icon="ph ph-user"
          value={filters.actor}
          options={actorOptions}
          onChange={(value) => set('actor', value)}
        />
        <AppDropdown
          variant="toolbar"
          ariaLabel="Fecha"
          icon="ph ph-calendar-blank"
          value={filters.range}
          options={RANGE_OPTIONS}
          onChange={(value) => set('range', value as ActivityRange)}
        />
        <Button
          variant="secondary"
          icon="ph ph-download-simple"
          onClick={() => window.location.assign(activityExportUrl(effective))}
        >
          Exportar
        </Button>
      </Cluster>

      <Card variant="panel">
        <section aria-label="Actividad" className="activity-feed">
          {list.isError ? (
            <Alert tone="danger">
              No se pudo cargar la actividad.{' '}
              <Button variant="secondary" size="sm" onClick={() => void list.refetch()}>
                Reintentar
              </Button>
            </Alert>
          ) : list.isPending ? (
            <div role="status" aria-label="Cargando actividad">
              <Skeleton variant="text" lines={6} />
            </div>
          ) : groups.length === 0 ? (
            <div data-testid="activity-empty">
              <EmptyState
                icon="clock-counter-clockwise"
                title="No hay actividad con estos filtros"
                description="Prueba con otro tipo, otro usuario o un rango de fechas más amplio."
                size="sm"
              />
            </div>
          ) : (
            groups.map((group) => (
              <div key={group.key} className="activity-day">
                <h3 className="activity-day__heading">{group.heading}</h3>
                <ul className="activity-list">
                  {group.items.map((entry) => (
                    <ActivityEntry key={entry.id} entry={entry} />
                  ))}
                </ul>
              </div>
            ))
          )}

          {list.hasNextPage ? (
            <div className="activity-more">
              <Button
                variant="secondary"
                loading={list.isFetchingNextPage}
                onClick={() => void list.fetchNextPage()}
              >
                Ver más ({total - items.length} restantes)
              </Button>
            </div>
          ) : null}

          <Text tone="muted" className="activity-note">
            <i className="ph ph-shield-check" aria-hidden="true" /> Este registro no se puede editar
            ni borrar desde el sistema.
          </Text>
        </section>
      </Card>
    </Stack>
  );
}
